# MAXXfam REST API v1

Base URL: `https://your-domain.example/api/v1`  
Content type: `application/json`  
Authentication: `Authorization: Bearer mf_live_...`

API keys are created in **User → API keys**. The raw key is shown once; only its SHA-256 hash is stored. Keys can be revoked or rotated at any time.

All responses are JSON. Successful responses use `success: true`; errors use:

```json
{
  "success": false,
  "error": {
    "code": "machine_readable_code",
    "message": "Human-readable explanation."
  }
}
```

Every response includes `X-Request-Id`. API requests are logged with request/response bodies redacted for secrets.

---

## Authentication and scopes

| Scope | Allows |
|---|---|
| `payments:read` | Read payment orders and status |
| `payments:write` | Create payment orders |
| `links:read` | List payment links |
| `links:write` | Create payment links |
| `webhooks:test` | Send a signed webhook test |
| `profile:read` | Read merchant profile |

A missing, revoked or expired key returns `401`. A valid key without the required scope returns `403 insufficient_scope`.

### Rate limits

Default **60 requests per minute per API key**. The limit is configurable in Admin → System configuration (`api_rate_limit_per_minute`). Exceeding it returns `429` with `Retry-After`.

### Idempotency

`POST /payments/create` accepts:

```http
Idempotency-Key: 7c9e6679-7425-40de-944b-e07fc1f90ae7
```

- The key must be 8–200 characters.
- Replaying the same key with the same JSON body returns the original status and body, with `Idempotent-Replayed: true`.
- Reusing the key with a different body returns `422 idempotency_key_reuse`.
- A request already in progress returns `409 request_in_progress`.
- Records expire after 24 hours.

---

## Create a payment

`POST /payments/create`  
Scope: `payments:write`

```json
{
  "amount": 499.00,
  "customer_name": "Asha Rao",
  "customer_email": "asha@example.com",
  "customer_phone": "9876543210",
  "customer_reference": "INV-1042",
  "description": "Annual subscription",
  "success_redirect_url": "https://merchant.example/thank-you",
  "webhook_url": "https://merchant.example/webhooks/payments",
  "provider_code": "famgateway"
}
```

Required: `amount` (INR, within platform min/max). Other fields are optional. `provider_code` defaults to `famgateway`.

Success: `201 Created`

```json
{
  "success": true,
  "data": {
    "order_id": "MF4F2A9C71D8E3B5A0C6D91234",
    "amount": "499.00",
    "payable_amount": "499.00",
    "currency": "INR",
    "status": "pending",
    "checkout_url": "http://127.0.0.1:8080/checkout.php?token=...",
    "qr_url": "https://famgateway.in/...",
    "upi_intent": "upi://pay?...",
    "upi_id": "merchant@upi",
    "customer_reference": "INV-1042",
    "expires_at": "2026-11-02T14:05:00+00:00",
    "created_at": "2026-11-02T14:00:00+00:00"
  }
}
```

Possible errors:

| HTTP | Code | Meaning |
|---|---|---|
| 409 | `integration_not_configured` | No authorized provider credential for this merchant |
| 409 | `provider_disabled` | Provider disabled by an administrator |
| 422 | `amount_out_of_range` | Amount outside configured limits |
| 422 | `invalid_amount` | Non-positive/invalid amount |
| 502 | `network_error` | Provider could not be reached |
| 502 | `bad_response` on `provider_http_error` | Provider returned an invalid/rejected response |

`qr_url`, `checkout_url` and `payable_amount` are the provider's values. Creating a QR does **not** mean payment succeeded.

---

## Retrieve one payment

`GET /payments/{order_id}`  
Scope: `payments:read`

If the order is still `pending`, the API asks the provider for the authoritative status before responding. Only the provider's confirmation can produce `success`.

```json
{
  "success": true,
  "data": {
    "order_id": "MF4F2A9C71D8E3B5A0C6D91234",
    "amount": "499.00",
    "payable_amount": "499.00",
    "currency": "INR",
    "status": "success",
    "checkout_url": "http://127.0.0.1:8080/checkout.php?token=...",
    "qr_url": "https://famgateway.in/...",
    "upi_intent": "upi://pay?...",
    "customer_reference": "INV-1042",
    "customer_name": "Asha Rao",
    "customer_email": "asha@example.com",
    "utr": "123456789012",
    "verified_at": "2026-11-02T14:03:12+00:00",
    "receipt_url": "http://127.0.0.1:8080/receipt.php?token=...",
    "failure_message": null,
    "expires_at": "2026-11-02T14:05:00+00:00",
    "created_at": "2026-11-02T14:00:00+00:00"
  }
}
```

Status values: `pending`, `success`, `failed`, `expired`, `cancelled`.

Unknown order: `404 order_not_found`.

---

## List payments

`GET /payments?page=1&per_page=20&status=success&from=2026-11-01&to=2026-11-30`  
Scope: `payments:read`

`per_page` is capped at 100. Dates are accepted as `YYYY-MM-DD`.

