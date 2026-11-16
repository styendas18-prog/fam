# Security model

## Authentication

### Merchants

- Email/password registration with server-side validation.
- Passwords hashed with PHP `password_hash()` (`PASSWORD_DEFAULT`, Argon2id where available, bcrypt otherwise) and rehashed on login when needed.
- Minimum password length 10 for merchants; administrators require 12+ with upper/lower/number.
- Login attempts are rate limited (`login_attempts`, `rate_limits`) and accounts lock after repeated failures.
- Session ID rotation on login and logout.
- Cookie flags: `HttpOnly`, `SameSite=Lax` (configurable), `Secure` automatically enforced when `APP_URL` is HTTPS.
- Session inactivity timeout (`SESSION_LIFETIME`).

### Administrators

- Separate `admins` table, separate login and session keys.
- Optional TOTP 2FA; administrators can enable it from the admin menu (password-only sign-in is allowed).
- TOTP secrets encrypted with AES-256-GCM.
- Lockout after repeated failures; rate limiting per IP/account.
- Optional `must_change_password` flag: an account provisioned with a temporary password is redirected to `/admin/change-password.php` until a permanent password (12+ characters, upper/lower/number) is saved.
- Recovery only through CLI (`php cron/create_admin.php`), never through a public endpoint.
- RBAC permissions per role and per page/action (`Auth::adminCan()`).

### API

- Bearer keys with prefix `mf_live_`.
- Only SHA-256 hashes are stored; the raw key is shown once at creation.
- Keys have explicit scopes, optional expiry, revocation and rotation.
- Per-key rate limiting.
- API logs redact keys, passwords, secrets, tokens, card and PIN fields.
- Idempotency keys are hashed; request-body hash mismatch is rejected.

## Authorization

- User resources are always filtered by `merchant_id`/`user_id` from the session, never by a client-supplied ID alone.
- Admin pages call `AdminPage::boot($permission)`; mutating actions check the relevant permission and write an audit record.
- Sensitive actions (suspend user, change provider credentials, maintenance mode, SMTP) require confirmation in the UI and are audit logged.
- Administrators cannot fabricate provider-confirmed payments: there is no admin endpoint that sets `success`, creates a transaction, or edits a verified UTR. The only path to `success` is `VerificationService` after a real provider status response.

## CSRF

- All state-changing browser requests include a session-bound `_token`.
- AJAX sends `X-CSRF-Token`.
- `Csrf::validateOrFail()` renders a 419 page for browser requests and JSON for API-style requests.
- API requests do not use cookies; they use Bearer authentication and therefore do not need CSRF tokens.

## XSS and output escaping

- Views escape dynamic values with the global `e()` helper (`htmlspecialchars`, `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8).
- A per-request CSP nonce is used for the small inline theme bootstrap script.
- User-supplied HTML is not rendered raw.
- JSON responses use `json_encode` and never interpolate untrusted strings into HTML.

## SQL injection

- All dynamic queries use PDO prepared statements.
- Dynamic `LIMIT`/`OFFSET` values are cast to integers and bounded.
- Filters are whitelisted or bound as parameters.
- Identifiers interpolated into SQL are internal constants, not request values.

## Payment integrity

The following controls prevent fake or duplicate success:

1. Provider adapter validates the authoritative response (order ID, amount, UTR format).
2. `VerificationService::processVerified()` compares amount and currency exactly.
3. `SELECT ... FOR UPDATE` locks the order during transition.
4. Unique indexes enforce one transaction per order, unique UTR, and unique `(provider_id, provider_transaction_id)`.
5. Duplicate UTR and duplicate provider transaction references are detected before insert and converted into explicit security events.
6. Duplicate-key races are caught and translated into `VerificationException`, never a silent success.
7. Webhook payloads are never trusted; the receiver always re-queries the provider.
8. Provider webhooks require a timing-safe HMAC-SHA256 signature.
9. Payload hashes are uniquely recorded for replay visibility.
10. Invoice, notification, webhook enqueue and audit happen inside the same database transaction as the status change.
11. Email-verified payments (`verification_source = 'email_imap'`) must pass trusted-sender matching, configured UPI-ID presence, exact amount, 12-digit UTR, single-pending-order matching and the same duplicate UTR/reference checks. Email alerts skip only the provider order-ID assertion, which does not exist in email.
12. Email content is never trusted on its own, and an email-verified transaction is labelled separately from provider API confirmation.

## Email verification credentials

- Google App Passwords are encrypted at rest with AES-256-GCM; only the last four characters are stored in plaintext for display.
- Credentials are decrypted only inside the service that opens the IMAP connection, never sent back to the browser.
- The custom IMAP client verifies the TLS peer certificate and peer name. Certificate verification is never disabled.
- Only unseen messages are fetched and each message UID is unique per account, preventing double processing.
- Trusted senders, the configured `@fam`/`@yesfam` UPI ID, amount and UTR are all required by default; administrators can relax sender/UPI matching but not amount/UTR/duplicate checks.
- Every scan writes per-message status and an audit record; secret values are redacted from errors and logs.

## Secrets

- `.env` is git-ignored.
- Provider credentials and webhook secrets are encrypted at rest with AES-256-GCM.
- `APP_KEY` is the master key; losing it makes existing ciphertext unreadable.
- Secrets are excluded from API responses, HTML, logs and error messages.
- Provider API keys are redacted from provider error strings before storage/display.
- SMTP password is stored as a secret setting and never returned to the browser.

## Outbound requests (SSRF)

- Merchant webhook URLs must be HTTP(S).
- Resolved IPs are checked against private, loopback, link-local and cloud-metadata ranges.
- The validated IP is pinned for the request (`CURLOPT_RESOLVE`) to reduce DNS-rebinding risk.
- Provider base URLs are configuration values, not user input.

## Headers and transport

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- Content Security Policy with nonce
- HTTPS required in production; `SESSION_SECURE=true`
- No directory listing (`Options -Indexes`)

## Logging and audit

- `security_logs`: authentication, verification mismatches, duplicate UTRs, admin actions.
- `admin_activity_logs`: admin CRUD and configuration changes.
- `api_logs`: request path, status, latency, redacted bodies, error.
- `provider_webhook_events`: signature failures, processed/ignored events, payload hashes.
- `storage/logs/app-*.log`: structured JSON application logs.
- Timestamps stored in UTC; UI displays IST where appropriate.

## CSV injection

CSV exports neutralize cells starting with `=`, `-`, `+` or `@` by prefixing an apostrophe.

## Known limitations

- No WAF, DDoS protection or bot management is bundled; use your hosting/provider layer.
- Email deliverability depends on correct SPF/DKIM/DMARC configuration.
- The application cannot prevent a compromised administrator workstation from approving harmful manual actions; 2FA, RBAC and audit logs reduce but do not eliminate that risk.
- Refunds are manual because the provider exposes no refund API.
- Security testing should be repeated after any code change. The included suite covers core controls but is not a substitute for an independent penetration test.
