# MAXXfam

**Smart Payments. Real Verification. Complete Control.**

MAXXfam is a production-oriented UPI payment gateway platform built with PHP 8.2+, MySQL 8+, Tailwind CSS and vanilla JavaScript. It creates real provider orders, displays provider-issued QR/checkout data, verifies payments server-to-server, and only marks an order `success` after the provider's authoritative status API confirms it.

> MAXXfam is an independent product. It is not affiliated with, endorsed by, or sponsored by FamPay, NPCI, any bank, or any UPI application. FamGateway is a third-party provider; use only an account and integration you are authorized to operate.

---

## What is real and what needs credentials

| Capability | Status |
|---|---|
| Merchant registration, login, merchant dashboard | Functional |
| Admin dashboard, RBAC, optional TOTP 2FA | Functional |
| Payment order creation through FamGateway | Requires an authorized FamGateway merchant API key |
| Dynamic QR / hosted checkout display | Provided by FamGateway response; requires credentials |
| Payment verification | Server-to-server call to FamGateway `verify-order.php`; requires credentials |
| Gmail IMAP / FamPay email verification | Functional; requires a FamPay-linked Gmail address and 16-character Google App Password |
| Subscription plans, limits, merchant requests | Functional; admin CRUD and approval, monthly order/API/link limits enforced |
| Signed webhook processing | HMAC-SHA256 verification; requires credentials and provider webhook configuration |
| API v1, API keys, scopes, rate limiting, idempotency | Functional |
| Merchant webhooks, retries, HMAC signatures, delivery history | Functional (outbound network required) |
| Invoices, PDF receipts, CSV/PDF reports | Functional |
| SMTP notifications | Requires real SMTP credentials |
| Refunds | **Not offered by the provider's documented API**; manual reconciliation only |
| Genuine end-to-end payment test | Requires a real authorized provider account |

No demo users, demo transactions, fabricated success states, or invented dashboard statistics are seeded. Dashboard figures are SQL aggregates over real rows.

---

## Requirements

- PHP **8.2+** with `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`
- MySQL **8.0+**
- Apache 2.4+/Nginx with HTTPS
- Composer (optional; the application ships with its own PSR-4 autoloader)
- Node.js 18+ only if you want to rebuild Tailwind CSS

> The PHP `imap` extension is **not** required. Gmail is read through a bundled TLS socket client (`App\Core\ImapClient`). Outbound TLS certificate verification needs a CA bundle; if the host has none, set `IMAP_CA_BUNDLE` to a valid `cacert.pem` path.

## Quick start (local)

```bash
cp .env.example .env
# Generate APP_KEY:
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
# Set DB_* and APP_URL in .env

php database/migrate.php
php cron/create_admin.php "Platform Owner" owner@example.com super_admin

php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080`. The administrator entry point is `http://127.0.0.1:8080/admin/login.php`; first sign-in forces authenticator enrollment.

For production, point the web server document root at `public/` and deny direct access to `app/`, `config/`, `database/`, `storage/`, `cron/`, `docs/` and `.env`.

### Build CSS (optional)

The compiled stylesheet is committed at `public/assets/css/app.css`. To rebuild:

```bash
npm install tailwindcss@3.4.17 --no-save
npx tailwindcss -c tailwind.php.config.js -i resources/css/app.css -o public/assets/css/app.css --minify
```

`package.json`, `src/`, `server/`, `worker/` and `index.html` at the repository root belong to an unrelated legacy prototype. They are **not** used by the MAXXfam PHP application and can be omitted from the production artifact.

---

## Provider integration

Open **Admin → Payment providers** and configure the FamGateway API key. Credentials are encrypted with AES-256-GCM using `APP_KEY`; only the last four characters are ever shown. A merchant can also store their own encrypted provider credential under **User → Integration**.

Honest connection states:

- `Not Configured` — no credential stored
- `Testing` — connection test in progress
- `Connected` — an authenticated request to the provider succeeded
- `Connection Failed` — the provider rejected or could not be reached
- `Disabled` — provider disabled by an administrator

The provider is never marked `Connected` from seeding, configuration alone, or a fabricated response.

Documented FamGateway surface used:

- `POST /api/create-order` — create order / return QR + checkout data
- `GET /api/verify-order.php?order_id=...` — authoritative payment status
- `GET /api/get-transactions.php` — read-only connectivity test
- Provider webhook signed with HMAC-SHA256 over the raw body, header `X-FamGateway-Signature`

The platform webhook receiver is `POST /api/webhooks/famgateway.php`. It verifies the HMAC before any state change, records the payload hash, then **always re-queries the provider status API**. A webhook body is never treated as proof of payment.

See [docs/PROVIDER.md](docs/PROVIDER.md) for the exact contract, security model and limitations.

---

## Gmail IMAP / FamPay email verification

Merchants can add a second, clearly labelled verification source under **User → Integration → Automated Payment Verification · Gmail IMAP**, with only three fields:

- FamPay-linked Gmail address
- 16-character Google App Password (spaces ignored; encrypted with AES-256-GCM)
- FamPay UPI ID, which must end with **`@fam`** or **`@yesfam`**

IMAP connection details use safe platform defaults (`imap.gmail.com`, port `993`, SSL, `INBOX`), and trusted senders default to `famapp.in,fampay.in`; administrators can change the sender policy. Connection test, manual scan, enable/disable and parsed-message history remain available below the form.

A payment is verified from email only when **all** of the following hold:

1. The sender domain/address is in the trusted list (`famapp.in,fampay.in` by default).
2. The configured FamPay UPI ID appears in the message body.
3. A valid **12-digit UTR** and the exact payable amount are extracted.
4. Exactly one pending order matches the amount inside the configured lookback window.
5. The UTR and provider reference are unique across all verified transactions.