```json
{
  "success": true,
  "data": [
    {
      "order_id": "MF4F2A9C71D8E3B5A0C6D91234",
      "amount": "499.00",
      "payable_amount": "499.00",
      "currency": "INR",
      "status": "success",
      "customer_name": "Asha Rao",
      "customer_email": "asha@example.com",
      "customer_reference": "INV-1042",
      "provider": "FamGateway UPI",
      "created_at": "2026-11-02T14:00:00+00:00",
      "paid_at": "2026-11-02T14:03:12+00:00"
    }
  ],
  "pagination": {
    "total": 1,
    "page": 1,
    "per_page": 20,
    "pages": 1
  }
}
```

---

## Create a payment link

`POST /payment-links/create`  
Scope: `links:write`

```json
{
  "title": "Consulting invoice #1042",
  "description": "One-hour architecture review",
  "amount": 2500.00,
  "min_amount": 1.00,
  "max_amount": 100000.00,
  "customer_reference": "CLIENT-1042",
  "success_redirect_url": "https://merchant.example/thank-you",
  "expires_in_days": 30
}
```

`amount` may be `null`/omitted for a variable-amount link. Required: `title` (2–190 characters).

Success: `201 Created`

```json
{
  "success": true,
  "data": {
    "link_id": 12,
    "slug": "consulting-invoice-1042-a1b2c3",
    "url": "https://your-domain.example/pay/consulting-invoice-1042-a1b2c3",
    "title": "Consulting invoice #1042",
    "amount": "2500.00",
    "min_amount": "1.00",
    "max_amount": "100000.00",
    "status": "active",
    "expires_at": "2026-12-02T14:00:00+00:00",
    "created_at": "2026-11-02T14:00:00+00:00"
  }
}
```

---

## List payment links

`GET /payment-links?page=1&per_page=20`  
Scope: `links:read`

Returns an array of links with `link_id`, `slug`, `url`, `title`, `amount`, `status`, `total_payments`, `total_volume`, `created_at`, plus a `pagination` object.

---

## Test a webhook

`POST /webhooks/test`  
Scope: `webhooks:test`

Sends a real signed `webhook.test` event to the merchant's active webhook endpoint and reports delivery status.

```json
{
  "success": true,
  "data": {
    "endpoint": "https://merchant.example/webhooks/payments",
    "delivery_id": 91,
    "delivery_status": "success",
    "response_code": 200,
    "message": "Delivered successfully.",
    "dispatched": true
  }
}
```

If no active endpoint exists: `422 no_webhook_configured`. If a specific endpoint id is supplied and it is not active or not owned by the merchant: `422 invalid_webhook`.

`dispatched` is only `true` when the endpoint returned a real `2xx`. Non-2xx responses, TLS failures and timeouts return `200` with `dispatched: false`, the actual `response_code` (or `null`) and a `message`; the delivery is queued for retry.

---

## Account profile

`GET /account/profile`  
Scope: `profile:read`

```json
{
  "success": true,
  "data": {
    "merchant_id": 8,
    "merchant_code": "MFM33213C1E",
    "business_name": "Acme Digital",
    "support_email": "billing@acme.example",
    "status": "active",
    "default_currency": "INR",
    "webhook_url": "https://acme.example/webhooks/payments",
    "webhook_status": "active",
    "created_at": "2026-10-01T09:00:00+00:00"
  }
}
```

---

## Merchant webhooks

Configure webhook URLs in **User → Webhooks**. Events:

`payment.created`, `payment.pending`, `payment.success`, `payment.failed`, `payment.expired`, `webhook.test`

Each delivery includes:

```http
X-MAXXfam-Event: payment.success
X-MAXXfam-Delivery: 91
X-MAXXfam-Timestamp: 1762092000
X-MAXXfam-Signature: <hex HMAC-SHA256>
```

Verify the signature against the **raw request body** using the endpoint secret (`whsec_...`):

```php
$expected = hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($expected, $_SERVER['HTTP_X_MAXXFAM_SIGNATURE'])) {
    http_response_code(401);
    exit;
}
```

Respond with any `2xx` to acknowledge. Non-2xx responses are retried with exponential backoff. Delivery history and response codes are visible in the dashboard. Webhook payloads never contain API keys, provider credentials, card data or customer payment secrets.

---

## Provider webhook (FamGateway → MAXXfam)

`POST /api/webhooks/famgateway.php`

Header: `X-FamGateway-Signature: <hex HMAC-SHA256 of raw body, key = merchant API key>`

The receiver:

1. Validates the HMAC before any state change.
2. Records the payload hash for replay visibility (unique per provider).
3. Calls FamGateway's authoritative `verify-order.php` status API.
4. Marks the order successful only through the normal verification state machine.

A missing or invalid signature returns `401`. Provider webhook bodies are never accepted as standalone proof of payment.

---

## Error codes

| HTTP | Code examples |
|---|---|
| 401 | `unauthorized`, `invalid_api_key` |
| 403 | `insufficient_scope` |
| 404 | `order_not_found`, `endpoint_not_found` |
| 409 | `integration_not_configured`, `request_in_progress` |
| 422 | `invalid_amount`, `invalid_title`, `idempotency_key_reuse`, `no_webhook_configured` |
| 429 | `rate_limit_exceeded` |
| 500 | `server_error` |
| 502 | `provider_error`, `network_error`, `bad_response` |
