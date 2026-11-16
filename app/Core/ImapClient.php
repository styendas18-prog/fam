<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Minimal, dependency-free IMAP client for Gmail over implicit TLS.
 *
 * The PHP `imap` extension is not available on every host, so this client
 * speaks just enough of RFC 3501 to authenticate, select a mailbox, search
 * UIDs and fetch full messages. It never disables certificate verification
 * unless the operator explicitly configures a CA bundle that verifies.
 */
final class ImapClient
{
    /** @var resource|null */
    private $stream = null;
    private int $tag = 0;
    private string $lastResponse = '';

    public function __construct(
        private string $host,
        private int $port = 993,
        private string $encryption = 'ssl',
        private int $timeout = 20
    ) {
    }

    public function connect(): void
    {
        $scheme = in_array($this->encryption, ['ssl', 'tls'], true) ? 'ssl' : 'tcp';
        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
        ];
        $caBundle = trim((string) Env::get('IMAP_CA_BUNDLE', ''));
        if ($caBundle !== '') {
            $ssl['cafile'] = $caBundle;
        }
        $context = stream_context_create(['ssl' => $ssl]);
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            $scheme . '://' . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($stream === false) {
            throw new RuntimeException('IMAP connection failed: ' . ($errstr !== '' ? $errstr : 'unknown error') . ' (' . $errno . ').');
        }
        $this->stream = $stream;
        stream_set_timeout($this->stream, $this->timeout);
        $greeting = $this->readLine();
        if (!str_starts_with(strtoupper($greeting), '* OK')) {
            throw new RuntimeException('IMAP server did not return a valid greeting.');
        }
    }

    public function login(string $username, string $password): void
    {
        $this->command('LOGIN ' . $this->quote($username) . ' ' . $this->quote($password));
    }

    public function select(string $mailbox = 'INBOX'): string
    {
        return $this->command('SELECT ' . $this->quote($mailbox));
    }

    /** @return string[] */
    public function uidSearch(string $criteria = 'UNSEEN'): array
    {
        $response = $this->command('UID SEARCH ' . $criteria);
        if (preg_match('/^\* SEARCH(.*)$/mi', $response, $match) !== 1) {
            return [];
        }
        $ids = preg_split('/\s+/', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter($ids, static fn (string $id): bool => ctype_digit($id)));
    }

    public function fetchRaw(string $uid): string
    {
        $response = $this->command('UID FETCH ' . (int) $uid . ' (BODY.PEEK[])');
        if (preg_match('/\{(\d+)\}\r?\n/', $response, $match, PREG_OFFSET_CAPTURE) === 1) {
            $length = (int) $match[1][0];
            $start = $match[0][1] + strlen($match[0][0]);
            $raw = substr($response, $start, $length);
            if ($raw !== '' && strlen($raw) === $length) {
                return $raw;
            }
        }
        throw new RuntimeException('IMAP message body could not be extracted.');
    }

    public function markSeen(string $uid): void
    {
        $this->command('UID STORE ' . (int) $uid . ' +FLAGS (\\Seen)');
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
        @fclose($this->stream);
        $this->stream = null;
    }

    public function lastResponse(): string
    {
        return $this->lastResponse;
    }

    private function command(string $command): string
    {
        $tag = 'A' . (++$this->tag);
        $this->write($tag . ' ' . $command . "\r\n");
        $response = $this->readResponse($tag);
        $this->lastResponse = $response;
        if (preg_match('/^' . preg_quote($tag, '/') . ' (OK|NO|BAD)\b/im', $response, $match) !== 1) {
            throw new RuntimeException('IMAP command did not receive a tagged response.');
        }
        if (strtoupper($match[1]) !== 'OK') {
            throw new RuntimeException('IMAP command rejected: ' . $this->summarize($response));
        }
        return $response;
    }

    private function write(string $data): void
    {
        if ($this->stream === null) {
            throw new RuntimeException('IMAP client is not connected.');
        }
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = fwrite($this->stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Unable to write to the IMAP connection.');
            }
            $written += $chunk;
        }
    }

    private function readResponse(string $tag): string
    {
        $buffer = '';
        while (true) {
            $line = $this->readLine();
            $buffer .= $line;
            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $match) === 1) {
                $remaining = (int) $match[1];
                while ($remaining > 0) {
                    if ($this->stream === null) {
                        throw new RuntimeException('IMAP connection closed while reading a message.');
                    }
                    $chunk = fread($this->stream, $remaining);
                    if ($chunk === false || $chunk === '') {
                        throw new RuntimeException('IMAP connection closed while reading a message.');
                    }
                    $buffer .= $chunk;
                    $remaining -= strlen($chunk);
                }
                continue;
            }
            if (preg_match('/^' . preg_quote($tag, '/') . ' /i', $line) === 1) {
                break;
            }
        }
        return $buffer;
    }

    private function readLine(): string
    {
        if ($this->stream === null) {
            throw new RuntimeException('IMAP client is not connected.');
        }
        $line = fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'IMAP read timed out.' : 'IMAP connection closed unexpectedly.');
        }
        return $line;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
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
}
