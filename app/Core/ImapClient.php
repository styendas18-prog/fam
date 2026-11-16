<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Dependency-free IMAP client (RFC 3501) tuned for Gmail.
 *
 * Why not ext-imap? It is missing on many shared hosts and cannot speak
 * XOAUTH2 cleanly. This client implements only what the verification pipeline
 * needs, but does it properly:
 *
 *   - implicit TLS (993) and STARTTLS (143)
 *   - LOGIN (Google App Password) and AUTHENTICATE XOAUTH2 (OAuth 2.0)
 *   - binary-safe literal handling (messages are never truncated or mangled)
 *   - UIDVALIDITY aware incremental scanning
 *   - capability discovery, mailbox listing and server ID
 *   - actionable error hints for the Gmail failures operators actually hit
 *
 * Security: certificate verification is always on. There is deliberately no
 * option to disable it; point IMAP_CA_BUNDLE at a CA file instead.
 */
final class ImapClient
{
    /** Gmail rejects messages above 50 MB; refuse absurd literals early. */
    private const MAX_LITERAL_BYTES = 52_428_800;

    /** @var resource|null */
    private $stream = null;
    private int $tag = 0;
    private string $readBuffer = '';
    private string $lastResponse = '';
    private string $greeting = '';
    /** @var string[] */
    private array $capabilities = [];
    private bool $authenticated = false;
    private string $authMethod = 'none';
    private ?string $lastHint = null;
    /** @var array<string,mixed> */
    private array $selected = [];

