<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Small dependency-free PDF writer for receipts and reports.
 * Produces valid A4 PDF 1.4 documents using the core Helvetica fonts.
 */
final class PdfService
{
    private const WIDTH = 595;
    private const HEIGHT = 842;

    public static function invoice(array $d): string
    {
        $pages = [];
        $p = [];
        $p[] = '0.031 0.055 0.118 rg 0 742 595 100 re f';
        $p[] = self::text(46, 796, 24, 'F2', 'MAXXfam', [1, 1, 1]);
        $p[] = self::text(46, 776, 9, 'F1', 'Smart Payments. Real Verification. Complete Control.', [0.65, 0.78, 1]);
        $p[] = self::text(400, 801, 10, 'F2', 'PAYMENT RECEIPT', [1, 1, 1]);
        $p[] = self::text(400, 783, 9, 'F1', 'Verified transaction', [0.72, 0.8, 0.95]);

        $p[] = self::text(46, 712, 9, 'F1', 'AMOUNT RECEIVED', [0.38, 0.45, 0.56]);
        $p[] = self::text(46, 682, 28, 'F2', 'INR ' . number_format((float) $d['amount'], 2), [0.05, 0.09, 0.16]);
        $p[] = self::badge(46, 655, 'VERIFIED', [0.02, 0.59, 0.41]);
        $p[] = self::text(46, 628, 9, 'F1', 'Verified server-side from provider status. Browser redirects are never trusted.', [0.38, 0.45, 0.56]);
        $p[] = self::text(400, 712, 8, 'F1', 'INVOICE', [0.38, 0.45, 0.56]);
        $p[] = self::text(400, 696, 12, 'F2', (string) $d['invoice_number'], [0.05, 0.09, 0.16]);
        $p[] = self::text(400, 678, 8, 'F1', 'Issued ' . (string) $d['issued_at_ist'] . ' IST', [0.38, 0.45, 0.56]);
        $p[] = self::line(46, 610, 549, 610, [0.85, 0.88, 0.93]);

        $p[] = self::text(46, 584, 9, 'F2', 'BILLED BY', [0.38, 0.45, 0.56]);
        $p[] = self::text(46, 566, 12, 'F2', (string) $d['business_name'], [0.05, 0.09, 0.16]);
        $p[] = self::text(46, 550, 9, 'F1', 'Merchant code: ' . (string) $d['merchant_code'], [0.25, 0.32, 0.42]);
        $p[] = self::text(46, 536, 9, 'F1', 'Support: ' . (string) ($d['support_email'] ?: 'See merchant website'), [0.25, 0.32, 0.42]);

        $p[] = self::text(330, 584, 9, 'F2', 'PAYMENT DETAILS', [0.38, 0.45, 0.56]);
        $rows = [
            ['Order ID', (string) $d['order_id']],
            ['Provider order', (string) ($d['provider_order_id'] ?: '—')],
            ['Payment provider', (string) $d['provider_name']],
            ['Payment method', 'UPI'],
            ['UTR', (string) $d['utr']],
            ['Payer', (string) ($d['sender_name'] ?: 'Not supplied by bank')],
            ['Verified at', (string) $d['verified_at_ist'] . ' IST'],
        ];
        $y = 566;
        foreach ($rows as [$label, $value]) {
            $p[] = self::text(330, $y, 8, 'F1', $label, [0.42, 0.48, 0.58]);
            $p[] = self::text(410, $y, 8.5, 'F2', self::truncate($value, 52), [0.08, 0.13, 0.22]);
            $p[] = self::line(330, $y - 5, 549, $y - 5, [0.93, 0.94, 0.96]);
            $y -= 19;
        }

        $p[] = self::text(46, 420, 9, 'F2', 'CUSTOMER', [0.38, 0.45, 0.56]);
        $p[] = self::text(46, 403, 10, 'F1', (string) ($d['customer_name'] ?: '—'), [0.08, 0.13, 0.22]);
        $p[] = self::text(46, 388, 9, 'F1', (string) ($d['customer_email'] ?: ''), [0.35, 0.42, 0.52]);
        $p[] = self::text(46, 373, 9, 'F1', self::truncate((string) ($d['description'] ?: ''), 80), [0.35, 0.42, 0.52]);

        $p[] = '0.96 0.98 1 rg 46 255 549 78 re f';
        $p[] = self::text(60, 313, 8, 'F2', 'VERIFICATION NOTICE', [0.12, 0.28, 0.58]);
        $p[] = self::text(60, 297, 8.2, 'F1', 'This receipt was generated only after the payment provider confirmed a successful', [0.25, 0.32, 0.42]);
        $p[] = self::text(60, 284, 8.2, 'F1', 'UPI payment. The amount, currency, provider order ID and unique UTR were validated', [0.25, 0.32, 0.42]);
        $p[] = self::text(60, 271, 8.2, 'F1', 'server-side and committed in an atomic database transaction.', [0.25, 0.32, 0.42]);

        $p[] = self::text(46, 120, 8, 'F2', 'MAXXfam', [0.1, 0.16, 0.27]);
        $p[] = self::text(46, 106, 7.5, 'F1', 'This document is an automatically generated payment receipt. It is not a tax invoice', [0.42, 0.48, 0.58]);
        $p[] = self::text(46, 94, 7.5, 'F1', 'unless the merchant has separately configured GST details. Funds settle directly to the', [0.42, 0.48, 0.58]);
        $p[] = self::text(46, 82, 7.5, 'F1', 'merchant UPI account through the connected provider.', [0.42, 0.48, 0.58]);
        $p[] = self::text(46, 54, 7, 'F1', 'Document hash: ' . substr(hash('sha256', json_encode($d)), 0, 32), [0.6, 0.64, 0.7]);
        $pages[] = $p;

        return self::document($pages, 'MAXXfam receipt ' . $d['invoice_number']);
    }

