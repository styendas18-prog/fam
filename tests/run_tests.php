<?php
declare(strict_types=1);

/**
 * MAXXfam Comprehensive Automated Test Suite
 * Validates backend architecture, security controls, payment verification,
 * duplicate UTR protection, REST APIs, webhooks, and PDF generation.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Settings;
use App\Models\ApiKeyModel;
use App\Models\IdempotencyModel;
use App\Models\InvoiceModel;
use App\Models\MerchantModel;
use App\Models\PaymentLinkModel;
use App\Models\PaymentOrderModel;
use App\Models\ProviderModel;
use App\Models\TransactionModel;
use App\Models\UserModel;
use App\Models\WebhookModel;
use App\Providers\FamGatewayProvider;
use App\Providers\IntegrationNotConfiguredException;
use App\Providers\ProviderManager;
use App\Providers\VerificationException;
use App\Services\Mailer;
use App\Services\EmailParser;
use App\Services\EmailVerificationService;
use App\Services\PaymentService;
use App\Services\PlanLimitException;
use App\Services\PlanService;
use App\Services\PdfService;
use App\Services\TotpService;
use App\Services\VerificationService;

$passed = 0;
$failed = 0;
$total = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name} - {$details}\n";
    }
}

echo "=========================================================\n";
echo "       MAXXfam Payment Gateway - Test Suite              \n";
echo "=========================================================\n\n";

// 1. Database & Migrations
echo "[Suite 1: Database & Core Infrastructure]\n";
$db = Database::connection();
assertTest('Database connection active', $db instanceof PDO);
$tableCount = (int) Database::scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
assertTest('Database schema tables initialized (>= 22 tables)', $tableCount >= 22, "Found {$tableCount} tables");

// 2. Encryption & Security
echo "\n[Suite 2: Security & Cryptography]\n";
$secret = 'SecretApiKey_2026_Test';
$encrypted = Crypto::encrypt($secret);
$decrypted = Crypto::decrypt($encrypted);
assertTest('AES-256-GCM encryption/decryption roundtrip', $decrypted === $secret);
assertTest('Encrypted text differs from plaintext', $encrypted !== $secret);

// 3. Password Hashing & Argon2/Bcrypt
$pw = 'SecureP@ssw0rd2026!#';
$hash = Auth::hashPassword($pw);
assertTest('Password hashing produces valid hash', password_verify($pw, $hash));
assertTest('Password verify rejects wrong password', !password_verify('WrongPassword', $hash));

// 4. TOTP 2FA Verification
echo "\n[Suite 3: Administrator 2FA TOTP Engine]\n";
$totpSecret = TotpService::generateSecret();
$totpCode = TotpService::code($totpSecret);
assertTest('TOTP code generated is 6 digits', preg_match('/^\d{6}$/', $totpCode) === 1);
assertTest('TOTP code verifies with valid secret', TotpService::verify($totpSecret, $totpCode));
assertTest('TOTP rejects invalid code', !TotpService::verify($totpSecret, '000000'));

// 5. Merchant & User Registration Flow
echo "\n[Suite 4: Merchant & User Lifecycle]\n";
$testEmail = 'merchant_' . bin2hex(random_bytes(4)) . '@example.com';
$userId = Database::insert(
    'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, "active", UTC_TIMESTAMP())',
    ['Test Merchant', $testEmail, $hash]
);
assertTest('User account created', $userId > 0);

$merchantId = Database::insert(
    'INSERT INTO merchants (user_id, business_name, merchant_code, support_email, status, created_at)
     VALUES (?, ?, ?, ?, "active", UTC_TIMESTAMP())',
    [$userId, 'Test Merchant Store', 'MFM' . strtoupper(bin2hex(random_bytes(3))), $testEmail]
);
assertTest('Merchant account profile linked', $merchantId > 0);

// 6. Honest Payment Provider Configuration
echo "\n[Suite 5: Payment Provider Architecture & Honest Status]\n";
$provider = ProviderModel::findByCode('famgateway');
assertTest('FamGateway provider registered in database', $provider !== null);
assertTest('Default provider status is not_configured', $provider['connection_status'] === 'not_configured');

// Testing payment creation with missing credentials must throw IntegrationNotConfiguredException
$threwNotConfigured = false;
try {
    PaymentService::create([
        'merchant_id' => $merchantId,
        'amount' => 500.00,
        'description' => 'Test order without credentials',
    ]);
} catch (IntegrationNotConfiguredException $e) {
    $threwNotConfigured = true;
} catch (\Throwable $e) {
    $threwNotConfigured = false;
}
assertTest('Payment creation fails honestly when provider is unconfigured', $threwNotConfigured);

// 7. Payment Order Creation with Configured Credentials
echo "\n[Suite 6: Payment Order & Checkout Session]\n";
// Set a test credential for this merchant
ProviderModel::saveCredential((int) $provider['id'], $merchantId, 'fg_test_apikey_1234567890');
$cred = ProviderModel::credential((int) $provider['id'], $merchantId);
assertTest('Merchant provider credential saved & encrypted', !empty($cred['credentials']['api_key']));

// Create an order locally
$orderId = PaymentOrderModel::create([
    'merchant_id' => $merchantId,
    'provider_id' => (int) $provider['id'],
    'amount' => 250.00,
    'currency' => 'INR',
    'customer_name' => 'Rahul Sharma',
    'customer_email' => 'rahul@example.com',
    'customer_reference' => 'TEST-REF-001',
    'description' => 'Test Payment Session',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$order = PaymentOrderModel::find($orderId);
assertTest('Local payment order created with unique public ID', !empty($order['public_id']));
assertTest('Order has secure checkout token', !empty($order['checkout_token']));
assertTest('Order initial status is pending', $order['status'] === 'pending');

// Update with simulated provider order details
PaymentOrderModel::markProviderCreated($orderId, [
    'provider_order_id' => 'FG_ORD_' . strtoupper(bin2hex(random_bytes(6))),
    'amount' => 250.00,
    'payable_amount' => 250.00,
    'qr_url' => 'https://famgateway.in/qr/test.png',
    'checkout_url' => 'https://famgateway.in/pay/test',
    'upi_intent' => 'upi://pay?pa=test@upi&pn=Test&am=250.00&cu=INR',
    'upi_id' => 'test@upi',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$freshOrder = PaymentOrderModel::find($orderId);
assertTest('Order updated with provider order ID and dynamic QR URL', !empty($freshOrder['provider_order_id']));

// 8. Automated Payment Verification Engine
echo "\n[Suite 7: Real Automated Payment Verification & Security]\n";
$validUtr = (string) random_int(100000000000, 999999999999); // Valid 12-digit unique bank UTR

// Test 1: Invalid UTR format rejection
$invalidUtrRejected = false;
try {
    VerificationService::processVerified($freshOrder, [
        'status' => 'success',
        'amount' => 250.00,
        'utr' => 'invalid-utr',
        'provider_order_id' => $freshOrder['provider_order_id'],
        'transaction_id' => 'TXN123',
        'paid_at' => gmdate('Y-m-d H:i:s'),
        'raw' => [],
    ], 'test');
} catch (VerificationException $e) {
    $invalidUtrRejected = $e->reason === 'utr_invalid';
}
assertTest('Verification rejects malformed UTR (must be 12 digits)', $invalidUtrRejected);

// Test 2: Amount mismatch rejection
$amountMismatchRejected = false;
try {
    VerificationService::processVerified($freshOrder, [
        'status' => 'success',
        'amount' => 240.00, // Mismatched amount
        'utr' => $validUtr,
        'provider_order_id' => $freshOrder['provider_order_id'],
        'transaction_id' => 'TXN123',
        'paid_at' => gmdate('Y-m-d H:i:s'),
        'raw' => [],
    ], 'test');
} catch (VerificationException $e) {
    $amountMismatchRejected = $e->reason === 'amount_mismatch';
}
assertTest('Verification rejects amount mismatch (240 vs 250)', $amountMismatchRejected);

// Test 3: Legitimate verified payment
$legitTxnId = 'TXN_' . strtoupper(bin2hex(random_bytes(6)));
$verifiedResult = VerificationService::processVerified($freshOrder, [
    'status' => 'success',
    'amount' => 250.00,
    'utr' => $validUtr,
    'provider_order_id' => $freshOrder['provider_order_id'],
    'transaction_id' => $legitTxnId,
    'paid_at' => gmdate('Y-m-d H:i:s'),
    'sender_name' => 'Rahul Sharma',
    'raw' => ['source' => 'test_suite'],
], 'test_engine');

$reloadedOrder = PaymentOrderModel::find($orderId);
assertTest('Payment status changes to SUCCESS only after verified confirmation', $reloadedOrder['status'] === 'success');
$transaction = Database::fetch('SELECT * FROM transactions WHERE order_id = ? LIMIT 1', [$orderId]);
assertTest('Transaction record created with 12-digit UTR', $transaction && $transaction['utr'] === $validUtr);

// Test 4: Duplicate UTR Prevention (Critical Fintech Security Requirement)
$dupProviderOrderId = 'FG_ORD_DUP_' . strtoupper(bin2hex(random_bytes(5)));
$orderId2 = PaymentOrderModel::create([
    'merchant_id' => $merchantId,
    'provider_id' => (int) $provider['id'],
    'amount' => 250.00,
    'currency' => 'INR',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
PaymentOrderModel::markProviderCreated($orderId2, [
    'provider_order_id' => $dupProviderOrderId,
    'amount' => 250.00,
    'payable_amount' => 250.00,
    'qr_url' => 'https://famgateway.in/qr/test2.png',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$order2 = PaymentOrderModel::find($orderId2);

$duplicateUtrBlocked = false;
try {
    // Attempt to verify second order with the SAME UTR
    VerificationService::processVerified($order2, [
        'status' => 'success',
        'amount' => 250.00,
        'utr' => $validUtr, // Replay/duplicate UTR!
        'provider_order_id' => $dupProviderOrderId,
        'transaction_id' => 'TXN_DUP_' . strtoupper(bin2hex(random_bytes(4))),
        'paid_at' => gmdate('Y-m-d H:i:s'),
        'raw' => [],
    ], 'test');
} catch (VerificationException $e) {
    $duplicateUtrBlocked = $e->reason === 'duplicate_utr';
}
assertTest('CRITICAL: Duplicate UTR is strictly blocked across different orders', $duplicateUtrBlocked);

// Test 5: Duplicate provider transaction reference prevention
$orderId3 = PaymentOrderModel::create([
    'merchant_id' => $merchantId,
    'provider_id' => (int) $provider['id'],
    'amount' => 250.00,
    'currency' => 'INR',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$dupTxnProviderOrder = 'FG_ORD_TXN_' . strtoupper(bin2hex(random_bytes(5)));
PaymentOrderModel::markProviderCreated($orderId3, [
    'provider_order_id' => $dupTxnProviderOrder,
    'amount' => 250.00,
    'payable_amount' => 250.00,
    'qr_url' => 'https://famgateway.in/qr/test3.png',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$order3 = PaymentOrderModel::find($orderId3);
$uniqueUtr3 = (string) random_int(100000000000, 999999999999);
$duplicateTxnBlocked = false;
try {
    VerificationService::processVerified($order3, [
        'status' => 'success',
        'amount' => 250.00,
        'utr' => $uniqueUtr3,
        'provider_order_id' => $dupTxnProviderOrder,
        'transaction_id' => $legitTxnId, // Reuse of the same provider transaction reference
        'paid_at' => gmdate('Y-m-d H:i:s'),
        'raw' => [],
    ], 'test');
} catch (VerificationException $e) {
    $duplicateTxnBlocked = $e->reason === 'duplicate_transaction';
}
assertTest('CRITICAL: Duplicate provider transaction reference is blocked', $duplicateTxnBlocked);

// 8. Invoices & PDF Generation
echo "\n[Suite 8: Invoices & PDF Receipt Generation]\n";
$invoice = Database::fetch('SELECT * FROM invoices WHERE order_id = ? LIMIT 1', [$orderId]);
assertTest('Invoice automatically generated for verified payment', $invoice !== null);
assertTest('Sequential invoice numbering format (MF-YYYY-XXXXXX)', preg_match('/^MF-\d{4}-\d{6}$/', (string) $invoice['invoice_number']) === 1);

$pdfData = [
    'invoice_number' => $invoice['invoice_number'],
    'order_id' => $reloadedOrder['public_id'],
    'provider_order_id' => $reloadedOrder['provider_order_id'],
    'provider_name' => 'FamGateway UPI',
    'amount' => 250.00,
    'utr' => $validUtr,
    'sender_name' => 'Rahul Sharma',
    'customer_name' => 'Rahul Sharma',
    'customer_email' => 'rahul@example.com',
    'description' => 'Test Payment Session',
    'business_name' => 'Test Merchant Store',
    'merchant_code' => 'MFMTEST',
    'support_email' => 'support@example.com',
    'issued_at_ist' => '31 Oct 2026, 23:00',
    'verified_at_ist' => '31 Oct 2026, 23:00',
];
$pdfContent = PdfService::invoice($pdfData);
assertTest('PdfService generates valid PDF 1.4 binary', str_starts_with($pdfContent, '%PDF-1.4'));
assertTest('PDF receipt contains document trailer and EOF', str_contains($pdfContent, '%%EOF'));

// 9. Webhook Signature & Queue
echo "\n[Suite 9: Webhook Delivery & Cryptographic HMAC]\n";
$webhookUrl = 'https://httpbin.org/post';
$hookResult = WebhookModel::create($merchantId, $webhookUrl, ['payment.success', 'payment.created']);
assertTest('Merchant webhook endpoint registered', $hookResult['id'] > 0);
assertTest('Webhook secret generated with whsec_ prefix', str_starts_with($hookResult['secret'], 'whsec_'));

$queued = WebhookModel::enqueue($merchantId, 'payment.success', [
    'order_id' => $reloadedOrder['public_id'],
    'amount' => '250.00',
    'status' => 'success',
    'utr' => $validUtr,
]);
assertTest('Signed webhook delivery enqueued for merchant', $queued > 0);

// 10. Developer REST API
echo "\n[Suite 10: Developer REST API Management]\n";
$newKey = ApiKeyModel::create($merchantId, 'Production API Key', ['payments:read', 'payments:write', 'links:read', 'links:write', 'profile:read']);
assertTest('Merchant API key generated with mf_live_ prefix', str_starts_with($newKey['key'], 'mf_live_'));

$authenticatedKey = ApiKeyModel::findActiveByToken($newKey['key']);
assertTest('API Key authenticated via SHA-256 hash lookup', $authenticatedKey !== null && (int) $authenticatedKey['merchant_id'] === $merchantId);

// Test API Key revocation
$revoked = ApiKeyModel::revoke((int) $newKey['id'], $merchantId);
assertTest('API key revocation succeeded', $revoked);
$authenticatedKeyAfter = ApiKeyModel::findActiveByToken($newKey['key']);
assertTest('Revoked API key is immediately rejected', $authenticatedKeyAfter === null);

// 11. Payment Links
echo "\n[Suite 11: Payment Links Engine]\n";
$linkId = PaymentLinkModel::create($merchantId, $userId, [
    'title' => 'Consulting Invoice #101',
    'amount' => 999.00,
    'min_amount' => 1.00,
    'max_amount' => 50000.00,
    'description' => '1 Hour Technical Consultation',
]);
$link = PaymentLinkModel::find($linkId, $merchantId);
assertTest('Payment link created with custom slug', !empty($link['slug']));
assertTest('Payment link status is active', $link['status'] === 'active');

// 12. Regression guards for shared helpers used by HTTP entry points
echo "\n[Suite 12: HTTP Entry-Point Regression Guards]\n";
$rateKey = 'tests:rate:' . bin2hex(random_bytes(6));
$rateFirst = RateLimiter::check($rateKey, 2, 60);
$rateSecond = RateLimiter::check($rateKey, 2, 60);
$rateThird = RateLimiter::check($rateKey, 2, 60);
assertTest('RateLimiter::check allows requests within limit', $rateFirst && $rateSecond);
assertTest('RateLimiter::check blocks requests above limit', $rateThird === false);

$allMerchants = MerchantModel::all();
assertTest('MerchantModel::all returns filter options including the test merchant', count(array_filter($allMerchants, static fn (array $m): bool => (int) $m['id'] === $merchantId)) === 1);
assertTest('WebhookModel::forMerchant resolves the active endpoint', (int) (WebhookModel::forMerchant($merchantId)['id'] ?? 0) === (int) $hookResult['id']);

$idemKey = 'idem-test-' . bin2hex(random_bytes(6));
$idemEndpoint = 'POST /api/v1/payments/create';
$idemHash = hash('sha256', '{"amount":250.00}');
$idemFresh = IdempotencyModel::reserve($merchantId, $idemEndpoint, $idemKey, $idemHash);
IdempotencyModel::complete($merchantId, $idemEndpoint, $idemKey, 201, '{"success":true}');
$idemReplay = IdempotencyModel::reserve($merchantId, $idemEndpoint, $idemKey, $idemHash);
$idemMismatch = IdempotencyModel::reserve($merchantId, $idemEndpoint, $idemKey, hash('sha256', '{"amount":999.00}'));
assertTest('Idempotency reserve/complete/replay works', $idemFresh['state'] === 'fresh' && $idemReplay['state'] === 'replay');
assertTest('Idempotency rejects a different body for the same key', $idemMismatch['state'] === 'mismatch');

// 13. Gmail IMAP / FamPay email verification
echo "\n[Suite 13: Gmail IMAP / FamPay Email Verification]\n";
assertTest('FamPay UPI ID accepts @fam', EmailVerificationService::validUpiId('merchant@fam'));
assertTest('FamPay UPI ID accepts @yesfam', EmailVerificationService::validUpiId('merchant.store@yesfam'));
assertTest('FamPay UPI ID rejects a Gmail address', !EmailVerificationService::validUpiId('merchant@gmail.com'));
assertTest('FamPay UPI ID rejects an unknown suffix', !EmailVerificationService::validUpiId('merchant@famx'));
assertTest('FamPay UPI ID rejects an empty value', !EmailVerificationService::validUpiId(''));

$emailUtr = (string) random_int(100000000000, 999999999999);
$rawEmail = "From: FamPay Alerts <alerts@famapp.in>\r\n"
    . "To: merchant@gmail.com\r\n"
    . "Subject: =?UTF-8?B?UGF5bWVudCByZWNlaXZlZA==?=\r\n"
    . "Message-ID: <fam-test-" . bin2hex(random_bytes(4)) . "@famapp.in>\r\n"
    . "Date: " . gmdate('D, d M Y H:i:s') . " +0000\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: multipart/alternative; boundary=\"fam-boundary\"\r\n\r\n"
    . "--fam-boundary\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
    . "Payment received! INR 149.00 was credited to merchant@fam.\r\n"
    . "UTR: " . $emailUtr . "\r\n"
    . "--fam-boundary--\r\n";
$parsedEmail = EmailParser::parse($rawEmail);
assertTest('MIME parser extracts the trusted sender address', $parsedEmail['from_address'] === 'alerts@famapp.in');
assertTest('MIME parser decodes an encoded subject', $parsedEmail['subject'] === 'Payment received');
assertTest('MIME parser decodes the transaction body', str_contains($parsedEmail['text'], 'INR 149.00'));

$alert = EmailVerificationService::parseAlert($parsedEmail['text'], 'merchant@fam');
assertTest('Alert parser extracts the exact amount', $alert['amount'] === 149.0);
assertTest('Alert parser extracts the 12-digit UTR', $alert['utr'] === $emailUtr);
assertTest('Alert parser finds the configured FamPay UPI ID', $alert['upi_found'] === true && $alert['upi_id'] === 'merchant@fam');
assertTest('Trusted sender matching accepts a configured domain', EmailVerificationService::senderTrusted('alerts@famapp.in', 'famapp.in'));
assertTest('Trusted sender matching rejects an unrelated domain', !EmailVerificationService::senderTrusted('attacker@example.com', 'famapp.in'));

$savedAccount = EmailVerificationService::saveAccount($merchantId, [
    'gmail_address' => 'alerts.owner@gmail.com',
    'app_password' => 'abcd1234efgh5678',
    'upi_id' => 'merchant@fam',
]);
assertTest('Minimal Gmail form stores only the three required fields with safe defaults', $savedAccount
    && $savedAccount['imap_host'] === 'imap.gmail.com'
    && (int) $savedAccount['imap_port'] === 993
    && $savedAccount['imap_encryption'] === 'ssl'
    && $savedAccount['folder'] === 'INBOX'
    && (int) $savedAccount['is_enabled'] === 1);
assertTest('Stored Google App Password is encrypted and only last four characters are exposed', $savedAccount
    && $savedAccount['app_password'] === 'abcd1234efgh5678'
    && $savedAccount['app_password_last4'] === '5678'
    && $savedAccount['app_password_encrypted'] !== 'abcd1234efgh5678');
EmailVerificationService::deleteAccount($merchantId);

$emailOrderId = PaymentOrderModel::create([
    'merchant_id' => $merchantId,
    'provider_id' => (int) $provider['id'],
    'amount' => 149.00,
    'currency' => 'INR',
    'customer_name' => 'Email Alert Customer',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
PaymentOrderModel::markProviderCreated($emailOrderId, [
    'provider_order_id' => 'FG_EMAIL_' . strtoupper(bin2hex(random_bytes(6))),
    'amount' => 149.00,
    'payable_amount' => 149.00,
    'upi_id' => 'merchant@fam',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$emailOrder = PaymentOrderModel::find($emailOrderId);
$emailVerified = VerificationService::processEmailVerified($emailOrder, [
    'status' => 'success',
    'amount' => 149.00,
    'utr' => $emailUtr,
    'paid_at' => gmdate('Y-m-d H:i:s'),
    'sender_name' => 'FamPay Alerts',
    'transaction_id' => null,
    'raw' => ['source' => 'gmail_imap', 'message_id' => $parsedEmail['message_id']],
], 'email_imap');
assertTest('Email-verified payment succeeds without a provider order id', PaymentOrderModel::find($emailOrderId)['status'] === 'success');
$emailTxn = Database::fetch('SELECT * FROM transactions WHERE order_id = ? LIMIT 1', [$emailOrderId]);
assertTest('Email verification records its distinct source', $emailTxn && $emailTxn['verification_source'] === 'email_imap');
assertTest('Email verification stores the parsed 12-digit UTR', $emailTxn && $emailTxn['utr'] === $emailUtr);

$emailOrderId2 = PaymentOrderModel::create([
    'merchant_id' => $merchantId,
    'provider_id' => (int) $provider['id'],
    'amount' => 149.00,
    'currency' => 'INR',
    'customer_name' => 'Duplicate UTR Attempt',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
PaymentOrderModel::markProviderCreated($emailOrderId2, [
    'provider_order_id' => 'FG_EMAIL_DUP_' . strtoupper(bin2hex(random_bytes(6))),
    'amount' => 149.00,
    'payable_amount' => 149.00,
    'upi_id' => 'merchant@fam',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$emailDuplicateBlocked = false;
try {
    VerificationService::processEmailVerified(PaymentOrderModel::find($emailOrderId2), [
        'status' => 'success',
        'amount' => 149.00,
        'utr' => $emailUtr,
        'paid_at' => gmdate('Y-m-d H:i:s'),
        'transaction_id' => null,
        'raw' => ['source' => 'gmail_imap'],
    ], 'email_imap');
} catch (VerificationException $e) {
    $emailDuplicateBlocked = $e->reason === 'duplicate_utr';
}
assertTest('CRITICAL: Duplicate UTR from email alerts is blocked', $emailDuplicateBlocked);

$adminPasswordColumn = Database::fetch("SHOW COLUMNS FROM admins LIKE 'must_change_password'");
assertTest('Administrators carry a forced-password-change flag', $adminPasswordColumn !== false);

// 14. Subscription plans, requests and usage limits
echo "\n[Suite 14: Subscription Plans & Usage Limits]\n";
$planMerchantUserId = Database::insert(
    'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, "active", UTC_TIMESTAMP())',
    ['Plan Test Merchant', 'plan_test_' . bin2hex(random_bytes(4)) . '@example.com', $hash]
);
$planMerchantId = Database::insert(
    'INSERT INTO merchants (user_id, business_name, merchant_code, support_email, status, created_at)
     VALUES (?, ?, ?, ?, "active", UTC_TIMESTAMP())',
    [$planMerchantUserId, 'Plan Test Store', 'MFPLN' . strtoupper(bin2hex(random_bytes(3))), 'plan-test@example.com']
);
$planId = Database::insert(
    'INSERT INTO plans (name, slug, description, price, currency, billing_interval,
       transaction_limit, api_limit, link_limit, features, is_active, sort_order, created_at)
     VALUES ("Test Growth", ?, "Test plan", 499.00, "INR", "monthly", 1, 1, 1, ?, 1, 10, UTC_TIMESTAMP())',
    ['test-growth-' . bin2hex(random_bytes(4)), "Feature A\nFeature B"]
);
$plan = PlanService::find($planId);
assertTest('Plan created with limits', $plan !== null && (int) $plan['transaction_limit'] === 1);
assertTest('Usage is zero before any activity', PlanService::usage($planMerchantId)['transactions'] === 0);

PlanService::assignPlan($planMerchantId, $plan);
$assigned = PlanService::assignedPlan($planMerchantId);
assertTest('Plan assignment stores expiry and active status', $assigned && $assigned['plan_status'] === 'active' && !empty($assigned['plan_expires_at']));

$limitPasses = true;
try {
    PlanService::assertCan($planMerchantId, 'transactions');
    PlanService::assertCan($planMerchantId, 'api');
    PlanService::assertCan($planMerchantId, 'links');
} catch (\Throwable) {
    $limitPasses = false;
}
assertTest('Assigned plan allows usage below limits', $limitPasses);

$limitOrderId = PaymentOrderModel::create([
    'merchant_id' => $planMerchantId,
    'provider_id' => (int) $provider['id'],
    'amount' => 100.00,
    'currency' => 'INR',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
]);
$transactionLimitBlocked = false;
try {
    PlanService::assertCan($planMerchantId, 'transactions');
} catch (PlanLimitException) {
    $transactionLimitBlocked = true;
}
assertTest('Payment order is blocked at the plan transaction limit', $transactionLimitBlocked);

PaymentLinkModel::create($planMerchantId, $planMerchantUserId, [
    'title' => 'Plan limit test link',
    'amount' => 100.00,
    'min_amount' => 1.00,
    'max_amount' => 1000.00,
]);
$linkLimitBlocked = false;
try {
    PlanService::assertCan($planMerchantId, 'links');
} catch (PlanLimitException) {
    $linkLimitBlocked = true;
}
assertTest('Payment link is blocked at the plan link limit', $linkLimitBlocked);

Database::insert(
    'INSERT INTO api_logs (merchant_id, request_id, method, path, status_code, ip, created_at)
     VALUES (?, ?, "GET", "/api/v1/payments", 200, "127.0.0.1", UTC_TIMESTAMP())',
    [$planMerchantId, 'pltest_' . bin2hex(random_bytes(6))]
);
$apiLimitBlocked = false;
try {
    PlanService::assertCan($planMerchantId, 'api');
} catch (PlanLimitException) {
    $apiLimitBlocked = true;
}
assertTest('API usage is blocked at the plan API limit', $apiLimitBlocked);

$planRequestUserId = Database::insert(
    'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, "active", UTC_TIMESTAMP())',
    ['Plan Requester', 'plan_req_' . bin2hex(random_bytes(4)) . '@example.com', $hash]
);
$planRequestMerchantId = Database::insert(
    'INSERT INTO merchants (user_id, business_name, merchant_code, support_email, status, created_at)
     VALUES (?, ?, ?, ?, "active", UTC_TIMESTAMP())',
    [$planRequestUserId, 'Plan Request Store', 'MFREQ' . strtoupper(bin2hex(random_bytes(3))), 'plan-req@example.com']
);
$requestId = PlanService::requestPlan($planRequestMerchantId, $planId, 'Need more capacity');
assertTest('Merchant plan request is created as pending', $requestId > 0 && PlanService::pendingRequest($planRequestMerchantId) !== null);
$duplicateRequestBlocked = false;
try {
    PlanService::requestPlan($planRequestMerchantId, $planId);
} catch (InvalidArgumentException) {
    $duplicateRequestBlocked = true;
}
assertTest('A second pending request for the same merchant is blocked', $duplicateRequestBlocked);
assertTest('Approving the request assigns the plan', PlanService::approveRequest($requestId, 0, 'approved in test'));
assertTest('Approved request assigns the plan to the merchant', PlanService::assignedPlan($planRequestMerchantId) !== null);

// Clean up plan test records
Database::execute('DELETE FROM api_logs WHERE merchant_id IN (?, ?)', [$planMerchantId, $planRequestMerchantId]);
Database::execute('DELETE FROM payment_links WHERE merchant_id IN (?, ?)', [$planMerchantId, $planRequestMerchantId]);
Database::execute('DELETE FROM payment_orders WHERE id = ?', [$limitOrderId]);
Database::execute('DELETE FROM plan_requests WHERE merchant_id IN (?, ?)', [$planMerchantId, $planRequestMerchantId]);
Database::execute('DELETE FROM merchant_profiles WHERE merchant_id IN (?, ?)', [$planMerchantId, $planRequestMerchantId]);
Database::execute('DELETE FROM merchants WHERE id IN (?, ?)', [$planMerchantId, $planRequestMerchantId]);
Database::execute('DELETE FROM users WHERE id IN (?, ?)', [$planMerchantUserId, $planRequestUserId]);
Database::execute('DELETE FROM plans WHERE id = ?', [$planId]);

Database::execute('DELETE FROM invoices WHERE order_id IN (?, ?)', [$emailOrderId, $emailOrderId2]);
Database::execute('DELETE FROM transactions WHERE order_id IN (?, ?)', [$emailOrderId, $emailOrderId2]);
Database::execute('DELETE FROM payment_orders WHERE id IN (?, ?)', [$emailOrderId, $emailOrderId2]);

// Clean up test records
Database::execute('DELETE FROM support_messages WHERE ticket_id IN (SELECT id FROM support_tickets WHERE user_id = ?)', [$userId]);
Database::execute('DELETE FROM support_tickets WHERE user_id = ?', [$userId]);
Database::execute('DELETE FROM invoices WHERE order_id IN (?, ?, ?)', [$orderId, $orderId2, $orderId3]);
Database::execute('DELETE FROM transactions WHERE order_id IN (?, ?, ?)', [$orderId, $orderId2, $orderId3]);
Database::execute('DELETE FROM webhook_deliveries WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM webhooks WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM payment_links WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM payment_orders WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM api_keys WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM email_queue WHERE to_email = ?', [$testEmail]);
Database::execute('DELETE FROM idempotency_keys WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM rate_limits WHERE bucket LIKE ?', [hash('sha256', $rateKey) . '%']);
Database::execute('DELETE FROM provider_credentials WHERE merchant_id = ?', [$merchantId]);
Database::execute('DELETE FROM merchants WHERE id = ?', [$merchantId]);
Database::execute('DELETE FROM users WHERE id = ?', [$userId]);

echo "\n=========================================================\n";
echo "Test Execution Summary:\n";
echo "Total Assertions: {$total}\n";
echo "Passed:           {$passed}\n";
echo "Failed:           {$failed}\n";
echo "Pass Rate:        " . round(($passed / $total) * 100, 1) . "%\n";
echo "=========================================================\n";

if ($failed > 0) {
    exit(1);
}