    public function __construct(
        private string $host,
        private int $port = 993,
        private string $encryption = 'ssl',
        private int $timeout = 20
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function connect(): void
    {
        if ($this->stream !== null) {
            return;
        }
        $mode = strtolower(trim($this->encryption));
        $implicitTls = in_array($mode, ['ssl', 'tls', 'imaps', 'tls-implicit'], true);
        $startTls = in_array($mode, ['starttls', 'tls-starttls'], true);

        $context = stream_context_create(['ssl' => $this->sslOptions()]);
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            ($implicitTls ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($stream === false) {
            $this->lastHint = $this->connectHint($errstr, $errno);
            throw new RuntimeException('IMAP connection failed: ' . ($errstr !== '' ? $errstr : 'unknown error') . ' (' . $errno . ').');
        }
        $this->stream = $stream;
        $this->readBuffer = '';
        stream_set_timeout($this->stream, $this->timeout);

        try {
            $greeting = $this->readLine();
        } catch (Throwable $e) {
            $this->close();
            throw new RuntimeException('IMAP server closed the connection before greeting: ' . $e->getMessage());
        }
        $this->greeting = trim($greeting);
        $upper = strtoupper($this->greeting);
        if (str_starts_with($upper, '* BYE')) {
            $this->lastHint = 'The server refused the session: ' . $this->greeting;
            $this->close();
            throw new RuntimeException('IMAP server rejected the connection: ' . $this->greeting);
        }
        if (!str_starts_with($upper, '* OK') && !str_starts_with($upper, '* PREAUTH')) {
            $this->lastHint = 'Unexpected IMAP greeting: ' . $this->greeting;
            $this->close();
            throw new RuntimeException('IMAP server did not return a valid greeting.');
        }

        if ($startTls) {
            $this->command('STARTTLS');
            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            $ok = @stream_socket_enable_crypto($this->stream, true, $method);
            if ($ok !== true) {
                $this->lastHint = 'STARTTLS was accepted but the TLS handshake failed. Check the port and the server certificate.';
                $this->close();
                throw new RuntimeException('Unable to negotiate TLS with the IMAP server.');
            }
            $this->capabilities = [];
        }

        try {
            $this->capabilities = $this->fetchCapabilities();
        } catch (Throwable) {
            // CAPABILITY is optional; commands still work without it.
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function sslOptions(): array
    {
        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $this->host,
        ];
        $caBundle = trim((string) Env::get('IMAP_CA_BUNDLE', ''));
        if ($caBundle !== '' && is_file($caBundle)) {
            $ssl['cafile'] = $caBundle;
        }
        return $ssl;
    }

    /** @return string[] */
    private function fetchCapabilities(): array
    {
        $text = $this->command('CAPABILITY')['text'];
        if (preg_match('/^\* CAPABILITY (.+)$/mi', $text, $match) !== 1) {
            return [];
        }
        return array_values(array_filter(array_map('strtoupper', preg_split('/\s+/', trim($match[1])) ?: [])));
    }

    /** @return string[] */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array(strtoupper($capability), $this->capabilities, true);
    }

    public function greeting(): string
    {
        return $this->greeting;
    }

    public function isTls(): bool
    {
        if ($this->stream === null) {
            return false;
        }
        $meta = stream_get_meta_data($this->stream);
        return !empty($meta['crypto']);
    }

    /**
     * Gmail's ID extension tells the server which client is connecting; it
     * improves server-side diagnostics and is harmless everywhere.
     */
    public function identify(string $name = 'MAXXfam', string $version = '1.0'): void
    {
        if (!$this->hasCapability('ID')) {
            return;
        }
        try {
            $this->command('ID ("name" "' . $this->escape($name) . '" "version" "' . $this->escape($version) . '" "vendor" "MAXXfam")');
        } catch (Throwable) {
            // Purely cosmetic.
        }
    }

    public function login(string $username, string $password): void
    {
        $this->command('LOGIN ' . $this->quote($username) . ' ' . $this->quote($password));
        $this->authenticated = true;
        $this->authMethod = 'login';
        $this->identify();
    }

    /**
     * Gmail OAuth 2.0 SASL. Uses SASL-IR when advertised, otherwise the
     * classic continuation handshake.
     */
    public function authenticateXoauth2(string $username, string $accessToken): void
    {
        $payload = self::xoauth2Payload($username, $accessToken);
        if ($this->hasCapability('SASL-IR')) {
            $this->command('AUTHENTICATE XOAUTH2 ' . $payload);
        } else {
            $this->continuationCommand('AUTHENTICATE XOAUTH2', $payload);
        }
        $this->authenticated = true;
        $this->authMethod = 'xoauth2';
        $this->identify();
    }

    public static function xoauth2Payload(string $username, string $accessToken): string
    {
        return base64_encode('user=' . $username . "\x01auth=Bearer " . $accessToken . "\x01\x01");
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function authMethod(): string
    {
        return $this->authMethod;
    }

    public function select(string $mailbox = 'INBOX'): string
    {
        $text = $this->command('SELECT ' . $this->quote($mailbox))['text'];
        $this->selected = $this->parseSelectResponse($text);
        return $text;
    }

    public function examine(string $mailbox = 'INBOX'): string
    {
        $text = $this->command('EXAMINE ' . $this->quote($mailbox))['text'];
        $this->selected = $this->parseSelectResponse($text);
        return $text;
    }

    /** @return array<string,mixed> */
    public function selected(): array
    {
        return $this->selected;
    }

    public function uidValidity(): int
    {
        return (int) ($this->selected['uidvalidity'] ?? 0);
    }

    /**
     * @param string|string[] $criteria
     * @return string[]
     */
    public function uidSearch(string|array $criteria = 'UNSEEN'): array
    {
        $query = is_array($criteria) ? implode(' ', $criteria) : $criteria;
        $text = $this->command('UID SEARCH ' . trim($query))['text'];
        if (preg_match('/^\* SEARCH(.*)$/mi', $text, $match) !== 1) {
            return [];
        }
        $ids = preg_split('/\s+/', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_values(array_filter($ids, static fn (string $id): bool => ctype_digit($id)));
        usort($ids, static fn (string $a, string $b): int => (int) $a <=> (int) $b);
        return $ids;
    }

    public function fetchRaw(string $uid): string
    {
        $result = $this->command('UID FETCH ' . (int) $uid . ' (BODY.PEEK[])');
        if (!isset($result['literals'][0])) {
            throw new RuntimeException('IMAP message body could not be extracted for UID ' . (int) $uid . '.');
        }
        return $result['literals'][0];
    }

    /**
     * @param string[] $fields
     */
    public function fetchHeaders(string $uid, array $fields = ['FROM', 'TO', 'SUBJECT', 'DATE', 'MESSAGE-ID']): string
    {
        $fields = array_map(static fn (string $f): string => preg_replace('/[^A-Za-z-]/', '', $f) ?? '', $fields);
        $fields = array_values(array_filter($fields));
        if (!$fields) {
            $fields = ['SUBJECT'];
        }
        $result = $this->command('UID FETCH ' . (int) $uid . ' (BODY.PEEK[HEADER.FIELDS (' . implode(' ', $fields) . ')])');
        if (!isset($result['literals'][0])) {
            return '';
        }
        return $result['literals'][0];
    }

    public function markSeen(string $uid): void
    {
        $this->command('UID STORE ' . (int) $uid . ' +FLAGS.SILENT (\\Seen)');
    }

    public function markUnseen(string $uid): void
    {
        $this->command('UID STORE ' . (int) $uid . ' -FLAGS.SILENT (\\Seen)');
    }

    public function noop(): void
    {
        $this->command('NOOP');
    }

    /**
     * @return array<int,array{name:string,delimiter:string,attributes:string[]}>
     */
    public function listMailboxes(string $reference = '', string $pattern = '*'): array
    {
        $text = $this->command('LIST ' . $this->quote($reference) . ' ' . $this->quote($pattern))['text'];
        $mailboxes = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if (preg_match('/^\* LIST \(([^)]*)\) (NIL|"[^"]*"|\S+) (.+)$/i', trim($line), $match) !== 1) {
                continue;
            }
            $name = $this->decodeAstring(trim($match[3]));
            if ($name === '') {
                continue;
            }
            $mailboxes[] = [
                'name' => $name,
                'delimiter' => $match[2] === 'NIL' ? '' : trim($match[2], '"'),
                'attributes' => array_values(array_filter(preg_split('/\s+/', trim($match[1])) ?: [])),
            ];
        }
        return $mailboxes;
    }

    public function logout(): void
    {
        if ($this->stream === null) {
            return;
        }
        try {
            $this->command('LOGOUT');
        } catch (Throwable) {
        }
        $this->close();
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
        $this->authenticated = false;
        $this->readBuffer = '';
    }

    public function lastResponse(): string
    {
        return $this->lastResponse;
    }

    /** Actionable explanation for the most recent failure, if we recognise it. */
    public function lastHint(): ?string
    {
        return $this->lastHint;
    }

    // ------------------------------------------------------------------
    // Protocol internals
    // ------------------------------------------------------------------

    /**
     * @return array{text:string,literals:string[]}
     */
    private function command(string $command): array
    {
        $tag = 'A' . (++$this->tag);
        $this->write($tag . ' ' . $command . "\r\n");
        $response = $this->readResponse($tag);
        $this->lastResponse = $response['text'];
        if (preg_match('/^' . preg_quote($tag, '/') . ' (OK|NO|BAD)\b/im', $response['text'], $match) !== 1) {
            $this->lastHint = 'The IMAP server did not answer the command in time.';
            throw new RuntimeException('IMAP command did not receive a tagged response.');
        }
        if (strtoupper($match[1]) !== 'OK') {
            $summary = $this->summarize($response['text']);
            $this->lastHint = $this->failureHint($summary);
            throw new RuntimeException('IMAP command rejected: ' . $summary);
        }
        return $response;
    }

    /**
     * AUTHENTICATE handshake for servers without SASL-IR.
     */
    private function continuationCommand(string $command, string $payload): array
    {
        $tag = 'A' . (++$this->tag);
        $this->write($tag . ' ' . $command . "\r\n");
        $text = '';
        $literals = [];
        $sent = false;
        while (true) {
            $line = $this->readLine();
            $text .= $line;
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '+')) {
                if (!$sent) {
                    $this->write($payload . "\r\n");
                    $sent = true;
                } else {
                    // Server sent a base64 error challenge; answer empty to finish.
                    $this->write("\r\n");
                }
                continue;
            }
            if (str_starts_with($trimmed, $tag . ' ')) {
                break;
            }
            if (preg_match('/\{(\d+)\}$/', trim($line), $match) === 1) {
                $literals[] = $this->readBytes((int) $match[1]);
            }
        }
        $this->lastResponse = $text;
        if (preg_match('/^' . preg_quote($tag, '/') . ' (OK|NO|BAD)\b/im', $text, $match) !== 1) {
            $this->lastHint = 'The IMAP server did not answer the AUTHENTICATE command.';
            throw new RuntimeException('IMAP authentication did not finish.');
        }
        if (strtoupper($match[1]) !== 'OK') {
            $summary = $this->summarize($text);
            $this->lastHint = $this->failureHint($summary);
            throw new RuntimeException('IMAP authentication rejected: ' . $summary);
        }
        return ['text' => $text, 'literals' => $literals];
    }

    /**
     * @return array{text:string,literals:string[]}
     */
    private function readResponse(string $tag): array
    {
        $text = '';
        $literals = [];
        while (true) {
            $line = $this->readLine();
            $text .= $line;
            if (preg_match('/\{(\d+)\}$/', rtrim($line, "\r\n"), $match) === 1) {
                $length = (int) $match[1];
                if ($length > self::MAX_LITERAL_BYTES) {
                    throw new RuntimeException('IMAP literal of ' . $length . ' bytes exceeds the safety limit.');
                }
                $literal = $this->readBytes($length);
                $literals[] = $literal;
                $text .= $literal;
                continue;
            }
            if (str_starts_with(ltrim($line), $tag . ' ')) {
                break;
            }
        }
        return ['text' => $text, 'literals' => $literals];
    }

    private function write(string $data): void
    {
        if ($this->stream === null) {
            throw new RuntimeException('IMAP client is not connected.');
        }
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = @fwrite($this->stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Unable to write to the IMAP connection.');
            }
            $written += $chunk;
        }
    }