    /**
     * @param array{title:string,subtitle:string,summary:array,headers:array,rows:array} $d
     */
    public static function report(array $d): string
    {
        $pages = [];
        $page = [];
        $pageNumber = 1;
        $y = 790;
        $page[] = self::text(46, 800, 18, 'F2', (string) $d['title'], [0.04, 0.08, 0.15]);
        $page[] = self::text(46, 782, 9, 'F1', (string) $d['subtitle'], [0.4, 0.46, 0.55]);
        foreach ($d['summary'] as $line) {
            $page[] = self::text(46, $y, 8.5, 'F1', (string) $line, [0.25, 0.32, 0.42]);
            $y -= 13;
        }
        $y -= 8;
        $page[] = self::line(46, $y, 549, $y, [0.8, 0.84, 0.9]);
        $y -= 18;
        $colX = [46, 120, 250, 410, 500];
        foreach ($d['headers'] as $i => $h) {
            $page[] = self::text($colX[$i] ?? 46, $y, 7.5, 'F2', strtoupper((string) $h), [0.35, 0.42, 0.52]);
        }
        $y -= 8;
        $page[] = self::line(46, $y, 549, $y, [0.85, 0.88, 0.92]);
        $y -= 16;
        foreach ($d['rows'] as $row) {
            if ($y < 70) {
                $page[] = self::text(46, 40, 7, 'F1', 'MAXXfam report · page ' . $pageNumber, [0.55, 0.6, 0.68]);
                $page[] = self::text(500, 40, 7, 'F1', 'Generated ' . gmdate('d M Y H:i') . ' UTC', [0.55, 0.6, 0.68]);
                $pages[] = $page;
                $pageNumber++;
                $page = [];
                $y = 790;
                $page[] = self::text(46, 800, 14, 'F2', (string) $d['title'] . ' (continued)', [0.04, 0.08, 0.15]);
                $y -= 30;
            }
            foreach (array_values($row) as $i => $cell) {
                $page[] = self::text($colX[$i] ?? 46, $y, 7.5, 'F1', self::truncate((string) $cell, $i === 2 ? 32 : 22), [0.12, 0.17, 0.26]);
            }
            $y -= 16;
        }
        if ($y < 70) {
            $page[] = self::text(46, 40, 7, 'F1', 'MAXXfam report · page ' . $pageNumber, [0.55, 0.6, 0.68]);
            $pages[] = $page;
        } else {
            $page[] = self::text(46, 40, 7, 'F1', 'MAXXfam report · page ' . $pageNumber, [0.55, 0.6, 0.68]);
            $page[] = self::text(470, 40, 7, 'F1', 'Generated ' . gmdate('d M Y H:i') . ' UTC', [0.55, 0.6, 0.68]);
            $pages[] = $page;
        }
        return self::document($pages, $d['title']);
    }

    private static function document(array $pages, string $title): string
    {
        $objects = [];
        $pageCount = count($pages);
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($pages as $i => $commands) {
            $pageId = 5 + $i * 2;
            $contentId = $pageId + 1;
            $content = implode("\n", $commands);
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $infoId = 5 + $pageCount * 2;
        $objects[$infoId] = '<< /Title (' . self::escape($title) . ') /Producer (MAXXfam) /Creator (MAXXfam) >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= isset($offsets[$i]) ? str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n" : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    private static function text(float $x, float $y, float $size, string $font, string $text, array $rgb = [0, 0, 0]): string
    {
        $color = sprintf('%.3F %.3F %.3F rg', $rgb[0], $rgb[1], $rgb[2]);
        return sprintf("BT /%s %.2F Tf %s %.2F %.2F Td (%s) Tj ET", $font, $size, $color, $x, $y, self::escape($text));
    }

    private static function line(float $x1, float $y1, float $x2, float $y2, array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S', $rgb[0], $rgb[1], $rgb[2], 0.7, $x1, $y1, $x2, $y2);
    }

    private static function badge(float $x, float $y, string $text, array $rgb): string
    {
        $w = max(64, strlen($text) * 5.2 + 18);
        return sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F 17 re f BT /F2 7.5 Tf 1 1 1 rg %.2F %.2F Td (%s) Tj ET', $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $x + 9, $y + 5, self::escape($text));
    }

    private static function escape(string $text): string
    {
        $text = str_replace(['₹', '—', '–', '’', '“', '”', '•'], ['INR ', '-', '-', "'", '"', '"', '-'], $text);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }

    private static function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
