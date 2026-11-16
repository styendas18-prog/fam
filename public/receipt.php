<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Request;
use App\Models\InvoiceModel;
use App\Models\PaymentOrderModel;
use App\Services\PdfService;

$token = Request::str('token');
$invoiceNumber = Request::str('invoice');

$order = null;
$invoice = null;

if ($token !== '') {
    $order = PaymentOrderModel::findByToken($token);
} elseif ($invoiceNumber !== '') {
    $invoice = InvoiceModel::findByInvoiceNumber($invoiceNumber);
    if ($invoice) {
        $order = PaymentOrderModel::find((int) $invoice['order_id']);
    }
}

if (!$order || $order['status'] !== 'success') {
    http_response_code(404);
    exit('Receipt not found. Receipts are only issued for successfully verified payments.');
}

$transaction = Database::fetch(
    'SELECT t.*, p.name AS provider_name FROM transactions t JOIN payment_providers p ON p.id = t.provider_id WHERE t.order_id = ? LIMIT 1',
    [$order['id']]
);

if (!$transaction) {
    http_response_code(404);
    exit('Transaction record not found.');
}

if (!$invoice) {
    $invoice = Database::fetch('SELECT * FROM invoices WHERE order_id = ? LIMIT 1', [$order['id']]);
    if (!$invoice) {
        $invNum = InvoiceModel::nextNumber();
        $invId = InvoiceModel::create([
            'invoice_number' => $invNum,
            'order_id' => (int) $order['id'],
            'transaction_id' => (int) $transaction['id'],
            'merchant_id' => (int) $order['merchant_id'],
            'amount' => (float) ($order['payable_amount'] ?? $order['amount']),
            'currency' => $order['currency'] ?? 'INR',
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'pdf_path' => 'storage/invoices/' . $invNum . '.pdf',
        ]);
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ? LIMIT 1', [$invId]);
    }
}

$pdfData = [
    'invoice_number' => $invoice['invoice_number'],
    'order_id' => $order['public_id'],
    'provider_order_id' => $order['provider_order_id'] ?? '',
    'provider_name' => $transaction['provider_name'] ?? 'UPI Provider',
    'amount' => (float) ($order['payable_amount'] ?? $order['amount']),
    'utr' => (string) $transaction['utr'],
    'sender_name' => $transaction['sender_name'] ?? null,
    'customer_name' => $order['customer_name'] ?? null,
    'customer_email' => $order['customer_email'] ?? null,
    'description' => $order['description'] ?? null,
    'business_name' => $order['business_name'] ?? 'Merchant',
    'merchant_code' => $order['merchant_code'] ?? '',
    'support_email' => $order['support_email'] ?? '',
    'issued_at_ist' => ist($invoice['issued_at'] ?? 'now', 'd M Y, H:i'),
    'verified_at_ist' => ist($transaction['verified_at'] ?? 'now', 'd M Y, H:i'),
];

$pdfContent = PdfService::invoice($pdfData);

$filename = 'MAXXfam-Receipt-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $invoice['invoice_number']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
header('Cache-Control: private, max-age=3600');

echo $pdfContent;
exit;
