<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Small, defensive MIME parser for transaction-alert emails.
 * It extracts only the headers and readable text needed for verification and
 * never executes or renders HTML.
 */
final class EmailParser
{
    /**
     * @return array{from:string,from_address:string,subject:string,message_id:string,date:?string,text:string}
     */
    public static function parse(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $split = explode("\n\n", $raw, 2);
        $headerText = $split[0] ?? '';
        $body = $split[1] ?? '';
        $headers = self::parseHeaders($headerText);
        $from = self::decodeHeader((string) ($headers['from'] ?? ''));
        $subject = self::decodeHeader((string) ($headers['subject'] ?? ''));
        $messageId = trim((string) ($headers['message-id'] ?? ''), " \t<>");
        $date = null;
        if (!empty($headers['date'])) {
            $timestamp = strtotime((string) $headers['date']);
            if ($timestamp !== false) {
                $date = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        return [
            'from' => $from,
            'from_address' => self::extractAddress($from),
            'subject' => mb_substr($subject, 0, 255),
            'message_id' => mb_substr($messageId, 0, 255),
            'date' => $date,
            'text' => self::extractText($headers, $body),
        ];
    }

    /** @return array<string,string> */
    private static function parseHeaders(string $headerText): array
    {
        $headers = [];
        $current = null;
        foreach (preg_split('/\n/', $headerText) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            if (($line[0] === ' ' || $line[0] === "\t") && $current !== null) {
                $headers[$current] .= ' ' . trim($line);
                continue;
            }
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $current = strtolower(trim($key));
                $headers[$current] = trim($value);
            }
        }
        return $headers;
    }

    /** @param array<string,string> $headers */
    private static function extractText(array $headers, string $body, int $depth = 0): string
    {
        if ($depth > 5) {
            return '';
        }
        $contentType = (string) ($headers['content-type'] ?? 'text/plain');
        if (stripos($contentType, 'multipart/') === 0
            && preg_match('/boundary="?([^";]+)"?/i', $contentType, $match) === 1) {
            $boundary = trim($match[1]);
            $plain = [];
            $html = [];
            foreach (explode('--' . $boundary, $body) as $part) {
                $part = ltrim($part, "\n");
                if ($part === '' || str_starts_with($part, '--')) {
                    continue;
                }
                $partSplit = explode("\n\n", $part, 2);
                $partHeaders = self::parseHeaders($partSplit[0] ?? '');
                $partBody = $partSplit[1] ?? '';
                $partType = strtolower((string) ($partHeaders['content-type'] ?? ''));
                $text = self::extractText($partHeaders, $partBody, $depth + 1);
                if (str_starts_with($partType, 'text/html')) {
                    $html[] = $text;
                } elseif (str_starts_with($partType, 'text/plain') || $partType === '') {
                    $plain[] = $text;
                }
            }
            $combined = trim(implode("\n", $plain));
            if ($combined === '') {
                $combined = trim(implode("\n", $html));
            }
            return $combined;
        }

        $decoded = self::decodeBody($headers, $body);
        $type = strtolower($contentType);
        if (str_contains($type, 'text/html')) {
            $decoded = self::htmlToText($decoded);
        }
        return trim($decoded);
    }

    /** @param array<string,string> $headers */
    private static function decodeBody(array $headers, string $body): string
    {
        $encoding = strtolower(trim((string) ($headers['content-transfer-encoding'] ?? '')));
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
            $body = $decoded === false ? '' : $decoded;
        } elseif ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        }

        $charset = '';
        if (preg_match('/charset="?([A-Za-z0-9._-]+)"?/i', (string) ($headers['content-type'] ?? ''), $match) === 1) {
            $charset = $match[1];
        }
        if ($charset !== '' && strtoupper($charset) !== 'UTF-8') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if (is_string($converted)) {
                $body = $converted;
            }
        }
        return $body;
    }

    private static function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $decoded = @mb_decode_mimeheader($value);
        return is_string($decoded) ? trim($decoded) : trim($value);
    }

    private static function extractAddress(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $match) === 1) {
            return strtolower(trim($match[1]));
        }
        if (filter_var(trim($from), FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($from));
        }
        return '';
    }

    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n", $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
