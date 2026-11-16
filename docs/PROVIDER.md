# Payment provider integration

## Provider

**FamGateway UPI** — `https://famgateway.in`  
Documentation: `https://famgateway.in/docs.php`

MAXXfam uses only the provider's documented, authenticated API. It does not claim affiliation with FamPay, NPCI, any bank or UPI application. You must have an account and API access that you are authorized to use.

## Adapter surface

`App\Providers\FamGatewayProvider` implements `PaymentProviderInterface`:

| Method | Purpose |
|---|---|
| `createPayment()` | `POST /api/create-order` |
| `getPaymentStatus()` | `GET /api/verify-order.php` |
| `processWebhook()` | HMAC validation of `X-FamGateway-Signature` |
| `testConnection()` | authenticated read-only `GET /api/get-transactions.php` |
| `supportsRefunds()` | `false` — provider documents no refund API |
| `getRefundStatus()` | throws `refunds_not_supported` |

Authentication uses the `X-Api-Key` header containing the merchant's provider API key. The key is stored encrypted at rest and is never sent to the browser.

## Credential storage

- AES-256-GCM with a 12-byte random IV and 16-byte authentication tag.
- Master key: `APP_KEY` (base64-encoded 32 bytes) from `.env`.
- Additional authenticated data: `maxxfam:v1`.
- Format: `MF1.<base64(iv || tag || ciphertext)>`.
- The admin UI displays only a masked suffix/last four characters.
- The raw credential is never returned by any API, log or view.
- Rotating the credential is done by saving a new key; the connection status resets to `Not Configured` until a real test succeeds.

If no merchant credential exists, the admin can optionally configure a platform default. This is only possible when `ALLOW_PLATFORM_DEFAULT_CREDENTIALS=1` and must be an intentional business decision because funds settle to the platform account.

## Order creation

Request sent to the provider:

```json
{
  "amount": 499.00,
  "customer_name": "Asha Rao",
  "customer_email": "asha@example.com",
  "customer_phone": "9876543210",
  "redirect_url": "https://your-domain/checkout.php?token=...",
  "webhook_url": "https://your-domain/api/webhooks/famgateway.php"
}
```

The adapter accepts a provider response only when:

- `status` is `success`,
- `order_id` is present and matches `[A-Za-z0-9_-]{1,64}`,
- `payable_amount`/`amount` is positive,
- `qr_url` is an HTTPS URL on the configured provider host,
- optional `checkout_url` is also on the provider host,
- optional `upi_intent` begins with `upi://pay?`,
- expiry is parsed from IST into UTC.

Any violation fails the payment creation and leaves the local order in a non-success state. Provider errors are never converted into successful payments.

## Status verification

`GET /api/verify-order.php?order_id=...` is the only source that can confirm a payment.

The adapter requires, for `success`:

- a 12-digit numeric UTR,
- a numeric amount,
- a recognised status (`success`, `pending`, `expired`, `failed`, `cancelled`).

`VerificationService` then validates amount equality, currency, order ID, UTR uniqueness, provider transaction-reference uniqueness, and applies an atomic row lock before writing `success`.

## Provider webhook

Endpoint: `POST /api/webhooks/famgateway.php`

1. The raw body is read exactly as received.
2. `X-FamGateway-Signature` is required and must be 64 hex characters.
3. Expected signature = `hash_hmac('sha256', rawBody, merchantApiKey)`.
4. `hash_equals()` provides timing-safe comparison.
5. The payload hash is recorded in `provider_webhook_events` with a unique constraint for replay visibility.
6. The receiver **never** trusts `status`, `amount` or `utr` from the payload.
7. It calls `VerificationService::verify(..., force: true)`, which queries the authoritative status API.
8. Only that API response can move the order to `success`.
9. Duplicate payloads that were already processed return `200` without re-processing.

Missing/invalid signature → `401`. Unknown order → `404`. Missing merchant credentials → `409`.

## Merchant webhooks (outbound)

Merchants configure endpoints in the dashboard. Deliveries are signed:

```text
X-MAXXfam-Signature = HMAC-SHA256(rawBody, whsec_secret)
```

Events: `payment.created`, `payment.pending`, `payment.success`, `payment.failed`, `payment.expired`, `webhook.test`.

Retries use `[60, 300, 900, 3600, 21600]` seconds by default (configurable in `webhook_retry_schedule`). Endpoint URLs are checked for SSRF (private/link-local/metadata addresses are rejected). Response codes and bodies (truncated) are stored for inspection.

## Honest status model

| Status | Meaning |
|---|---|
| `not_configured` | No credential stored |
| `testing` | A connection test is running |
| `connected` | An authenticated provider request returned HTTP 200 |
| `connection_failed` | Provider rejected, timed out, or returned invalid JSON |
| `disabled` | Administrator disabled the provider |

A status is never set by the seed, by saving a key, or by a frontend message.

## Refunds and limitations

- FamGateway's documented API does not expose refunds or refund status.
- MAXXfam therefore does not fabricate refunds. `supportsRefunds()` returns `false`.
- Any refund must be issued manually in the merchant's UPI application and recorded outside the automated flow.
- A genuine success test requires a real, authorized FamGateway merchant key and a UPI payment. Invalid-key connectivity tests can only prove rejection, not payment success.
- Provider-side fees, settlement timing, chargebacks and dispute handling are governed by the provider agreement.

## Testing connectivity

Admin → Payment providers → **Test connection**, or:

```bash
php -r "require 'app/bootstrap.php'; ..."
```

The test performs a real TLS request to `get-transactions.php`. Success means authenticated connectivity, not that a payment succeeded. The provider's own error message is stored (truncated and with the key redacted) when the test fails.
