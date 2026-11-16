<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;
use App\Core\ImapClient;
use App\Core\Logger;
use App\Core\Settings;
use App\Providers\VerificationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class EmailVerificationService
{
    public const UPI_SUFFIXES = ['@fam', '@yesfam'];
    public const DEFAULT_SENDERS = 'famapp.in,fampay.in';

    public static function validUpiId(string $upi): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9._-]{1,63}@(fam|yesfam)$/i', trim($upi)) === 1;
    }

    public static function normalizeUpiId(string $upi): string
    {
        return strtolower(trim($upi));
    }

    /**
     * Create or update the merchant's Gmail IMAP account.
     * @return array<string,mixed>
     */
    public static function saveAccount(int $merchantId, array $input): array
    {
        $existing = self::accountForMerchant($merchantId);
        $gmail = strtolower(trim((string) ($input['gmail_address'] ?? '')));
        if (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid Gmail address.');
        }
        $upi = self::normalizeUpiId((string) ($input['upi_id'] ?? ''));
        if (!self::validUpiId($upi)) {
            throw new InvalidArgumentException('The FamPay UPI ID must end with @fam or @yesfam (for example name@fam).');
        }
        $appPassword = preg_replace('/\s+/', '', (string) ($input['app_password'] ?? '')) ?? '';
        if ($appPassword === '' && $existing && !empty($existing['app_password'])) {
            $appPassword = (string) $existing['app_password'];
        }

        $authMethod = strtolower(trim((string) ($input['auth_method'] ?? ($existing['auth_method'] ?? 'app_password'))));
        if (!in_array($authMethod, ['app_password', 'oauth2'], true)) {
            $authMethod = 'app_password';
        }
        // Entering a fresh App Password switches an OAuth account back, unless
        // the caller explicitly asked to keep OAuth.
        if ($appPassword !== '' && !array_key_exists('auth_method', $input)) {
            $authMethod = 'app_password';
        }
        if ($authMethod === 'app_password' && preg_match('/^[A-Za-z0-9]{16}$/', $appPassword) !== 1) {
            throw new InvalidArgumentException('Enter the 16-character Google App Password (spaces are ignored).');
        }
        if ($authMethod === 'oauth2' && (!GmailOAuth::configured()) && empty($existing['oauth_refresh_token'])) {
            throw new InvalidArgumentException('OAuth 2.0 is not configured on this server yet. Use an App Password, or ask the administrator to set GMAIL_OAUTH_CLIENT_ID and GMAIL_OAUTH_CLIENT_SECRET.');
        }

        $host = trim((string) ($input['imap_host'] ?? 'imap.gmail.com')) ?: 'imap.gmail.com';
        if (preg_match('/^[A-Za-z0-9.-]{3,190}$/', $host) !== 1) {
            throw new InvalidArgumentException('Enter a valid IMAP host.');
        }
        $port = (int) ($input['imap_port'] ?? 993);
        if ($port < 1 || $port > 65535) {
            $port = 993;
        }
        $encryption = strtolower((string) ($input['imap_encryption'] ?? 'ssl'));
        if (!in_array($encryption, ['ssl', 'tls', 'starttls', 'none'], true)) {
            $encryption = 'ssl';
        }
        if ($encryption === 'none' && !Env::bool('IMAP_ALLOW_PLAINTEXT', false)) {
            throw new InvalidArgumentException('Unencrypted IMAP is disabled. Use ssl (port 993) or starttls (port 143).');
        }
        $folder = trim((string) ($input['folder'] ?? 'INBOX')) ?: 'INBOX';
        $senders = self::normalizeSenders((string) ($input['sender_whitelist'] ?? Settings::get('email_verification_default_senders', self::DEFAULT_SENDERS)));
        $enabled = array_key_exists('is_enabled', $input) ? (!empty($input['is_enabled']) ? 1 : 0) : 1;
        $searchDays = max(0, min(365, (int) ($input['search_days'] ?? ($existing['search_days'] ?? 0))));
        $markRead = array_key_exists('mark_read', $input) ? (!empty($input['mark_read']) ? 1 : 0) : (int) ($existing['mark_read'] ?? 1);
        $encrypted = $appPassword !== ''
            ? Crypto::encrypt($appPassword)
            : (string) ($existing['app_password_encrypted'] ?? Crypto::encrypt(''));
        $last4 = $appPassword !== '' ? substr($appPassword, -4) : (string) ($existing['app_password_last4'] ?? '');

        if ($existing) {
            Database::execute(
                'UPDATE email_verification_accounts
                 SET gmail_address = ?, app_password_encrypted = ?, app_password_last4 = ?, upi_id = ?,
                     imap_host = ?, imap_port = ?, imap_encryption = ?, folder = ?, sender_whitelist = ?,
                     auth_method = ?, search_days = ?, mark_read = ?,
                     is_enabled = ?, connection_status = "not_configured", last_test_message = NULL,
                     last_error = NULL, updated_at = UTC_TIMESTAMP()
                 WHERE merchant_id = ?',
                [
                    $gmail, $encrypted, $last4, $upi, $host, $port, $encryption,
                    $folder, $senders, $authMethod, $searchDays, $markRead, $enabled, $merchantId,
                ]
            );
        } else {
            Database::insert(
                'INSERT INTO email_verification_accounts
                 (merchant_id, gmail_address, app_password_encrypted, app_password_last4, upi_id,
                  imap_host, imap_port, imap_encryption, folder, sender_whitelist, auth_method,
                  search_days, mark_read, is_enabled, connection_status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "not_configured", UTC_TIMESTAMP())',
                [
                    $merchantId, $gmail, $encrypted, $last4, $upi, $host, $port,
                    $encryption, $folder, $senders, $authMethod, $searchDays, $markRead, $enabled,
                ]
            );
        }

        return self::accountForMerchant($merchantId) ?? [];
    }

    public static function accountForMerchant(int $merchantId): ?array
    {
        $row = Database::fetch('SELECT * FROM email_verification_accounts WHERE merchant_id = ? LIMIT 1', [$merchantId]);
        return $row ? self::hydrate($row) : null;
    }

    public static function findById(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM email_verification_accounts WHERE id = ? LIMIT 1', [$id]);
        return $row ? self::hydrate($row) : null;
    }

    public static function allAccounts(bool $enabledOnly = false): array
    {
        $sql = 'SELECT a.*, m.business_name, m.merchant_code, u.email AS owner_email
                FROM email_verification_accounts a
                JOIN merchants m ON m.id = a.merchant_id
                LEFT JOIN users u ON u.id = m.user_id';
        if ($enabledOnly) {
            $sql .= ' WHERE a.is_enabled = 1';
        }
        $sql .= ' ORDER BY a.created_at DESC';
        return array_map([self::class, 'hydrate'], Database::fetchAll($sql));
    }

    public static function deleteAccount(int $merchantId): void
    {
        Database::execute('DELETE FROM email_verification_accounts WHERE merchant_id = ?', [$merchantId]);
    }

    /** Store a Gmail OAuth refresh token for an account and switch it to OAuth mode. */
    public static function setOauthTokens(int $accountId, string $refreshToken, string $tokenEmail = ''): void
    {
        GmailOAuth::storeRefreshToken($accountId, $refreshToken, $tokenEmail);
    }

    public static function setEnabled(int $accountId, bool $enabled): void
    {
        Database::execute(
            'UPDATE email_verification_accounts SET is_enabled = ?, connection_status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$enabled ? 1 : 0, $enabled ? 'not_configured' : 'disabled', $accountId]
        );
    }

    /**
     * Authenticate and read the mailbox without changing any payment.
     * @return array{connected:bool,message:string,unseen:int,steps:array<int,array<string,mixed>>}
     */
    public static function test(array $account): array
    {
        $id = (int) ($account['id'] ?? 0);
        if ($id > 0) {
            Database::execute('UPDATE email_verification_accounts SET connection_status = "testing", updated_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
        }
        $diagnosis = self::diagnose($account, 0);
        $message = $diagnosis['message'];
        if ($id > 0) {
            Database::execute(
                'UPDATE email_verification_accounts SET connection_status = ?, last_test_at = UTC_TIMESTAMP(),
                 last_test_message = ?, last_error = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [
                    $diagnosis['ok'] ? 'connected' : 'failed',
                    mb_substr($message, 0, 500),
                    $diagnosis['ok'] ? null : mb_substr($message, 0, 500),
                    $id,
                ]
            );
        }
        Audit::security($diagnosis['ok'] ? 'email_imap_test_success' : 'email_imap_test_failed', $diagnosis['ok'] ? 'info' : 'warning', [
            'account_id' => $id,
            'merchant_id' => (int) ($account['merchant_id'] ?? 0),
            'unseen' => $diagnosis['unseen'],
            'message' => mb_substr($message, 0, 200),
        ], null, null);

        return [
            'connected' => $diagnosis['ok'],
            'message' => $message,
            'unseen' => $diagnosis['unseen'],
            'steps' => $diagnosis['steps'],
        ];
    }

    /**
     * Step-by-step mailbox diagnosis: DNS, TLS, greeting, auth, folder,
     * mailbox list and a live alert parse. Never mutates payments.
     *
     * @return array{ok:bool,message:string,unseen:int,steps:array<int,array<string,mixed>>,mailboxes:array<int,string>,sample:?array<string,mixed>,duration_ms:int}
     */
    public static function diagnose(array $account, int $sampleLimit = 1): array
    {
        $steps = [];
        $started = microtime(true);
        $record = static function (string $name, bool $ok, string $detail, ?string $hint = null) use (&$steps): void {
            $steps[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail, 'hint' => $hint, 'ms' => 0];
        };

        $secret = (string) ($account['app_password'] ?? '');
        $host = (string) ($account['imap_host'] ?: 'imap.gmail.com');
        $port = (int) ($account['imap_port'] ?: 993);
        $encryption = (string) ($account['imap_encryption'] ?: 'ssl');
        $folder = (string) ($account['folder'] ?: 'INBOX');
        $email = (string) ($account['gmail_address'] ?? '');
        $method = (string) ($account['auth_method'] ?? 'app_password');
        $mailboxes = [];
        $sample = null;
        $unseen = 0;
        $client = self::client($account);

        $finish = static function (array &$steps, float $from): void {
            if ($steps) {
                $steps[count($steps) - 1]['ms'] = (int) round((microtime(true) - $from) * 1000);
            }
        };

        // 1. Credentials
        $t = microtime(true);
        if ($method === 'oauth2') {
            $hasCredential = !empty($account['oauth_refresh_token']);
            $record('Credentials', $hasCredential, $hasCredential
                ? 'OAuth 2.0 refresh token stored for ' . $email . '.'
                : 'No OAuth refresh token. Reconnect the Gmail account.', 'Reconnect Gmail with OAuth 2.0 to store a refresh token.');
        } else {
            $hasCredential = $secret !== '';
            $record('Credentials', $hasCredential, $hasCredential
                ? 'Google App Password available (' . strlen($secret) . ' characters, ending ' . substr($secret, -4) . ').'
                : 'The stored Google App Password could not be decrypted.', 'Re-save the App Password. If APP_KEY changed, credentials must be entered again.');
        }
        $finish($steps, $t);
        if (!$hasCredential) {
            $client->close();
            return self::diagnoseResult($steps, $unseen, $mailboxes, $sample, $started);
        }

        // 2. DNS
        $t = microtime(true);
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        $dnsOk = $ip !== $host || filter_var($host, FILTER_VALIDATE_IP);
        $record('DNS', (bool) $dnsOk, $dnsOk ? $host . ' resolves to ' . $ip . '.' : 'Unable to resolve ' . $host . '.',
            $dnsOk ? null : 'Check the host name and the server DNS resolver.');
        $finish($steps, $t);
        if (!$dnsOk) {
            return self::diagnoseResult($steps, $unseen, $mailboxes, $sample, $started);
        }

        // 3. TLS / connection
        $t = microtime(true);
        try {
            $client->connect();
            $record('TLS session', true, 'Connected to ' . $host . ':' . $port . ' (' . strtoupper($encryption) . ')' .
                ($client->isTls() ? ', TLS active.' : ', TLS not active.'));
        } catch (Throwable $e) {
            $record('TLS session', false, self::safeError($e->getMessage(), $secret), $client->lastHint());
            $finish($steps, $t);
            $client->close();
            return self::diagnoseResult($steps, $unseen, $mailboxes, $sample, $started);
        }
        $finish($steps, $t);

        // 4. Capabilities
        $t = microtime(true);
        $caps = $client->capabilities();
        $record('Capabilities', true, 'Server advertises: ' . (implode(' ', array_slice($caps, 0, 12)) ?: 'none reported') . '.');
        $finish($steps, $t);

        // 5. Authentication
        $t = microtime(true);
        try {
            self::authenticate($client, $account);
            $record('Authentication', true, 'Signed in as ' . $email . ' using ' . ($method === 'oauth2' ? 'XOAUTH2' : 'LOGIN (App Password)') . '.');
        } catch (Throwable $e) {
            $record('Authentication', false, self::safeError($e->getMessage(), $secret), $client->lastHint());
            $finish($steps, $t);
            $client->close();
            return self::diagnoseResult($steps, $unseen, $mailboxes, $sample, $started);
        }
        $finish($steps, $t);

        // 6. Folder
        $t = microtime(true);
        try {
            $client->select($folder);
            $selected = $client->selected();
            $record('Folder', true, $folder . ': ' . (int) ($selected['exists'] ?? 0) . ' message(s), UIDVALIDITY ' .
                (int) ($selected['uidvalidity'] ?? 0) . ', ' . (int) ($selected['unseen'] ?? 0) . ' unseen.');
        } catch (Throwable $e) {
            $record('Folder', false, self::safeError($e->getMessage(), $secret), $client->lastHint()
                ?? 'Gmail labels must use the full name, for example [Gmail]/All Mail.');
            $finish($steps, $t);
            $client->close();
            return self::diagnoseResult($steps, $unseen, $mailboxes, $sample, $started);
        }
        $finish($steps, $t);

        // 7. Mailbox list
        $t = microtime(true);
        try {
            foreach ($client->listMailboxes() as $mailbox) {
                $mailboxes[] = $mailbox['name'];
            }
            $record('Mailbox list', true, count($mailboxes) . ' folder(s): ' . implode(', ', array_slice($mailboxes, 0, 8)) .
                (count($mailboxes) > 8 ? '…' : '') . '.');
        } catch (Throwable $e) {
            $record('Mailbox list', false, self::safeError($e->getMessage(), $secret), null);
        }
        $finish($steps, $t);

        // 8. Search
        $t = microtime(true);
        $uids = [];
        try {
            $uids = $client->uidSearch(self::searchCriteria($account));
            $unseen = count($uids);
            $record('Search', true, $unseen . ' unseen message(s) matched ' . self::searchCriteria($account) . '.');
        } catch (Throwable $e) {
            $record('Search', false, self::safeError($e->getMessage(), $secret), $client->lastHint());
        }
        $finish($steps, $t);

        // 9. Live parse of the newest alert
        if ($sampleLimit > 0 && $uids) {
            $t = microtime(true);
            try {
                $uid = (string) end($uids);
                $raw = $client->fetchRaw($uid);
                $parsed = EmailParser::parse($raw);
                $analysis = self::analyse($parsed, $account);
                $sample = [
                    'uid' => (int) $uid,
                    'from' => $parsed['from_address'],
                    'subject' => mb_substr((string) $parsed['subject'], 0, 120),
                    'date' => $parsed['date'],
                    'amount' => $analysis['amount'],
                    'utr' => $analysis['utr'],
                    'upi_id' => $analysis['upi_id'],
                    'sender_trusted' => $analysis['sender_trusted'],
                    'keywords' => $analysis['keywords'],
                ];
                $ok = $analysis['amount'] !== null && $analysis['utr'] !== null && $analysis['keywords'];
                $record('Alert parse', $ok, $ok
                    ? 'UID ' . $uid . ': amount ₹' . number_format((float) $analysis['amount'], 2) . ', UTR ' . $analysis['utr'] . ', UPI ' . ($analysis['upi_id'] ?? '—') . '.'
                    : 'UID ' . $uid . ': could not extract a confident amount + UTR pair.',
                    $ok ? null : 'Open the message in Gmail and add its sender domain to the trusted list, or check the amount/UTR wording.');
            } catch (Throwable $e) {
                $record('Alert parse', false, self::safeError($e->getMessage(), $secret), null);
            }
            $finish($steps, $t);
        }

        $client->logout();
        return self::diagnoseResult($steps, $unseen, $mailboxes, $sample, $started);
    }

    /**
     * @param array<int,array<string,mixed>> $steps
     * @param string[] $mailboxes
     * @return array{ok:bool,message:string,unseen:int,steps:array<int,array<string,mixed>>,mailboxes:array<int,string>,sample:?array<string,mixed>,duration_ms:int}
     */
    private static function diagnoseResult(array $steps, int $unseen, array $mailboxes, ?array $sample, float $started): array
    {
        $failed = array_values(array_filter($steps, static fn (array $step): bool => !$step['ok']));
        $ok = $failed === [];
        if ($ok) {
            $message = 'IMAP is healthy. ' . $unseen . ' unseen message(s) ready to scan.';
        } else {
            $message = 'IMAP check failed at "' . $failed[0]['name'] . '": ' . $failed[0]['detail'];
            if (!empty($failed[0]['hint'])) {
                $message .= ' Fix: ' . $failed[0]['hint'];
            }
        }
        return [
            'ok' => $ok,
            'message' => mb_substr($message, 0, 500),
            'unseen' => $unseen,
            'steps' => $steps,
            'mailboxes' => $mailboxes,
            'sample' => $sample,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /** @param string|string[] $criteria */
    private static function searchCriteria(array $account): string
    {
        $criteria = ['UNSEEN'];
        $days = (int) ($account['search_days'] ?? 0);
        if ($days > 0) {
            $criteria[] = 'SINCE ' . gmdate('d-M-Y', time() - $days * 86400);
        }
        return implode(' ', $criteria);
    }

    private static function authenticate(ImapClient $client, array $account): void
    {
        $email = (string) ($account['gmail_address'] ?? '');
        if ((string) ($account['auth_method'] ?? 'app_password') === 'oauth2') {
            $token = GmailOAuth::accessToken($account);
            $client->authenticateXoauth2($email, $token);
            return;
        }
        $password = (string) ($account['app_password'] ?? '');
        if ($password === '') {
            throw new RuntimeException('The stored Google App Password could not be decrypted.');
        }
        $client->login($email, $password);
    }

    /** @return string[] */
    public static function mailboxes(array $account): array
    {
        $client = self::client($account);
        try {
            $client->connect();
            self::authenticate($client, $account);
            $names = array_map(static fn (array $mailbox): string => $mailbox['name'], $client->listMailboxes());
            $client->logout();
            return $names;
        } finally {
            $client->close();
        }
    }

    /**
     * Read unseen transaction alerts and reconcile them with pending orders.
     * @return array<string,mixed>
     */
    public static function scanAccount(array $account, ?int $limit = null): array
    {
        $summary = [
            'account_id' => (int) ($account['id'] ?? 0),
            'checked' => 0,
            'verified' => 0,
            'ignored' => 0,
            'unparsed' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'duplicate' => 0,
            'failed' => 0,
            'messages' => [],
        ];
        if (!Settings::bool('email_verification_enabled', true)) {
            $summary['error'] = 'Email verification is disabled by the platform administrator.';
            return $summary;
        }
        if ((int) ($account['is_enabled'] ?? 0) !== 1) {
            $summary['error'] = 'This email verification account is disabled.';
            return $summary;
        }
        if ((string) ($account['auth_method'] ?? 'app_password') === 'app_password' && empty($account['app_password'])) {
            $summary['error'] = 'The stored Google App Password could not be decrypted.';
            return $summary;
        }
        if ((string) ($account['auth_method'] ?? 'app_password') === 'oauth2' && empty($account['oauth_refresh_token'])) {
            $summary['error'] = 'This account uses OAuth 2.0 but no refresh token is stored. Reconnect the Gmail mailbox.';
            return $summary;
        }
        $limit = $limit ?? max(1, min(100, Settings::int('email_verification_max_per_run', 20)));
        $client = self::client($account);
        $checkedAt = gmdate('Y-m-d H:i:s');
        try {
            $client->connect();
            self::authenticate($client, $account);
            $client->select((string) ($account['folder'] ?: 'INBOX'));

            // UIDVALIDITY guards against recycled UIDs after a mailbox rebuild.
            $uidValidity = (int) $client->uidValidity();
            $lastUid = (int) ($account['last_uid'] ?? 0);
            if ($uidValidity > 0 && (int) ($account['uidvalidity'] ?? 0) > 0 && $uidValidity !== (int) $account['uidvalidity']) {
                $lastUid = 0;
            }

            $uids = $client->uidSearch(self::searchCriteria($account));
            if ($lastUid > 0) {
                $uids = array_values(array_filter($uids, static fn (string $uid): bool => (int) $uid > $lastUid));
            }
            $markRead = (int) ($account['mark_read'] ?? 1) === 1;
            $maxProcessed = $lastUid;
            if (!$uids) {
                Database::execute(
                    'UPDATE email_verification_accounts SET last_checked_at = ?, connection_status = "connected", last_error = NULL,
                     uidvalidity = COALESCE(NULLIF(?, 0), uidvalidity) WHERE id = ?',
                    [$checkedAt, $uidValidity, (int) $account['id']]
                );
                $client->logout();
                return $summary;
            }
            foreach (array_slice($uids, 0, $limit) as $uid) {
                $uid = (string) $uid;
                $existing = Database::scalar(
                    'SELECT COUNT(*) FROM email_verification_messages WHERE account_id = ? AND message_uid = ?',
                    [(int) $account['id'], (int) $uid]
                );
                if ((int) $existing > 0) {
                    try {
                        if ($markRead) {
                            $client->markSeen($uid);
                        }
                    } catch (Throwable) {
                    }
                    continue;
                }
                try {
                    $raw = $client->fetchRaw($uid);
                } catch (Throwable $e) {
                    $summary['failed']++;
                    Logger::warning('Email verification fetch failed', [
                        'account_id' => (int) $account['id'],
                        'uid' => $uid,
                        'error' => self::safeError($e->getMessage(), (string) $account['app_password']),
                    ]);
                    continue;
                }
                $parsed = EmailParser::parse($raw);
                $analysis = self::analyse($parsed, $account);
                $messageId = 'email_' . bin2hex(random_bytes(8));
                try {
                    $messageId = (string) Database::insert(
                        'INSERT INTO email_verification_messages
                         (account_id, merchant_id, message_uid, message_id_hash, from_address, subject, received_at,
                          body_hash, parsed_amount, parsed_utr, parsed_upi_id, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "received", UTC_TIMESTAMP())',
                        [
                            (int) $account['id'],
                            (int) $account['merchant_id'],
                            (int) $uid,
                            hash('sha256', $parsed['message_id'] !== '' ? $parsed['message_id'] : $parsed['text']),
                            mb_substr($parsed['from_address'], 0, 190),
                            mb_substr($parsed['subject'], 0, 255),
                            $parsed['date'],
                            hash('sha256', $parsed['text']),
                            $analysis['amount'],
                            $analysis['utr'],
                            $analysis['upi_id'],
                        ]
                    );
                } catch (Throwable) {
                    // A concurrent scan already recorded this UID.
                    continue;
                }

                $status = 'received';
                $note = '';
                $matchedOrderId = null;
                if ($analysis['require_sender'] && !$analysis['sender_trusted']) {
                    $status = 'ignored';
                    $note = 'Sender is not in the trusted FamPay sender list.';
                } elseif ($analysis['require_upi'] && !$analysis['upi_found']) {
                    $status = 'ignored';
                    $note = 'The configured FamPay UPI ID was not found in the message body.';
                } elseif (!$analysis['keywords']) {
                    $status = 'ignored';
                    $note = 'The message does not look like a payment-credit alert.';
                } elseif ($analysis['amount'] === null || $analysis['utr'] === null) {
                    $status = 'unparsed';
                    $note = 'Could not extract both the amount and a 12-digit UTR.';
                } else {
                    $orders = self::matchingOrders($account, (float) $analysis['amount']);
                    if (count($orders) === 0) {
                        $status = 'unmatched';
                        $note = 'No pending order matches this amount within the configured lookback window.';
                    } elseif (count($orders) > 1) {
                        $status = 'ambiguous';
                        $note = 'Multiple pending orders match this amount; manual review required.';
                    } else {
                        $order = $orders[0];
                        $matchedOrderId = (int) $order['id'];
                        try {
                            VerificationService::processEmailVerified($order, [
                                'status' => 'success',
                                'amount' => (float) $analysis['amount'],
                                'utr' => (string) $analysis['utr'],
                                'paid_at' => $parsed['date'] ?: gmdate('Y-m-d H:i:s'),
                                'sender_name' => $analysis['sender_name'],
                                'transaction_id' => null,
                                'raw' => [
                                    'source' => 'gmail_imap',
                                    'message_id' => $parsed['message_id'],
                                    'from' => $parsed['from_address'],
                                    'subject' => $parsed['subject'],
                                    'upi_id' => $analysis['upi_id'],
                                ],
                            ], 'email_imap');
                            $status = 'verified';
                            $note = 'Verified against a trusted FamPay email alert. Order ' . $order['public_id'] . '.';
                            $summary['verified']++;
                        } catch (VerificationException $e) {
                            if (in_array($e->reason, ['duplicate_utr', 'duplicate_transaction'], true)) {
                                $status = 'duplicate';
                                $note = 'Duplicate payment reference blocked: ' . $e->getMessage();
                                $summary['duplicate']++;
                            } else {
                                $status = 'failed';
                                $note = 'Verification rejected: ' . $e->getMessage();
                                $summary['failed']++;
                            }
                        } catch (Throwable $e) {
                            $status = 'failed';
                            $note = 'Verification error: ' . self::safeError($e->getMessage(), (string) $account['app_password']);
                            $summary['failed']++;
                        }
                    }
                }

                Database::execute(
                    'UPDATE email_verification_messages SET status = ?, note = ?, matched_order_id = ?, processed_at = UTC_TIMESTAMP() WHERE id = ?',
                    [$status, mb_substr($note, 0, 500), $matchedOrderId, (int) $messageId]
                );
                Database::execute(
                    'UPDATE email_verification_accounts SET total_messages = total_messages + 1,
                     total_verified = total_verified + ?, last_checked_at = ?, connection_status = "connected", last_error = NULL
                     WHERE id = ?',
                    [$status === 'verified' ? 1 : 0, $checkedAt, (int) $account['id']]
                );
                try {
                    if ($markRead) {
                        $client->markSeen($uid);
                    }
                } catch (Throwable) {
                }
                $summary['checked']++;
                $maxProcessed = max($maxProcessed, (int) $uid);
                if (in_array($status, ['ignored', 'unparsed', 'unmatched', 'ambiguous'], true)) {
                    $summary[$status]++;
                }
                $summary['messages'][] = [
                    'uid' => (int) $uid,
                    'subject' => $parsed['subject'],
                    'status' => $status,
                    'note' => $note,
                ];
            }
            Database::execute(
                'UPDATE email_verification_accounts SET uidvalidity = COALESCE(NULLIF(?, 0), uidvalidity), last_uid = GREATEST(COALESCE(last_uid, 0), ?) WHERE id = ?',
                [$uidValidity, $maxProcessed, (int) $account['id']]
            );
            $client->logout();
            return $summary;
        } catch (Throwable $e) {
            $message = self::safeError($e->getMessage(), (string) ($account['app_password'] ?? ''));
            Database::execute(
                'UPDATE email_verification_accounts SET connection_status = "failed", last_error = ?, last_checked_at = ? WHERE id = ?',
                [mb_substr($message, 0, 500), $checkedAt, (int) $account['id']]
            );
            Logger::warning('Email verification scan failed', [
                'account_id' => (int) ($account['id'] ?? 0),
                'error' => $message,
            ]);
            $summary['error'] = $message;
            return $summary;
        }
    }

    /** @return array<string,mixed> */
    public static function scanAll(?int $limit = null): array
    {
        if (!Settings::bool('email_verification_enabled', true)) {
            return ['enabled' => false, 'accounts' => 0, 'results' => []];
        }
        $accounts = self::allAccounts(true);
        $results = [];
        foreach ($accounts as $account) {
            $results[] = self::scanAccount($account, $limit);
        }
        return ['enabled' => true, 'accounts' => count($accounts), 'results' => $results];
    }

    public static function recentMessages(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT em.*, a.gmail_address, a.upi_id, m.business_name
             FROM email_verification_messages em
             JOIN email_verification_accounts a ON a.id = em.account_id
             JOIN merchants m ON m.id = em.merchant_id
             ORDER BY em.id DESC LIMIT {$limit}"
        );
    }

    /** @return array<string,int> */
    public static function stats(): array
    {
        $rows = Database::fetchAll(
            'SELECT status, COUNT(*) AS total FROM email_verification_messages
             WHERE created_at >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR) GROUP BY status'
        );
        $stats = ['received' => 0, 'ignored' => 0, 'unparsed' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'verified' => 0, 'duplicate' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $stats[(string) $row['status']] = (int) $row['total'];
        }
        return $stats;
    }

    public static function parseAlert(string $text, string $upiId = ''): array
    {
        $upiId = self::normalizeUpiId($upiId);
        $upiFound = $upiId !== '' && stripos($text, $upiId) !== false;
        return [
            'amount' => self::extractAmount($text),
            'utr' => self::extractUtr($text),
            'upi_id' => self::extractUpiId($text),
            'upi_found' => $upiFound,
            'keywords' => preg_match('/\b(credited|received|success|successful|paid|payment received)\b/i', $text) === 1,
        ];
    }

    public static function extractAmount(string $text): ?float
    {
        $patterns = [
            '/(?:₹|Rs\.?|INR)\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)/i',
            '/([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(?:INR|Rs\.?|₹)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $amount = (float) str_replace(',', '', $match[1]);
                if ($amount > 0) {
                    return round($amount, 2);
                }
            }
        }
        return null;
    }

    public static function extractUtr(string $text): ?string
    {
        $context = '/(?:UTR|UPI\s*(?:transaction|reference)?\s*(?:ID|No\.?|number)?|Reference\s*(?:No\.?|ID)?|RRN|Transaction\s*(?:ID|No\.?))\s*[:\-]?\s*(\d{12})\b/i';
        if (preg_match($context, $text, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(\d{12})\b/', $text, $match) === 1) {
            return $match[1];
        }
        return null;
    }

    public static function extractUpiId(string $text): ?string
    {
        if (preg_match('/\b([a-z0-9][a-z0-9._-]{1,63}@(?:fam|yesfam))\b/i', $text, $match) === 1) {
            return strtolower($match[1]);
        }
        return null;
    }

    public static function senderTrusted(string $fromAddress, array|string $senders): bool
    {
        $fromAddress = strtolower(trim($fromAddress));
        if ($fromAddress === '' || !str_contains($fromAddress, '@')) {
            return false;
        }
        $list = is_array($senders) ? $senders : self::senderList($senders);
        $domain = substr($fromAddress, strrpos($fromAddress, '@') + 1);
        foreach ($list as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === '') {
                continue;
            }
            if (str_contains($allowed, '@')) {
                if (hash_equals($allowed, $fromAddress)) {
                    return true;
                }
                continue;
            }
            if ($domain === $allowed || str_ends_with($domain, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    public static function senderList(string $value): array
    {
        $parts = preg_split('/[\s,;]+/', strtolower($value)) ?: [];
        return array_values(array_filter(array_map(static fn (string $v): string => ltrim(trim($v), '@'), $parts)));
    }

    public static function normalizeSenders(string $value): string
    {
        $list = [];
        foreach (self::senderList($value) as $sender) {
            if (filter_var($sender, FILTER_VALIDATE_EMAIL) || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $sender) === 1) {
                $list[$sender] = true;
            }
        }
        return implode(',', array_keys($list)) ?: self::DEFAULT_SENDERS;
    }

    /** @return array<string,mixed> */
    private static function analyse(array $parsed, array $account): array
    {
        $text = (string) ($parsed['text'] ?? '');
        $parsedAlert = self::parseAlert($text, (string) ($account['upi_id'] ?? ''));
        $configuredUpi = self::normalizeUpiId((string) ($account['upi_id'] ?? ''));
        $upiFound = $configuredUpi !== '' && stripos($text, $configuredUpi) !== false;
        $senders = $account['sender_list'] ?? self::senderList((string) ($account['sender_whitelist'] ?? self::DEFAULT_SENDERS));
        return [
            'amount' => $parsedAlert['amount'],
            'utr' => $parsedAlert['utr'],
            'upi_id' => $parsedAlert['upi_id'] ?: ($upiFound ? $configuredUpi : null),
            'upi_found' => $upiFound,
            'keywords' => $parsedAlert['keywords'],
            'sender_trusted' => self::senderTrusted((string) ($parsed['from_address'] ?? ''), $senders),
            'sender_name' => self::senderName((string) ($parsed['from'] ?? '')),
            'require_sender' => Settings::bool('email_verification_require_sender_match', true),
            'require_upi' => Settings::bool('email_verification_require_upi_match', true),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function matchingOrders(array $account, float $amount): array
    {
        $lookback = max(1, min(168, Settings::int('email_verification_lookback_hours', 24)));
        $upi = self::normalizeUpiId((string) ($account['upi_id'] ?? ''));
        return Database::fetchAll(
            "SELECT * FROM payment_orders
             WHERE merchant_id = ? AND status = 'pending'
               AND ABS(COALESCE(payable_amount, amount) - ?) < 0.01
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
               AND created_at >= (UTC_TIMESTAMP() - INTERVAL {$lookback} HOUR)
               AND (upi_id IS NULL OR upi_id = '' OR LOWER(upi_id) = ? OR ? = '')
             ORDER BY created_at DESC LIMIT 5",
            [(int) $account['merchant_id'], number_format($amount, 2, '.', ''), $upi, $upi]
        );
    }

    private static function client(array $account): ImapClient
    {
        return new ImapClient(
            (string) ($account['imap_host'] ?: 'imap.gmail.com'),
            (int) ($account['imap_port'] ?: 993),
            (string) ($account['imap_encryption'] ?: 'ssl'),
            20
        );
    }

    private static function senderName(string $from): ?string
    {
        $name = trim(preg_replace('/<[^>]+>/', '', $from) ?? '');
        $name = trim($name, " \t\"'");
        return $name !== '' ? mb_substr($name, 0, 190) : null;
    }

    private static function hydrate(array $row): array
    {
        $row['auth_method'] = (string) ($row['auth_method'] ?? 'app_password');
        $row['sender_list'] = self::senderList((string) ($row['sender_whitelist'] ?? self::DEFAULT_SENDERS));
        $row['app_password'] = null;
        try {
            $row['app_password'] = Crypto::decrypt((string) $row['app_password_encrypted']);
        } catch (Throwable $e) {
            if ($row['auth_method'] === 'app_password') {
                Logger::error('Email verification credential decryption failed', [
                    'account_id' => $row['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $row['connection_status'] = 'failed';
                $row['last_error'] = 'The stored Google App Password could not be decrypted. Re-save the credential.';
            }
        }

        $row['oauth_refresh_token'] = null;
        $row['oauth_access_token'] = null;
        if (!empty($row['oauth_refresh_token_encrypted'])) {
            try {
                $row['oauth_refresh_token'] = Crypto::decrypt((string) $row['oauth_refresh_token_encrypted']);
            } catch (Throwable $e) {
                Logger::error('Gmail OAuth refresh token decryption failed', [
                    'account_id' => $row['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $row['last_error'] = 'The stored Gmail OAuth token could not be decrypted. Reconnect the mailbox.';
            }
        }
        if (!empty($row['oauth_access_token_encrypted'])) {
            try {
                $row['oauth_access_token'] = Crypto::decrypt((string) $row['oauth_access_token_encrypted']);
            } catch (Throwable) {
                $row['oauth_access_token'] = null;
            }
        }
        return $row;
    }

    private static function safeError(string $message, string $secret): string
    {
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;
        return mb_substr(trim($message), 0, 500);
    }
}
