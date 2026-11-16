# Testing and production validation

## Automated suite

```bash
php tests/run_tests.php
```

The suite runs against the database configured in `.env`. Run it against a dedicated staging database in production. It creates temporary users/merchants/orders and removes them on completion.

### Latest local result — 3 November 2026

Environment: PHP 8.3.36, MySQL 8.4.9, Windows, local database `maxxfam`.

```text
Total Assertions: 75
Passed:           75
Failed:           0
Pass Rate:        100%
```

Coverage:

| Suite | Checks |
|---|---|
| Database & infrastructure | PDO connection, 27-table schema |
| Security & cryptography | AES-256-GCM round-trip, password hashing/verification |
| Admin 2FA | TOTP generation, valid code, invalid code |
| Merchant lifecycle | User + merchant creation |
| Provider honesty | FamGateway registered, `not_configured` default, creation fails without credentials |
| Order & checkout | Unique public ID, secure token, pending initial state, provider order details |
| Verification | Invalid UTR rejected, amount mismatch rejected, verified success, duplicate UTR blocked, duplicate provider transaction blocked |
| Invoices/PDF | Auto-issued invoice, numbering format, valid PDF 1.4 + EOF |
| Webhooks | Secret creation, HMAC-signed delivery enqueue |
| API keys | `mf_live_` format, hash lookup, revocation |
| Payment links | Slug creation, active state |
| HTTP regression guards | `RateLimiter::check`, `MerchantModel::all`, `WebhookModel::forMerchant`, idempotency reserve/replay/mismatch |
| Gmail IMAP / email verification | `@fam`/`@yesfam` UPI validation, MIME decoding, amount/UTR/UPI extraction, trusted-sender matching, email-verified success without provider order ID, `email_imap` source, duplicate UTR blocked, forced-password-change column |
| Subscription plans | Plan creation with limits, assignment/expiry, order/API/link limit enforcement, pending request creation, duplicate request blocked, approval assigns plan |

## Manual browser/HTTP validation

The following were exercised against the local PHP server with real sessions, CSRF tokens and database state.

### Public website

| Path | Result |
|---|---|
| `/` | 200 |
| `/features`, `/how-it-works`, `/docs`, `/api-reference` | 200 |
| `/pricing`, `/status`, `/contact`, `/faq` | 200 |
| `/privacy`, `/terms` | 200 |
| `/login.php`, `/register.php`, `/forgot-password.php` | 200 |
| `/assets/css/app.css` | 200 |
| `/pay/{slug}` | 200 |
| `/checkout?token=invalid` | 404 |

### Merchant panel

Registration and login were completed through the real forms. All pages returned 200 with an authenticated session:

`/user/dashboard.php`, `create-payment.php`, `transactions.php`, `payment-links.php`, `api-keys.php`, `webhooks.php`, `integration.php`, `api-logs.php`, `analytics.php`, `invoices.php`, `notifications.php`, `plans.php`, `profile.php`, `security.php`, `support.php`, `documentation.php`.

### Admin panel

Administrator login, optional TOTP enrollment and session creation were completed. All pages returned 200:

`/admin/dashboard.php`, `users.php`, `merchants.php`, `transactions.php`, `orders.php`, `providers.php`, `email-verification.php`, `plans.php`, `api-management.php`, `webhooks.php`, `payment-settings.php`, `settings.php`, `smtp.php`, `security-logs.php`, `api-logs.php`, `support.php`, `announcements.php`, `reports.php`, `activity.php`, `maintenance.php`, `change-password.php`.

### Checkout

- Valid token → 200 hosted checkout with QR/state data.
- Status poll with CSRF → `200 waiting/pending`.
- Cancel action → order became `cancelled`.
- Receipt for a non-success order → `404` (receipts are only issued for verified payments).

### API v1

