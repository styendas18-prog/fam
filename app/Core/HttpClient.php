<?php
declare(strict_types=1);

namespace App\Core;

final class HttpClient
{
    /**
     * @return array{status:int,headers:array<string,string>,body:string,error:?string,latency_ms:int}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 15, ?string $resolveIp = null): array
    {
        $start = hrtime(true);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'Unable to initialise HTTP client.', 'latency_ms' => 0];
        }
        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'MAXXfam/1.0 (+server-side)',
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($k))] = trim($v);
                }
                return $len;
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($resolveIp !== null) {
            $parts = parse_url($url);
            $host = $parts['host'] ?? '';
            $port = $parts['port'] ?? (($parts['scheme'] ?? '') === 'http' ? 80 : 443);
            if ($host !== '') {
                curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . $port . ':' . $resolveIp]);
            }
        }
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $latency = (int) round((hrtime(true) - $start) / 1_000_000);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => is_string($result) ? $result : '',
            'error' => $error,
            'latency_ms' => $latency,
        ];
    }

    public static function get(string $url, array $headers = [], int $timeout = 15): array
    {
        return self::request('GET', $url, $headers, null, $timeout);
    }

    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 20): array
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        return self::request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_SLASHES), $timeout);
    }
}