    private function readLine(): string
    {
        while (($position = strpos($this->readBuffer, "\n")) === false) {
            $this->fill();
        }
        $line = substr($this->readBuffer, 0, $position + 1);
        $this->readBuffer = substr($this->readBuffer, $position + 1);
        return $line;
    }

    private function readBytes(int $length): string
    {
        while (strlen($this->readBuffer) < $length) {
            $this->fill();
        }
        $bytes = substr($this->readBuffer, 0, $length);
        $this->readBuffer = substr($this->readBuffer, $length);
        return $bytes;
    }

    private function fill(): void
    {
        if ($this->stream === null) {
            throw new RuntimeException('IMAP client is not connected.');
        }
        $chunk = @fread($this->stream, 65536);
        if ($chunk === false || $chunk === '') {
            $meta = stream_get_meta_data($this->stream);
            if (!empty($meta['timed_out'])) {
                $this->lastHint = 'The IMAP server stopped responding. Check the mailbox size and the network.';
                throw new RuntimeException('IMAP read timed out.');
            }
            $this->lastHint = 'The IMAP connection closed unexpectedly.';
            throw new RuntimeException('IMAP connection closed unexpectedly.');
        }
        $this->readBuffer .= $chunk;
    }

    /** @return array<string,mixed> */
    private function parseSelectResponse(string $text): array
    {
        $info = [
            'exists' => 0,
            'recent' => 0,
            'uidvalidity' => 0,
            'uidnext' => 0,
            'unseen' => 0,
            'read_only' => str_contains(strtoupper($text), '[READ-ONLY]'),
            'flags' => [],
        ];
        if (preg_match('/^\* (\d+) EXISTS\r?$/mi', $text, $match) === 1) {
            $info['exists'] = (int) $match[1];
        }
        if (preg_match('/^\* (\d+) RECENT\r?$/mi', $text, $match) === 1) {
            $info['recent'] = (int) $match[1];
        }
        if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $text, $match) === 1) {
            $info['uidvalidity'] = (int) $match[1];
        }
        if (preg_match('/\[UIDNEXT (\d+)\]/i', $text, $match) === 1) {
            $info['uidnext'] = (int) $match[1];
        }
        if (preg_match('/\[UNSEEN (\d+)\]/i', $text, $match) === 1) {
            $info['unseen'] = (int) $match[1];
        }
        if (preg_match('/^\* FLAGS \(([^)]*)\)/mi', $text, $match) === 1) {
            $info['flags'] = array_values(array_filter(preg_split('/\s+/', trim($match[1])) ?: []));
        }
        return $info;
    }

    private function quote(string $value): string
    {
        return '"' . $this->escape($value) . '"';
    }

    private function escape(string $value): string
    {
        return addcslashes($value, "\\\"");
    }

    private function decodeAstring(string $value): string
    {
        if ($value === '' || strtoupper($value) === 'NIL') {
            return '';
        }
        if ($value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }
        return trim($value);
    }

    private function summarize(string $response): string
    {
        $lines = preg_split('/\r?\n/', trim($response)) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/^(A\d+ )?(NO|BAD)\b(.*)$/i', $line, $match) === 1) {
                return trim(mb_substr($match[3], 0, 300));
            }
        }
        return mb_substr(trim($response), 0, 300);
    }

    private function connectHint(string $error, int $errno): ?string
    {
        $error = strtolower($error);
        if (str_contains($error, 'getaddrinfo') || str_contains($error, 'name or service not known')) {
            return 'DNS lookup failed. Check the IMAP host name and the server DNS resolver.';
        }
        if (str_contains($error, 'certificate') || str_contains($error, 'ssl')) {
            return 'TLS handshake failed. The server certificate did not verify; install a CA bundle and set IMAP_CA_BUNDLE.';
        }
        if (str_contains($error, 'timed out')) {
            return 'Connection timed out. The host firewall may block outbound port ' . $this->port . '.';
        }
        if ($errno === 10061 || str_contains($error, 'refused')) {
            return 'Connection refused. Check the port (993 for SSL, 143 for STARTTLS).';
        }
        return null;
    }

    /**
     * Translate the Gmail errors operators actually hit into fixes.
     */
    public function failureHint(string $message): ?string
    {
        $m = strtolower($message);
        return match (true) {
            str_contains($m, 'application-specific password required'),
            str_contains($m, 'invalid credentials'),
            str_contains($m, 'authenticationfailed') => 'Gmail rejected the password. Enable 2-Step Verification, create a 16-character App Password, and use that (never the normal Gmail password).',
            str_contains($m, 'web login required'),
            str_contains($m, 'please log in via your web browser') => 'Gmail blocked the sign-in as suspicious. Sign in to the Gmail account in a browser once, then retry with an App Password.',
            str_contains($m, 'xoauth2'),
            str_contains($m, 'invalid_token'),
            str_contains($m, 'expired') => 'The OAuth access token was rejected. Reconnect Gmail OAuth so a fresh refresh token is stored.',
            str_contains($m, 'too many simultaneous connections') => 'Gmail allows 15 simultaneous IMAP sessions per account. Close other mail clients or reduce cron frequency.',
            str_contains($m, 'imap access is disabled') => 'IMAP is disabled for this mailbox. Enable it in Gmail Settings > Forwarding and POP/IMAP.',
            str_contains($m, 'unsafe login'),
            str_contains($m, 'less secure') => 'Google no longer allows "less secure app" logins. Use an App Password or OAuth 2.0.',
            str_contains($m, 'domain') && str_contains($m, 'disabled') => 'The Workspace administrator disabled IMAP or App Passwords for this domain. Use OAuth 2.0 instead.',
            str_contains($m, 'mailbox') && str_contains($m, 'not found') => 'The folder name does not exist. Gmail labels need their full path, for example [Gmail]/All Mail.',
            str_contains($m, 'server busy'),
            str_contains($m, 'try again') => 'Gmail is throttling this mailbox. Retry in a few minutes.',
            default => null,
        };
    }
}
