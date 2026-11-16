<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(array $data = [], int $status = 200, array $meta = []): never
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        self::json($payload, $status);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): never
    {
        $payload = ['success' => false, 'error' => ['code' => $code, 'message' => $message]];
        if ($details) {
            $payload['error']['details'] = $details;
        }
        self::json($payload, $status);
    }

    public static function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}
