<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use RuntimeException;
use Throwable;

final class Mailer
{
    public static function queue(string $to, ?string $name, string $subject, string $html, ?string $text = null): int
    {
        return Database::insert(
            'INSERT INTO email_queue (to_email, to_name, subject, html_body, text_body, status, attempts, scheduled_at, created_at)
             VALUES (?, ?, ?, ?, ?, "pending", 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$to, $name, $subject, $html, $text]
        );
    }

    public static function processQueue(int $limit = 20): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM email_queue WHERE status = "pending" AND scheduled_at <= UTC_TIMESTAMP() ORDER BY scheduled_at ASC LIMIT ' . max(1, min(100, $limit))
        );
        $results = [];
        foreach ($rows as $mail) {
            try {
                self::send($mail['to_email'], $mail['to_name'] ?? '', $mail['subject'], $mail['html_body'], $mail['text_body'] ?? null);
                Database::execute('UPDATE email_queue SET status = "sent", attempts = attempts + 1, sent_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = ?', [$mail['id']]);
                $results[] = ['id' => $mail['id'], 'sent' => true];
            } catch (Throwable $e) {
                $attempts = (int) $mail['attempts'] + 1;
                $failed = $attempts >= 6;
                Database::execute(
                    'UPDATE email_queue SET status = ?, attempts = ?, last_error = ?, scheduled_at = ? WHERE id = ?',
                    [
                        $failed ? 'failed' : 'pending',
                        $attempts,
                        mb_substr($e->getMessage(), 0, 500),
                        gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** $attempts))),
                        $mail['id'],
                    ]
                );
                Logger::warning('Email delivery failed', ['queue_id' => $mail['id'], 'error' => $e->getMessage()]);
                $results[] = ['id' => $mail['id'], 'sent' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function send(string $to, string $name, string $subject, string $html, ?string $text = null): void
    {
        $config = self::smtpConfig();
        if ($config['host'] === '' || $config['from_address'] === '') {
            throw new RuntimeException('SMTP is not configured. Set SMTP host and from address in Admin → SMTP Settings.');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient address.');
        }
        $boundary = 'mf_' . bin2hex(random_bytes(12));
        $fromName = self::encodeHeader($config['from_name']);
        $subjectEncoded = self::encodeHeader($subject);
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $fromName . ' <' . $config['from_address'] . '>',
            'To: ' . ($name !== '' ? self::encodeHeader($name) . ' <' . $to . '>' : $to),
            'Subject: ' . $subjectEncoded,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url((string) Config::get('url'), PHP_URL_HOST) ?: 'maxxfam.local') . '>',
            'MIME-Version: 1.0',
            'X-Mailer: MAXXfam',
        ];
        if ($text !== null) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($text)) . "\r\n--" . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html)) . "\r\n--" . $boundary . "--";
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($html));
        }
        self::smtpSend($config, $to, implode("\r\n", $headers), $body);
    }

    public static function test(string $to): array
    {
        try {
            self::send($to, '', 'MAXXfam SMTP test', self::layout(
                'SMTP connection verified',
                '<p>This message confirms that MAXXfam can authenticate to your SMTP server and deliver email.</p><p>Sent at ' . gmdate('Y-m-d H:i:s') . ' UTC.</p>'
            ));
            return ['success' => true, 'message' => 'Test email accepted by the SMTP server.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function layout(string $heading, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
    {
        $brand = e((string) (Settings::get('site_name', 'MAXXfam')));
        $cta = '';
        if ($ctaText && $ctaUrl) {
            $cta = '<p style="margin:26px 0"><a href="' . e($ctaUrl) . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:600;display:inline-block">' . e($ctaText) . '</a></p>';
        }
        return '<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a">'
            . '<div style="max-width:580px;margin:30px auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden">'
            . '<div style="background:#070d1c;padding:24px 28px"><span style="color:#fff;font-size:20px;font-weight:700">' . $brand . '</span>'
            . '<div style="color:#93c5fd;font-size:11px;margin-top:4px">Smart Payments. Real Verification. Complete Control.</div></div>'
            . '<div style="padding:28px"><h1 style="font-size:20px;margin:0 0 14px">' . e($heading) . '</h1>'
            . '<div style="font-size:14px;line-height:1.7;color:#334155">' . $bodyHtml . '</div>' . $cta . '</div>'
            . '<div style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b">'
            . 'This is an automated message from ' . $brand . '. Never share passwords, API keys, or OTP codes with anyone.</div>'
            . '</div></body></html>';
    }

    public static function queueWelcome(array $user): void
    {
        self::queue($user['email'], $user['name'], 'Welcome to MAXXfam', self::layout(
            'Your merchant workspace is ready',
            '<p>Hi ' . e($user['name']) . ',</p><p>Your MAXXfam account has been created. Connect your authorized UPI provider credentials under <strong>Integration</strong> before creating live payments.</p><p>MAXXfam never asks for your bank password or UPI PIN.</p>',
            'Open dashboard',
            url('/user/dashboard.php')
        ));
    }

    public static function queueLoginAlert(array $user): void
    {
        self::queue($user['email'], $user['name'], 'New sign-in to your MAXXfam account', self::layout(
            'New sign-in detected',
            '<p>A new sign-in occurred on your MAXXfam account.</p><p><strong>Time:</strong> ' . e(ist(gmdate('Y-m-d H:i:s'))) . ' IST<br><strong>IP:</strong> ' . e($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '</p><p>If this was not you, change your password immediately and contact support.</p>',
            'Review security',
            url('/user/security.php')
        ));
    }

    public static function queuePasswordReset(array $user, string $resetUrl): void
    {
        self::queue($user['email'], $user['name'], 'Reset your MAXXfam password', self::layout(
            'Password reset requested',
            '<p>Use the button below to choose a new password. This link expires in 60 minutes and can be used once.</p><p>If you did not request this, you can ignore the email.</p>',
            'Reset password',
            $resetUrl
        ));
    }

    public static function queuePaymentSuccess(array $order, string $utr, float $amount, string $invoicePath): void
    {
        $user = self::merchantUser((int) $order['merchant_id']);
        if (!$user) {
            return;
        }
        self::queue($user['email'], $user['name'], 'Payment verified — ' . money($amount), self::layout(
            'A payment was verified',
            '<p><strong>' . money($amount) . '</strong> was confirmed by the payment provider for order <strong>' . e($order['public_id']) . '</strong>.</p>'
            . '<p><strong>UTR:</strong> ' . e($utr) . '<br><strong>Customer:</strong> ' . e($order['customer_name'] ?: 'Not supplied') . '</p>'
            . '<p>Only provider-confirmed payments trigger this message.</p>',
            'View transaction',
            url('/user/transactions.php?q=' . urlencode($order['public_id']))
        ));
    }

    public static function queuePaymentFailed(array $order, string $reason): void
    {
        $user = self::merchantUser((int) $order['merchant_id']);
        if (!$user) {
            return;
        }
        self::queue($user['email'], $user['name'], 'Payment could not be verified — ' . $order['public_id'], self::layout(
            'Payment verification failed',
            '<p>Order <strong>' . e($order['public_id']) . '</strong> was not marked successful.</p><p>Reason: ' . e($reason) . '</p><p>No unverified payment is ever marked successful. Check the dashboard for details.</p>',
            'Review order',
            url('/user/transactions.php?q=' . urlencode($order['public_id']))
        ));
    }

    public static function queueApiKeyCreated(array $user, string $keyName, string $last4): void
    {
        self::queue($user['email'], $user['name'], 'New MAXXfam API key created', self::layout(
            'API key created',
            '<p>A new API key <strong>' . e($keyName) . '</strong> ending in <strong>' . e($last4) . '</strong> was created.</p><p>The full key was shown once and is not stored in plaintext. If this was not you, revoke it immediately.</p>',
            'Manage API keys',
            url('/user/api-keys.php')
        ));
    }

    public static function queueWebhookFailure(array $delivery, string $error, int $attempts): void
    {
        $user = self::merchantUser((int) $delivery['merchant_id']);
        if (!$user) {
            return;
        }
        self::queue($user['email'], $user['name'], 'Webhook delivery failed permanently', self::layout(
            'Webhook delivery failed',
            '<p>Event <strong>' . e($delivery['event']) . '</strong> could not be delivered after ' . $attempts . ' attempts.</p><p>Last error: ' . e($error) . '</p><p>Check your endpoint availability and signature verification.</p>',
            'View deliveries',
            url('/user/webhooks.php')
        ));
    }

    public static function queueAnnouncement(array $user, string $title, string $body): void
    {
        self::queue($user['email'], $user['name'], $title, self::layout($title, '<p>' . nl2br(e($body)) . '</p>', 'Open dashboard', url('/user/dashboard.php')));
    }

    public static function merchantUser(int $merchantId): ?array
    {
        return Database::fetch(
            'SELECT u.* FROM users u JOIN merchants m ON m.user_id = u.id WHERE m.id = ? LIMIT 1',
            [$merchantId]
        );
    }

    private static function smtpConfig(): array
    {
        $host = trim((string) (Settings::get('smtp_host', '') ?: Config::get('mail.host', '')));
        $port = (int) (Settings::get('smtp_port', '') ?: Config::get('mail.port', 587));
        $encryption = (string) (Settings::get('smtp_encryption', '') ?: Config::get('mail.encryption', 'tls'));
        $username = (string) (Settings::get('smtp_username', '') ?: Config::get('mail.username', ''));
        $password = (string) (Settings::get('smtp_password', '') ?: Config::get('mail.password', ''));
        $fromAddress = (string) (Settings::get('mail_from_address', '') ?: Config::get('mail.from_address', ''));
        $fromName = (string) (Settings::get('mail_from_name', '') ?: Config::get('mail.from_name', 'MAXXfam'));
        return [
            'host' => $host,
            'port' => $port > 0 ? $port : 587,
            'encryption' => strtolower($encryption),
            'username' => $username,
            'password' => $password,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'timeout' => (int) Config::get('mail.timeout', 12),
        ];
    }

    private static function smtpSend(array $config, string $to, string $headers, string $body): void
    {
        $host = $config['host'];
        $port = $config['port'];
        $scheme = $config['encryption'] === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($scheme . '://' . $host . ':' . $port, $errno, $errstr, $config['timeout'], STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException('SMTP connection failed: ' . ($errstr ?: 'unknown error'));
        }
        stream_set_timeout($socket, $config['timeout']);
        try {
            self::expect($socket, [220], 'server greeting');
            self::command($socket, 'EHLO maxxfam.local', [250]);
            if ($config['encryption'] === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                self::command($socket, 'EHLO maxxfam.local', [250]);
            }
            if ($config['username'] !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($config['username']), [334]);
                self::command($socket, base64_encode($config['password']), [235]);
            }
            self::command($socket, 'MAIL FROM:<' . $config['from_address'] . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);
            $data = $headers . "\r\n\r\n" . $body;
            $data = preg_replace('/^\./m', '..', $data) ?? $data;
            fwrite($socket, $data . "\r\n.\r\n");
            self::expect($socket, [250], 'message body');
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private static function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expected, $command === 'AUTH LOGIN' ? 'AUTH' : 'command');
    }

    private static function expect($socket, array $expected, string $stage): string
    {
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP server closed the connection during ' . $stage . '.');
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $safe = preg_replace('/AUTH\s+\S+/i', 'AUTH [REDACTED]', $response) ?? $response;
            throw new RuntimeException('SMTP ' . $stage . ' rejected (' . $code . '): ' . trim(mb_substr($safe, 0, 180)));
        }
        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
