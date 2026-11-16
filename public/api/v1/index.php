<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Models\ApiKeyModel;
use App\Models\IdempotencyModel;
use App\Models\PaymentLinkModel;
use App\Models\PaymentOrderModel;
use App\Models\WebhookModel;
use App\Providers\IntegrationNotConfiguredException;
use App\Providers\ProviderException;
use App\Services\PaymentService;
use App\Services\PlanLimitException;
use App\Services\PlanService;
use App\Services\VerificationService;
use App\Services\WebhookDispatcher;

$startTime = microtime(true);
$requestId = 'req_' . bin2hex(random_bytes(16));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

// Normalize path relative to /api/v1
$relPath = preg_replace('#^.*?/api/v1#', '', $path);
$relPath = '/' . ltrim((string) $relPath, '/');

header('X-Request-Id: ' . $requestId);
header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input') ?: '';
$inputData = [];
if ($rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}

// Redact helper for logging
$redact = static function (mixed $data) use (&$redact): mixed {
    if (!is_array($data)) {
        return $data;
    }
    $sensitive = ['api_key', 'key', 'password', 'secret', 'token', 'authorization', 'card', 'pin'];
    $clean = [];
    foreach ($data as $k => $v) {
        if (in_array(strtolower((string) $k), $sensitive, true)) {
            $clean[$k] = '••••••••';
        } elseif (is_array($v)) {
            $clean[$k] = $redact($v);
        } else {
            $clean[$k] = $v;
        }
    }
    return $clean;
};

// Response sender with audit logging
$sendResponse = static function (int $status, array $payload, ?int $merchantId = null, ?int $apiKeyId = null) use (
    $startTime, $requestId, $method, $path, $inputData, $redact
): void {
    $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
    $reqBodyLog = !empty($inputData) ? json_encode($redact($inputData), JSON_UNESCAPED_SLASHES) : null;
    $resBodyLog = json_encode($redact($payload), JSON_UNESCAPED_SLASHES);
    $errorMsg = !$payload['success'] ? ($payload['error']['message'] ?? 'API error') : null;

    try {
        Database::insert(
            'INSERT INTO api_logs (merchant_id, api_key_id, request_id, method, path, status_code, ip, user_agent, request_body, response_body, latency_ms, error, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $merchantId,
                $apiKeyId,
                $requestId,
                $method,
                $path,
                $status,
                Request::ip(),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                $reqBodyLog,
                $resBodyLog,
                $latencyMs,
                $errorMsg ? mb_substr((string) $errorMsg, 0, 500) : null,
            ]
        );
    } catch (\Throwable $e) {
        // Log failure should not break response delivery
    }

    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
};

// 1. Authenticate API Key via Bearer Token
$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/Bearer\s+(mf_live_[A-Za-z0-9_\-]+)/i', $authHeader, $authMatch)) {
    $sendResponse(401, [
        'success' => false,
        'error' => [
            'code' => 'unauthorized',
            'message' => 'Missing or invalid Bearer authentication. Provide your MAXXfam API key in the Authorization header (Authorization: Bearer mf_live_...).',
        ],
    ]);
}

$token = $authMatch[1];
$apiKey = ApiKeyModel::findActiveByToken($token);
if (!$apiKey) {
    $sendResponse(401, [
        'success' => false,
        'error' => [
            'code' => 'invalid_api_key',
            'message' => 'The provided API key is invalid, expired, or revoked.',
        ],
    ]);
}

$merchantId = (int) $apiKey['merchant_id'];
$apiKeyId = (int) $apiKey['id'];
$scopes = $apiKey['scopes_array'] ?? [];

// 2. Rate Limiting (configurable, default 60 requests per minute per key)
$rateLimit = max(1, (int) Settings::get('api_rate_limit_per_minute', 60));
$rateKey = 'api_key:' . $apiKeyId;
if (!RateLimiter::check($rateKey, $rateLimit, 60)) {
    $sendResponse(429, [
        'success' => false,
        'error' => [
            'code' => 'rate_limit_exceeded',
            'message' => "Too many requests. Limit is {$rateLimit} requests per minute.",
        ],
    ], $merchantId, $apiKeyId);
}

// 2b. Subscription plan API limit (monthly).
try {
    PlanService::assertCan($merchantId, 'api');
} catch (PlanLimitException $e) {
    $sendResponse(429, [
        'success' => false,
        'error' => [
            'code' => 'plan_limit_exceeded',
            'message' => $e->getMessage(),
        ],
    ], $merchantId, $apiKeyId);
}

