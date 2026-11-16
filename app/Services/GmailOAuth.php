<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpClient;
use App\Core\Logger;
use RuntimeException;

/**
 * Gmail OAuth 2.0 (installed-app / web flow) for IMAP XOAUTH2.
 *
 * Google is phasing out App Passwords for many Workspace tenants, so the
 * IMAP pipeline supports both. OAuth credentials protect the mailbox without
 * storing the account password anywhere.
 *
 * Required environment (Google Cloud Console > APIs & Services > Credentials):
 *   GMAIL_OAUTH_CLIENT_ID
 *   GMAIL_OAUTH_CLIENT_SECRET
 *   GMAIL_OAUTH_REDIRECT_URI   (must match the authorised redirect exactly)
 *
 * Scope: https://mail.google.com/ (full IMAP/SMTP access).
 */
final class GmailOAuth
{
    public const SCOPE = 'https://mail.google.com/';
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';
    private const EXPIRY_SKEW_SECONDS = 120;

    public static function configured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function clientId(): string
    {
        return trim((string) Env::get('GMAIL_OAUTH_CLIENT_ID', ''));
    }

    public static function clientSecret(): string
    {
        return trim((string) Env::get('GMAIL_OAUTH_CLIENT_SECRET', ''));
    }

    public static function redirectUri(): string
    {
        $configured = trim((string) Env::get('GMAIL_OAUTH_REDIRECT_URI', ''));
        if ($configured !== '') {
            return $configured;
        }
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        return $base !== '' ? $base . '/user/gmail-oauth.php' : '';
    }

    public static function authUrl(string $state, ?string $loginHint = null): string
    {
        self::assertConfigured();
        $params = [
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ];
        if ($loginHint !== null && $loginHint !== '') {
            $params['login_hint'] = $loginHint;
        }
        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * @return array{refresh_token:string,access_token:string,expires_at:string,scope:string,email:?string,id_token:?string}
     */
    public static function exchange(string $code): array
    {
        self::assertConfigured();
        return self::tokenRequest([
            'code' => $code,
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ], 'authorization code');
    }

    /**
     * @return array{refresh_token:string,access_token:string,expires_at:string,scope:string,email:?string,id_token:?string}
     */
    public static function refresh(string $refreshToken): array
    {
        self::assertConfigured();
        return self::tokenRequest([
            'refresh_token' => $refreshToken,
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'grant_type' => 'refresh_token',
        ], 'refresh token');
    }

    /**
     * Returns a usable access token for an account row, refreshing and caching
     * it in the database when needed.
     */
    public static function accessToken(array $account): string
    {
        $email = (string) ($account['gmail_address'] ?? '');
        $cached = (string) ($account['oauth_access_token'] ?? '');
        $expiresAt = (string) ($account['oauth_token_expires_at'] ?? '');

        if ($cached !== '' && $expiresAt !== '') {
            $expiry = strtotime($expiresAt . ' UTC');
            if ($expiry !== false && $expiry - self::EXPIRY_SKEW_SECONDS > time()) {
                return $cached;
            }
        }

        $refreshToken = (string) ($account['oauth_refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new RuntimeException('This Gmail account is set to OAuth 2.0 but no refresh token is stored. Reconnect the mailbox.');
        }

        $tokens = self::refresh($refreshToken);
        if ($tokens['access_token'] === '') {
            throw new RuntimeException('Google did not return an access token for ' . ($email !== '' ? $email : 'this mailbox') . '.');
        }
        $id = (int) ($account['id'] ?? 0);
        if ($id > 0) {
            try {
                Database::execute(
                    'UPDATE email_verification_accounts
                     SET oauth_access_token_encrypted = ?, oauth_token_expires_at = ?, updated_at = UTC_TIMESTAMP()
                     WHERE id = ?',
                    [Crypto::encrypt($tokens['access_token']), $tokens['expires_at'], $id]
                );
            } catch (\Throwable $e) {
                Logger::warning('Unable to cache Gmail OAuth access token', ['account_id' => $id, 'error' => $e->getMessage()]);
            }
        }
        return $tokens['access_token'];
    }

    /** Persist the long-lived refresh token for an account. */
    public static function storeRefreshToken(int $accountId, string $refreshToken, string $tokenEmail = ''): void
    {
        if ($refreshToken === '') {
            throw new RuntimeException('An empty refresh token cannot be stored.');
        }
        Database::execute(
            'UPDATE email_verification_accounts
             SET auth_method = "oauth2", oauth_refresh_token_encrypted = ?, oauth_access_token_encrypted = NULL,
                 oauth_token_expires_at = NULL, connection_status = "not_configured", last_error = NULL,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
            [Crypto::encrypt($refreshToken), $accountId]
        );
        if ($tokenEmail !== '') {
            Database::execute(
                'UPDATE email_verification_accounts SET gmail_address = ? WHERE id = ?',
                [$tokenEmail, $accountId]
            );
        }
    }

    public static function revoke(string $token): void
    {
        if ($token === '') {
            return;
        }
        try {
            HttpClient::request(
                'POST',
                self::REVOKE_ENDPOINT,
                ['Content-Type' => 'application/x-www-form-urlencoded'],
                http_build_query(['token' => $token]),
                15
            );
        } catch (\Throwable) {
            // Revocation is best effort.
        }
    }

    /**
     * @return array{refresh_token:string,access_token:string,expires_at:string,scope:string,email:?string,id_token:?string}
     */
    private static function tokenRequest(array $payload, string $what): array
    {
        $response = HttpClient::request(
            'POST',
            self::TOKEN_ENDPOINT,
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            http_build_query($payload),
            20
        );
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            throw new RuntimeException('Google token endpoint returned an unreadable response (HTTP ' . $response['status'] . ').');
        }
        if (!empty($json['error'])) {
            $detail = (string) ($json['error_description'] ?? $json['error']);
            throw new RuntimeException('Google rejected the ' . $what . ': ' . $detail);
        }
        $accessToken = (string) ($json['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Google did not return an access token for the ' . $what . '.');
        }
        $expiresIn = (int) ($json['expires_in'] ?? 3600);
        $idToken = isset($json['id_token']) ? (string) $json['id_token'] : null;
        return [
            'refresh_token' => (string) ($json['refresh_token'] ?? ''),
            'access_token' => $accessToken,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + max(60, $expiresIn)),
            'scope' => (string) ($json['scope'] ?? ''),
            'email' => $idToken !== null ? self::emailFromIdToken($idToken) : null,
            'id_token' => $idToken,
        ];
    }

    private static function emailFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return null;
        }
        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }
        $claims = json_decode($decoded, true);
        if (!is_array($claims)) {
            return null;
        }
        $email = (string) ($claims['email'] ?? '');
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function assertConfigured(): void
    {
        if (!self::configured()) {
            throw new RuntimeException('Gmail OAuth is not configured. Set GMAIL_OAUTH_CLIENT_ID, GMAIL_OAUTH_CLIENT_SECRET and GMAIL_OAUTH_REDIRECT_URI.');
        }
    }
}
