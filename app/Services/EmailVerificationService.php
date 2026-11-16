<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\ImapClient;
use App\Core\Logger;
use App\Core\Settings;
use App\Providers\VerificationException;
use InvalidArgumentException;
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
        if (preg_match('/^[A-Za-z0-9]{16}$/', $appPassword) !== 1) {
            throw new InvalidArgumentException('Enter the 16-character Google App Password (spaces are ignored).');
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
        if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            $encryption = 'ssl';
        }
        $folder = trim((string) ($input['folder'] ?? 'INBOX')) ?: 'INBOX';
        $senders = self::normalizeSenders((string) ($input['sender_whitelist'] ?? Settings::get('email_verification_default_senders', self::DEFAULT_SENDERS)));
        $enabled = array_key_exists('is_enabled', $input) ? (!empty($input['is_enabled']) ? 1 : 0) : 1;
        $encrypted = Crypto::encrypt($appPassword);

        if ($existing) {
            Database::execute(
                'UPDATE email_verification_accounts
                 SET gmail_address = ?, app_password_encrypted = ?, app_password_last4 = ?, upi_id = ?,
                     imap_host = ?, imap_port = ?, imap_encryption = ?, folder = ?, sender_whitelist = ?,
                     is_enabled = ?, connection_status = "not_configured", last_test_message = NULL,
                     last_error = NULL, updated_at = UTC_TIMESTAMP()
                 WHERE merchant_id = ?',
                [
                    $gmail, $encrypted, substr($appPassword, -4), $upi, $host, $port, $encryption,
                    $folder, $senders, $enabled, $merchantId,
                ]
            );
        } else {
            Database::insert(
                'INSERT INTO email_verification_accounts
                 (merchant_id, gmail_address, app_password_encrypted, app_password_last4, upi_id,
                  imap_host, imap_port, imap_encryption, folder, sender_whitelist, is_enabled,
                  connection_status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "not_configured", UTC_TIMESTAMP())',
                [
                    $merchantId, $gmail, $encrypted, substr($appPassword, -4), $upi, $host, $port,
                    $encryption, $folder, $senders, $enabled,
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

    public static function setEnabled(int $accountId, bool $enabled): void
    {
        Database::execute(
            'UPDATE email_verification_accounts SET is_enabled = ?, connection_status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$enabled ? 1 : 0, $enabled ? 'not_configured' : 'disabled', $accountId]
        );
    }

    /**
     * Authenticate and read the mailbox without changing any payment.
     * @return array{connected:bool,message:string,unseen:int}
     */
    public static function test(array $account): array
    {
        if (empty($account['app_password'])) {
            return ['connected' => false, 'message' => 'The stored Google App Password could not be decrypted.', 'unseen' => 0];
        }
        $id = (int) ($account['id'] ?? 0);
        if ($id > 0) {
            Database::execute('UPDATE email_verification_accounts SET connection_status = "testing", updated_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
        }
        $client = self::client($account);
        try {
            $client->connect();
            $client->login((string) $account['gmail_address'], (string) $account['app_password']);
            $client->select((string) ($account['folder'] ?: 'INBOX'));
            $uids = $client->uidSearch('UNSEEN');
            $client->logout();
            $count = count($uids);
            $message = 'Authenticated with Gmail IMAP. ' . $count . ' unseen message(s) in ' . ($account['folder'] ?: 'INBOX') . '.';
            if ($id > 0) {
                Database::execute(
                    'UPDATE email_verification_accounts SET connection_status = "connected", last_test_at = UTC_TIMESTAMP(),
                     last_test_message = ?, last_error = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    [mb_substr($message, 0, 500), $id]
                );
            }
            Audit::security('email_imap_test_success', 'info', [
                'account_id' => $id,
                'merchant_id' => (int) ($account['merchant_id'] ?? 0),
                'unseen' => $count,
            ], null, null);
            return ['connected' => true, 'message' => $message, 'unseen' => $count];
        } catch (Throwable $e) {
            $message = self::safeError($e->getMessage(), (string) $account['app_password']);
            if ($id > 0) {
                Database::execute(
                    'UPDATE email_verification_accounts SET connection_status = "failed", last_test_at = UTC_TIMESTAMP(),
                     last_test_message = ?, last_error = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    [mb_substr($message, 0, 500), mb_substr($message, 0, 500), $id]
                );
            }
            Audit::security('email_imap_test_failed', 'warning', [
                'account_id' => $id,
                'merchant_id' => (int) ($account['merchant_id'] ?? 0),
                'message' => mb_substr($message, 0, 200),
            ], null, null);
            return ['connected' => false, 'message' => $message, 'unseen' => 0];
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
        if (empty($account['app_password'])) {
            $summary['error'] = 'The stored Google App Password could not be decrypted.';
            return $summary;
        }
        $limit = $limit ?? max(1, min(100, Settings::int('email_verification_max_per_run', 20)));
        $client = self::client($account);
        $checkedAt = gmdate('Y-m-d H:i:s');
        try {
            $client->connect();
            $client->login((string) $account['gmail_address'], (string) $account['app_password']);
            $client->select((string) ($account['folder'] ?: 'INBOX'));
            $uids = $client->uidSearch('UNSEEN');
            if (!$uids) {
                Database::execute(
                    'UPDATE email_verification_accounts SET last_checked_at = ?, connection_status = "connected", last_error = NULL WHERE id = ?',
                    [$checkedAt, (int) $account['id']]
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
                        $client->markSeen($uid);
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
                    $client->markSeen($uid);
                } catch (Throwable) {
                }
                $summary['checked']++;
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
        $row['sender_list'] = self::senderList((string) ($row['sender_whitelist'] ?? self::DEFAULT_SENDERS));
        $row['app_password'] = null;
        try {
            $row['app_password'] = Crypto::decrypt((string) $row['app_password_encrypted']);
        } catch (Throwable $e) {
            Logger::error('Email verification credential decryption failed', [
                'account_id' => $row['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $row['connection_status'] = 'failed';
            $row['last_error'] = 'The stored Google App Password could not be decrypted. Re-save the credential.';
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