Email verification is recorded with the distinct source `email_imap` and does not replace provider API confirmation. Duplicate UTRs remain blocked, and every scan stores the parsed message, outcome and note for audit. See [docs/EMAIL_VERIFICATION.md](docs/EMAIL_VERIFICATION.md).

Administrators manage platform policy and every merchant account under **Admin → Email verification** (`emailverify.view` / `emailverify.manage`).

---

## Subscription plans and usage limits

Administrators can create, edit, enable/disable and delete plans under **Admin → Plans**. Each plan defines:

- price and billing interval (`monthly`, `yearly`, or `one_time`)
- monthly limits for payment orders, API requests and payment links (`0` = unlimited)
- description, feature list, featured flag and sort order

Merchants see active plans under **User → Plans** and submit a request. An administrator approves or rejects it; approval assigns the plan and monthly usage starts counting. Usage is measured per calendar month (UTC) from real `payment_orders`, `api_logs` and `payment_links` rows.

Limits are **enforced**, not display-only:

- `PaymentService::create()` blocks new orders at the transaction limit.
- The API v1 entry point blocks requests at the API limit with `plan_limit_exceeded`.
- `PaymentLinkModel::create()` blocks new links at the link limit.
- Expired or inactive assigned plans block new billable actions until a new plan is approved.

A merchant with no assigned plan has no plan-level limits. See [docs/PLANS.md](docs/PLANS.md).

---

## Payment states

`pending` → `success` | `failed` | `expired` | `cancelled`

Only `VerificationService::processVerified()` can set `success`. Before doing so it validates:

1. Provider order ID matches the stored order.
2. Provider-confirmed amount equals the payable amount.
3. Provider-confirmed UTR is exactly 12 digits.
4. The UTR is unique across all verified transactions.
5. The provider transaction reference is unique for the provider.
6. A `SELECT ... FOR UPDATE` row lock and database transaction serialize concurrent callbacks.
7. Invoice, notification, merchant webhook and audit entries are created atomically.

Email alerts use the same pipeline through `VerificationService::processEmailVerified()`. They skip only the provider order-ID assertion (an email has none); amount, 12-digit UTR, duplicate-UTR/reference protection and row locking still apply, and the transaction source is `email_imap`.

---

## Developer API

Base URL: `/api/v1`  
Authentication: `Authorization: Bearer mf_live_...`  
Scopes: `payments:read`, `payments:write`, `links:read`, `links:write`, `webhooks:test`, `profile:read`

| Method | Endpoint | Scope |
|---|---|---|
| POST | `/api/v1/payments/create` | `payments:write` |
| GET | `/api/v1/payments/{order_id}` | `payments:read` |
| GET | `/api/v1/payments` | `payments:read` |
| POST | `/api/v1/payment-links/create` | `links:write` |
| GET | `/api/v1/payment-links` | `links:read` |
| POST | `/api/v1/webhooks/test` | `webhooks:test` |
| GET | `/api/v1/account/profile` | `profile:read` |

API keys are stored only as SHA-256 hashes and can be revoked or rotated. Requests are rate limited (configurable, default 60/minute per key), logged with sensitive fields redacted, and support `Idempotency-Key` on payment creation.

Full request/response documentation: [docs/API.md](docs/API.md).

---

## Security controls

- Argon2id/bcrypt password hashing (`PASSWORD_DEFAULT` with rehash)
- CSRF tokens on all state-changing browser requests
- Output escaping helpers and strict input validation
- PDO prepared statements throughout
- Secure, HttpOnly, SameSite cookies; session ID rotation on login
- Login rate limiting, failed-attempt lockout and audit records
- RBAC for administrators; optional TOTP 2FA
- AES-256-GCM encryption for provider credentials, webhook secrets and Google App Passwords
- SHA-256-only API key storage
- SSRF checks on outbound webhook URLs
- CSV-injection neutralization in exports
- Idempotency, duplicate UTR and duplicate transaction-reference protection
- Trusted-sender, configured-UPI, 12-digit UTR and exact-amount checks for email-verified payments
- Forced administrator password rotation when an account is provisioned with a temporary password
- Audit/security logs with UTC timestamps and IST display formatting

See [docs/SECURITY.md](docs/SECURITY.md).

---

## Background jobs

```cron
* * * * * php /path/to/maxxfam/cron/expire_orders.php
* * * * * php /path/to/maxxfam/cron/verify_payments.php
* * * * * php /path/to/maxxfam/cron/retry_webhooks.php
* * * * * php /path/to/maxxfam/cron/process_email.php
* * * * * php /path/to/maxxfam/cron/process_email_verification.php
```

Or run all jobs with `php cron/run_all.php`. HTTP invocation is possible with `?token=CRON_TOKEN` / `X-Cron-Token`; prefer CLI.

---

## Directory layout

```text
app/                 Core framework, models, services, providers, views
config/app.php       Configuration loaded from .env
database/            Migrations, safe seed, migrate.php
public/              Web document root (index.php, user/, admin/, api/)
resources/css/       Tailwind source
storage/             Logs, invoices, cache (must be writable)
cron/                Background jobs and secure admin CLI
docs/                Installation, API, provider, security, testing docs
tests/run_tests.php  Automated verification suite
```

## Documentation

- [Installation & deployment](docs/INSTALLATION.md)
- [API reference](docs/API.md)
- [Provider integration](docs/PROVIDER.md)
- [Gmail IMAP email verification](docs/EMAIL_VERIFICATION.md)
- [Subscription plans and limits](docs/PLANS.md)
- [Security model](docs/SECURITY.md)
- [Testing & production validation](docs/TESTING.md)

## License

Proprietary. All rights reserved.