| Request | Result |
|---|---|
| `GET /account/profile` with valid Bearer key | 200 |
| `GET /payments` | 200 + pagination |
| `GET /payment-links` | 200 + pagination |
| `POST /payment-links/create` | 201 + real slug/URL |
| `POST /payments/create` without provider credentials | 409 `integration_not_configured` |
| Same `Idempotency-Key` replay | 409 replay with `Idempotent-Replayed: true` |
| Same key + different body | 422 `idempotency_key_reuse` |
| `POST /webhooks/test` without endpoint | 422 `no_webhook_configured` |
| `POST /webhooks/test` with an active endpoint | 200; a real signed `webhook.test` POST was attempted and the actual HTTP response (e.g. `405`) was recorded; `dispatched: false` when the endpoint did not return 2xx |

### Provider webhook receiver

| Request | Result |
|---|---|
| Unknown order | 404 |
| Existing order, missing signature | 401 |
| Existing order, invalid signature | 401 |

A valid signature followed by authoritative reconciliation requires a real provider API key; see limitations.

### Background jobs

`php cron/run_all.php` completed expiry, verification, webhook retry, email queue, and Gmail IMAP email-verification processing without errors.

The email-verification cron completed with `Accounts 0, checked 0, verified 0` because no live Gmail account is configured locally.

## What could not be validated locally

These items require external credentials or infrastructure and are **not claimed as verified**:

1. **Genuine successful payment** — needs an authorized FamGateway merchant API key and a real UPI payment. Only invalid-credential/rejection behaviour can be tested without one.
2. **Provider `Connected` status** — requires an authenticated HTTP 200 from `get-transactions.php`; it cannot honestly be asserted from configuration alone.
3. **Live provider webhook delivery** — requires the provider merchant dashboard to call the public HTTPS endpoint with the real signing key.
4. **SMTP delivery** — requires working SMTP host/credentials and correct DNS (SPF/DKIM/DMARC).
5. **Outbound merchant webhook delivery** — requires a public HTTPS endpoint; local/private destinations are blocked by the SSRF guard.
6. **Refunds** — FamGateway documents no refund API; refunds are manual by design.
7. **Live Gmail IMAP verification** — requires a real FamPay-linked Gmail address and a 16-character Google App Password. The local PHP build also has no CA bundle, so outbound Gmail TLS fails certificate verification unless `IMAP_CA_BUNDLE` points to a valid `cacert.pem`. Parsing, matching and duplicate protection are covered by the automated suite with synthetic MIME messages.

## Production go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`
- [ ] Strong, unique `APP_KEY` backed up securely
- [ ] Database user has least privilege; backups scheduled
- [ ] Docroot is `public/`; `app/`, `config/`, `database/`, `storage/`, `cron/` denied
- [ ] Admin created through CLI; TOTP enrolled; recovery procedure documented
- [ ] Any temporary administrator password rotated at `/admin/change-password.php`
- [ ] Provider credentials entered and **Test connection** succeeds
- [ ] Provider dashboard webhook URL points to `/api/webhooks/famgateway.php`
- [ ] If Gmail IMAP is enabled: `IMAP_CA_BUNDLE` configured, **Test IMAP** succeeds, a real FamPay alert is reconciled
- [ ] Email-verification cron scheduled and observed
- [ ] Real low-value payment tested end-to-end, including UTR, invoice and receipt
- [ ] Duplicate/replay test repeated with real provider data
- [ ] SMTP test email received; cron jobs scheduled and observed
- [ ] Merchant webhook endpoint receives a signed test delivery
- [ ] Logs, audit and security pages reviewed
- [ ] Independent security review / penetration test completed
- [ ] Privacy policy, terms, support contacts and business details updated

## Re-running tests safely

Use a separate database:

```dotenv
DB_NAME=maxxfam_test
```

Then:

```bash
php database/migrate.php
php tests/run_tests.php
```

Do not run the suite against production data: it creates and deletes temporary records, although it only removes rows it created.