// Scope check helper
$requireScope = static function (string $scope) use ($scopes, $sendResponse, $merchantId, $apiKeyId): void {
    if (!in_array($scope, $scopes, true)) {
        $sendResponse(403, [
            'success' => false,
            'error' => [
                'code' => 'insufficient_scope',
                'message' => "Your API key does not have the '{$scope}' scope.",
            ],
        ], $merchantId, $apiKeyId);
    }
};

$idemContext = null;

try {
    // -------------------------------------------------------------
    // Endpoint: POST /api/v1/payments/create
    // -------------------------------------------------------------
    if ($method === 'POST' && ($relPath === '/payments/create' || $relPath === '/payments')) {
        $requireScope('payments:write');

        // Check Idempotency Key
        $idempotencyKey = (string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '');
        if ($idempotencyKey !== '') {
            if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 200) {
                $sendResponse(422, [
                    'success' => false,
                    'error' => ['code' => 'invalid_idempotency_key', 'message' => 'Idempotency-Key must be between 8 and 200 characters.'],
                ], $merchantId, $apiKeyId);
            }
            $idemEndpoint = $method . ' ' . $relPath;
            $check = IdempotencyModel::reserve($merchantId, $idemEndpoint, $idempotencyKey, hash('sha256', $rawInput));
            if ($check['state'] === 'replay') {
                $cachedBody = json_decode((string) $check['record']['response_body'], true) ?: [];
                header('Idempotent-Replayed: true');
                $sendResponse((int) $check['record']['status_code'], $cachedBody, $merchantId, $apiKeyId);
            }
            if ($check['state'] === 'mismatch') {
                $sendResponse(422, [
                    'success' => false,
                    'error' => ['code' => 'idempotency_key_reuse', 'message' => 'This Idempotency-Key was already used with a different request body.'],
                ], $merchantId, $apiKeyId);
            }
            if ($check['state'] === 'in_progress') {
                $sendResponse(409, [
                    'success' => false,
                    'error' => ['code' => 'request_in_progress', 'message' => 'A request with this Idempotency-Key is already being processed.'],
                ], $merchantId, $apiKeyId);
            }
            $idemContext = ['merchant' => $merchantId, 'endpoint' => $idemEndpoint, 'key' => $idempotencyKey];
        }

        $amount = isset($inputData['amount']) ? round((float) $inputData['amount'], 2) : 0.0;
        if ($amount <= 0) {
            $sendResponse(422, [
                'success' => false,
                'error' => ['code' => 'invalid_amount', 'message' => 'A valid positive amount in INR is required.'],
            ], $merchantId, $apiKeyId);
        }

        $options = [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'customer_name' => !empty($inputData['customer_name']) ? (string) $inputData['customer_name'] : null,
            'customer_email' => !empty($inputData['customer_email']) ? strtolower((string) $inputData['customer_email']) : null,
            'customer_phone' => !empty($inputData['customer_phone']) ? (string) $inputData['customer_phone'] : null,
            'customer_reference' => !empty($inputData['customer_reference']) ? (string) $inputData['customer_reference'] : null,
            'description' => !empty($inputData['description']) ? (string) $inputData['description'] : null,
            'success_redirect_url' => !empty($inputData['success_redirect_url']) ? (string) $inputData['success_redirect_url'] : null,
            'webhook_url' => !empty($inputData['webhook_url']) ? (string) $inputData['webhook_url'] : null,
            'provider_code' => !empty($inputData['provider_code']) ? (string) $inputData['provider_code'] : 'famgateway',
            'source' => 'api',
        ];

        $result = PaymentService::create($options);
        $order = $result['order'];

        $resData = [
            'success' => true,
            'data' => [
                'order_id' => $order['public_id'],
                'amount' => number_format((float) $order['amount'], 2, '.', ''),
                'payable_amount' => number_format((float) ($order['payable_amount'] ?? $order['amount']), 2, '.', ''),
                'currency' => $order['currency'] ?? 'INR',
                'status' => $order['status'],
                'checkout_url' => $order['checkout_url'],
                'qr_url' => $order['qr_url'] ?? null,
                'upi_intent' => $order['upi_intent'] ?? null,
                'upi_id' => $order['upi_id'] ?? null,
                'customer_reference' => $order['customer_reference'],
                'expires_at' => !empty($order['expires_at']) ? gmdate('c', strtotime($order['expires_at'] . ' UTC')) : null,
                'created_at' => !empty($order['created_at']) ? gmdate('c', strtotime($order['created_at'] . ' UTC')) : gmdate('c'),
            ],
        ];

        if ($idempotencyKey !== '') {
            IdempotencyModel::complete($merchantId, $method . ' ' . $relPath, $idempotencyKey, 201, json_encode($resData, JSON_UNESCAPED_SLASHES) ?: '{}');
            $idemContext = null;
        }

        $sendResponse(201, $resData, $merchantId, $apiKeyId);
    }

    // -------------------------------------------------------------
    // Endpoint: GET /api/v1/payments/{order_id}
    // -------------------------------------------------------------
    if ($method === 'GET' && preg_match('#^/payments/([A-Za-z0-9_\-]+)$#', $relPath, $m)) {
        $requireScope('payments:read');
        $orderPublicId = $m[1];

        $order = PaymentOrderModel::findByPublicId($orderPublicId, $merchantId);
        if (!$order) {
            $sendResponse(404, [
                'success' => false,
                'error' => ['code' => 'order_not_found', 'message' => "Order '{$orderPublicId}' not found."],
            ], $merchantId, $apiKeyId);
        }

        // If pending, recheck with provider
        if ($order['status'] === 'pending') {
            $order = VerificationService::verify($order, 'api');
            $fresh = PaymentOrderModel::find((int) $order['id']);
            if ($fresh) {
                $order = $fresh;
            }
        }

        $transaction = null;
        if ($order['status'] === 'success') {
            $transaction = Database::fetch('SELECT utr, verified_at FROM transactions WHERE order_id = ? LIMIT 1', [$order['id']]);
        }

        $sendResponse(200, [
            'success' => true,
            'data' => [
                'order_id' => $order['public_id'],
                'amount' => number_format((float) $order['amount'], 2, '.', ''),
                'payable_amount' => number_format((float) ($order['payable_amount'] ?? $order['amount']), 2, '.', ''),
                'currency' => $order['currency'] ?? 'INR',
                'status' => $order['status'],
                'checkout_url' => $order['checkout_url'],
                'qr_url' => $order['qr_url'] ?? null,
                'upi_intent' => $order['upi_intent'] ?? null,
                'customer_reference' => $order['customer_reference'],
                'customer_name' => $order['customer_name'],
                'customer_email' => $order['customer_email'],
                'utr' => $transaction['utr'] ?? null,
                'verified_at' => !empty($transaction['verified_at']) ? gmdate('c', strtotime($transaction['verified_at'] . ' UTC')) : null,
                'receipt_url' => $order['status'] === 'success' ? url('/receipt.php?token=' . urlencode((string) $order['checkout_token'])) : null,
                'failure_message' => $order['failure_message'] ?? null,
                'expires_at' => !empty($order['expires_at']) ? gmdate('c', strtotime($order['expires_at'] . ' UTC')) : null,
                'created_at' => !empty($order['created_at']) ? gmdate('c', strtotime($order['created_at'] . ' UTC')) : null,
            ],
        ], $merchantId, $apiKeyId);
    }

    // -------------------------------------------------------------
    // Endpoint: GET /api/v1/payments
    // -------------------------------------------------------------
    if ($method === 'GET' && ($relPath === '/payments' || $relPath === '/payments/')) {
        $requireScope('payments:read');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $filters = [
            'status' => $_GET['status'] ?? '',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
        ];

        $list = PaymentOrderModel::listForMerchant($merchantId, $filters, $page, $perPage);
        $formatted = array_map(static fn (array $row) => [
            'order_id' => $row['public_id'],
            'amount' => number_format((float) $row['amount'], 2, '.', ''),
            'payable_amount' => number_format((float) ($row['payable_amount'] ?? $row['amount']), 2, '.', ''),
            'currency' => $row['currency'],
            'status' => $row['status'],
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'customer_reference' => $row['customer_reference'],
            'provider' => $row['provider_name'],
            'created_at' => gmdate('c', strtotime($row['created_at'] . ' UTC')),
            'paid_at' => !empty($row['paid_at']) ? gmdate('c', strtotime($row['paid_at'] . ' UTC')) : null,
        ], $list['rows']);

        $sendResponse(200, [
            'success' => true,
            'data' => $formatted,
            'pagination' => [
                'total' => $list['total'],
                'page' => $list['page'],
                'per_page' => $list['per_page'],
                'pages' => $list['pages'],
            ],
        ], $merchantId, $apiKeyId);
    }

    // -------------------------------------------------------------
    // Endpoint: POST /api/v1/payment-links/create
    // -------------------------------------------------------------
    if ($method === 'POST' && ($relPath === '/payment-links/create' || $relPath === '/payment-links')) {
        $requireScope('links:write');

        $title = trim((string) ($inputData['title'] ?? ''));
        if ($title === '' || mb_strlen($title) < 2) {
            $sendResponse(422, [
                'success' => false,
                'error' => ['code' => 'invalid_title', 'message' => 'A descriptive title between 2 and 190 characters is required.'],
            ], $merchantId, $apiKeyId);
        }

        $amount = isset($inputData['amount']) && $inputData['amount'] !== null && $inputData['amount'] !== '' ? round((float) $inputData['amount'], 2) : null;
        $minAmount = isset($inputData['min_amount']) ? round((float) $inputData['min_amount'], 2) : 1.0;
        $maxAmount = isset($inputData['max_amount']) ? round((float) $inputData['max_amount'], 2) : 100000.0;

        $expiresAt = null;
        if (!empty($inputData['expires_in_days'])) {
            $expiresAt = gmdate('Y-m-d H:i:s', time() + ((int) $inputData['expires_in_days'] * 86400));
        }

        $merchantRow = Database::fetch('SELECT user_id FROM merchants WHERE id = ? LIMIT 1', [$merchantId]);
        $creatorUserId = (int) ($merchantRow['user_id'] ?? 1);

        $linkId = PaymentLinkModel::create($merchantId, $creatorUserId, [
            'title' => $title,
            'description' => !empty($inputData['description']) ? (string) $inputData['description'] : null,
            'amount' => $amount,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'customer_reference' => !empty($inputData['customer_reference']) ? (string) $inputData['customer_reference'] : null,
            'success_redirect_url' => !empty($inputData['success_redirect_url']) ? (string) $inputData['success_redirect_url'] : null,
            'expires_at' => $expiresAt,
        ]);

        $link = PaymentLinkModel::find($linkId, $merchantId);

        $sendResponse(201, [
            'success' => true,
            'data' => [
                'link_id' => $link['id'],
                'slug' => $link['slug'],
                'url' => url('/pay/' . $link['slug']),
                'title' => $link['title'],
                'amount' => $link['amount'] !== null ? number_format((float) $link['amount'], 2, '.', '') : null,
                'min_amount' => number_format((float) $link['min_amount'], 2, '.', ''),
                'max_amount' => number_format((float) $link['max_amount'], 2, '.', ''),
                'status' => $link['status'],
                'expires_at' => !empty($link['expires_at']) ? gmdate('c', strtotime($link['expires_at'] . ' UTC')) : null,
                'created_at' => gmdate('c', strtotime($link['created_at'] . ' UTC')),
            ],
        ], $merchantId, $apiKeyId);
    }

    // -------------------------------------------------------------
    // Endpoint: GET /api/v1/payment-links
    // -------------------------------------------------------------
    if ($method === 'GET' && ($relPath === '/payment-links' || $relPath === '/payment-links/')) {
        $requireScope('links:read');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));

        $list = PaymentLinkModel::listForMerchant($merchantId, [], $page, $perPage);
        $formatted = array_map(static fn (array $row) => [
            'link_id' => $row['id'],
            'slug' => $row['slug'],
            'url' => url('/pay/' . $row['slug']),
            'title' => $row['title'],
            'amount' => $row['amount'] !== null ? number_format((float) $row['amount'], 2, '.', '') : null,
            'status' => $row['status'],
            'total_payments' => (int) ($row['total_payments'] ?? 0),
            'total_volume' => number_format((float) ($row['total_volume'] ?? 0), 2, '.', ''),
            'created_at' => gmdate('c', strtotime($row['created_at'] . ' UTC')),
        ], $list['rows']);

        $sendResponse(200, [
            'success' => true,
            'data' => $formatted,
            'pagination' => [
                'total' => $list['total'],
                'page' => $list['page'],
                'per_page' => $list['per_page'],
                'pages' => $list['pages'],
            ],
        ], $merchantId, $apiKeyId);
    }

    // -------------------------------------------------------------
    // Endpoint: POST /api/v1/webhooks/test
    // -------------------------------------------------------------
    if ($method === 'POST' && $relPath === '/webhooks/test') {
        $requireScope('webhooks:test');

        $requestedHookId = (int) ($inputData['webhook_id'] ?? 0);
        if ($requestedHookId > 0) {
            $webhook = WebhookModel::find($requestedHookId, $merchantId);
            if (!$webhook || (int) $webhook['is_active'] !== 1) {
                $sendResponse(422, [
                    'success' => false,
                    'error' => ['code' => 'invalid_webhook', 'message' => 'The requested webhook endpoint was not found or is inactive.'],
                ], $merchantId, $apiKeyId);
            }
        } else {
            $webhook = WebhookModel::forMerchant($merchantId);
        }

        if (!$webhook || empty($webhook['url'])) {
            $sendResponse(422, [
                'success' => false,
                'error' => ['code' => 'no_webhook_configured', 'message' => 'No active webhook endpoint is configured for your merchant account.'],
            ], $merchantId, $apiKeyId);
        }

        // Sends a real signed webhook.test delivery and reports the actual
        // HTTP outcome. It never claims delivery if the endpoint failed.
        $result = WebhookDispatcher::sendTest($webhook);
        $deliveryId = (int) ($result['delivery_id'] ?? 0);
        $freshDelivery = $deliveryId > 0
            ? Database::fetch('SELECT status, response_code FROM webhook_deliveries WHERE id = ? LIMIT 1', [$deliveryId])
            : null;

        $sendResponse(200, [
            'success' => true,
            'data' => [
                'endpoint' => $webhook['url'],
                'delivery_id' => $deliveryId,
                'delivery_status' => $freshDelivery['status'] ?? 'pending',
                'response_code' => $freshDelivery['response_code'] ?? null,
                'message' => $result['message'] ?? null,
                'dispatched' => (bool) ($result['success'] ?? false),
            ],
        ], $merchantId, $apiKeyId);
    }

    // -------------------------------------------------------------
    // Endpoint: GET /api/v1/account/profile
    // -------------------------------------------------------------
    if ($method === 'GET' && ($relPath === '/account/profile' || $relPath === '/profile')) {
        $requireScope('profile:read');

        $merchant = Database::fetch('SELECT * FROM merchants WHERE id = ? LIMIT 1', [$merchantId]);
        $webhook = WebhookModel::forMerchant($merchantId);

        $sendResponse(200, [
            'success' => true,
            'data' => [
                'merchant_id' => $merchant['id'],
                'merchant_code' => $merchant['merchant_code'],
                'business_name' => $merchant['business_name'],
                'support_email' => $merchant['support_email'],
                'status' => $merchant['status'],
                'default_currency' => 'INR',
                'webhook_url' => $webhook['url'] ?? null,
                'webhook_status' => $webhook ? (!empty($webhook['is_active']) ? 'active' : 'inactive') : 'not_configured',
                'created_at' => gmdate('c', strtotime($merchant['created_at'] . ' UTC')),
            ],
        ], $merchantId, $apiKeyId);
    }

    // 404 for unknown endpoints under /api/v1
    $sendResponse(404, [
        'success' => false,
        'error' => [
            'code' => 'endpoint_not_found',
            'message' => "The requested endpoint '{$method} {$relPath}' does not exist on MAXXfam API v1.",
        ],
    ], $merchantId, $apiKeyId);

} catch (IntegrationNotConfiguredException $e) {
    $payload = [
        'success' => false,
        'error' => [
            'code' => 'integration_not_configured',
            'message' => $e->getMessage(),
        ],
    ];
    if ($idemContext) {
        IdempotencyModel::complete($idemContext['merchant'], $idemContext['endpoint'], $idemContext['key'], 409, json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
    $sendResponse(409, $payload, $merchantId, $apiKeyId);
} catch (PlanLimitException $e) {
    $payload = [
        'success' => false,
        'error' => [
            'code' => 'plan_limit_exceeded',
            'message' => $e->getMessage(),
        ],
    ];
    if ($idemContext) {
        IdempotencyModel::complete($idemContext['merchant'], $idemContext['endpoint'], $idemContext['key'], 429, json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
    $sendResponse(429, $payload, $merchantId, $apiKeyId);
} catch (ProviderException $e) {
    $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;
    $payload = [
        'success' => false,
        'error' => [
            'code' => $e->providerCode ?? 'provider_error',
            'message' => $e->getMessage(),
        ],
    ];
    if ($idemContext) {
        IdempotencyModel::complete($idemContext['merchant'], $idemContext['endpoint'], $idemContext['key'], $statusCode, json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
    $sendResponse($statusCode, $payload, $merchantId, $apiKeyId);
} catch (\Throwable $e) {
    $payload = [
        'success' => false,
        'error' => [
            'code' => 'server_error',
            'message' => 'An unexpected server error occurred.',
        ],
    ];
    if ($idemContext) {
        IdempotencyModel::complete($idemContext['merchant'], $idemContext['endpoint'], $idemContext['key'], 500, json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
    $sendResponse(500, $payload, $merchantId, $apiKeyId);
}
