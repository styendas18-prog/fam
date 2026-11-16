<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'MF1';

    private static function key(): string
    {
        $raw = (string) Config::get('key', '');
        $decoded = base64_decode($raw, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('APP_KEY is missing or invalid. It must be a base64-encoded 32-byte key.');
        }
        return $decoded;
    }

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'maxxfam:v1', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::PREFIX . '.' . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX . '.')) {
            throw new RuntimeException('Unrecognised ciphertext format.');
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX) + 1), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Ciphertext is corrupt.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plain = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'maxxfam:v1');
        if ($plain === false) {
            throw new RuntimeException('Decryption failed — wrong key or tampered data.');
        }
        return $plain;
    }

    public static function generateAppKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
