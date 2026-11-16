# Gmail IMAP / FamPay email verification

MAXXfam can reconcile a pending order with a FamPay transaction-alert email read from a merchant's Gmail mailbox. This is an **additional, lower-assurance** verification source. It never replaces the provider's authoritative status API and it never fabricates a success state.

A payment verified from email is stored with `transactions.verification_source = 'email_imap'` and is clearly distinguishable from provider API (`poll`, `webhook`, `cron`) and manual verification.

---

## What the merchant configures

Open **User → Integration → Automated Payment Verification · Gmail IMAP**.

| Field | Rule |
|---|---|
| FamPay-linked Gmail address | A valid email address. Google App Passwords require 2-Step Verification on that Google account. |
| Google App Password | Exactly 16 letters/digits after spaces are removed. Encrypted with AES-256-GCM; only the last four characters are shown later. |
| FamPay UPI ID | Must end with **`@fam`** or **`@yesfam`**. This is a UPI handle, not an email domain. |

The merchant form intentionally exposes only these three fields. Fixed safe defaults are used for the rest:

| Setting | Default |
|---|---|
| IMAP host | `imap.gmail.com` |
| IMAP port | `993` |
| Encryption | `ssl` |
| Folder | `INBOX` |
| Trusted senders | `famapp.in,fampay.in` (administrator policy) |
| Enabled | `1` |

Actions available to the merchant:

- **Test IMAP** – authenticates, selects the folder and counts unseen messages. Success is recorded only after a real TLS login succeeds.
- **Scan now** – reads unseen messages and reconciles them immediately.
- **Disable / Enable** – stops or resumes automated scans without deleting credentials.
- **Remove** – deletes the account and its parsed message history.

Administrators see every account under **Admin → Email verification** and can enable, disable, test, scan, or remove an account. They also control platform policy: global enable, default trusted senders, order lookback window, maximum messages per scan, and whether sender/UPI matching is mandatory.

Permissions: `emailverify.view` (admin + support) and `emailverify.manage` (admin; `super_admin` always allowed).

---

## Verification rules

An email can mark an order `success` only if every condition passes:

1. The message sender matches the trusted list. A bare domain also matches its subdomains.
2. The configured FamPay UPI ID appears in the decoded message body (unless the administrator explicitly disables UPI matching).
3. The body looks like a credit alert (`credited`, `received`, `paid`, `success`, …).
4. A positive amount is extracted from `₹`, `Rs.`, or `INR` notation.
5. A 12-digit UTR is extracted (a contextual `UTR`/`RRN`/reference label is preferred; a bare 12-digit number is the fallback).
6. Exactly **one** pending order matches the amount within the lookback window and has a compatible/empty `upi_id`.
7. `VerificationService::processEmailVerified()` re-validates the amount, the 12-digit UTR, UTR uniqueness, provider-reference uniqueness and row locking before changing state.

If zero orders match, the message is recorded as `unmatched`. If several match, it is `ambiguous` and requires manual review. If the amount or UTR is missing, it is `unparsed`. If the sender or UPI does not match, it is `ignored`. Duplicate UTRs are recorded as `duplicate` and blocked.

Every scanned message is stored in `email_verification_messages` with sender, subject, received time, body hash, parsed amount/UTR/UPI, matched order, status and note. Message UIDs are unique per account, so a repeated scan cannot double-process the same email.

---

## IMAP implementation and TLS

The PHP `imap` extension is **not required**. `App\Core\ImapClient` is an RFC 3501 client written for Gmail:

- implicit TLS (`ssl://imap.gmail.com:993`) and STARTTLS (`starttls`, port 143);
- certificate and peer-name verification, with `IMAP_CA_BUNDLE` for hosts without a system store;
- `LOGIN` with a Google App Password **or** `AUTHENTICATE XOAUTH2` with an OAuth 2.0 access token (SASL-IR when advertised, continuation handshake otherwise);
- binary-safe literal handling, so large multipart alert emails are never truncated;
- `CAPABILITY`, `ID`, `LIST`, `SELECT`/`EXAMINE`, `UID SEARCH`, `UID FETCH`, `UID STORE`, `NOOP`;
- `UIDVALIDITY` tracking so a rebuilt mailbox cannot cause the scanner to skip or replay mail;
- Gmail-aware failure hints (`[AUTHENTICATIONFAILED]`, `Application-specific password required`, `Web login required`, `Too many simultaneous connections`, `IMAP access is disabled`, …).

If the host has no system CA bundle, set an absolute path:

```dotenv
IMAP_CA_BUNDLE=/etc/ssl/certs/cacert.pem
```

Without a valid CA bundle, Gmail TLS verification fails and the account status becomes `failed`. This is intentional: the platform does not silently accept invalid certificates.

Unencrypted IMAP exists only for local testing and is refused by the web form unless `IMAP_ALLOW_PLAINTEXT=1`.

### Google App Password

1. Enable 2-Step Verification on the FamPay-linked Google account.
2. Open Google Account → Security → App passwords.
3. Create an app password for Mail.
4. Paste the 16-character value into MAXXfam. Spaces are ignored.

Normal Google account passwords cannot be used for IMAP.

### Gmail OAuth 2.0

Use OAuth when the Google account is a Workspace mailbox, or when an administrator has disabled App Passwords. No password is stored: Google issues a refresh token, MAXXfam keeps it encrypted with AES-256-GCM, and access can be revoked from the Google account at any time.

1. In Google Cloud Console create a project and enable the **Gmail API** (OAuth consent also needs the `https://mail.google.com/` scope; add the merchant as a test user while the app is unverified).
2. Create an **OAuth client ID → Web application** with this authorised redirect URI:
   `https://your-domain/user/gmail-oauth.php`
3. Set the environment:

```dotenv
GMAIL_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
GMAIL_OAUTH_CLIENT_SECRET=xxxx
GMAIL_OAUTH_REDIRECT_URI=https://your-domain/user/gmail-oauth.php
```

4. The merchant opens **Integration → Connect with Google** and approves access. The account is switched to `auth_method = oauth2` and a refresh token is stored.
5. Run **Test IMAP** to confirm. Access tokens are refreshed automatically 2 minutes before expiry and cached encrypted in the account row.

The App Password form keeps working and stays the default. Saving the form while OAuth is active does not delete the refresh token; use **Connect with Google** again to rotate it.

---

## Advanced scan controls

These columns exist per account and are managed by the administrator (defaults keep the behaviour described above):

| Column | Default | Meaning |
|---|---|---|
| `folder` | `INBOX` | Gmail label to read. Use the full name, e.g. `[Gmail]/All Mail`, to include archived mail. |
| `search_days` | `0` | `0` = all unseen mail. `N` adds `SINCE` so only the last N days are searched. |
| `mark_read` | `1` | Mark processed messages `\Seen`. Turn off to keep the mailbox untouched (messages are still de-duplicated by UID). |
| `uidvalidity` / `last_uid` | auto | Incremental scanning. A changed `UIDVALIDITY` resets the cursor automatically. |
| `auth_method` | `app_password` | `app_password` or `oauth2`. |

---

## Doctor: prove the mailbox before trusting the cron

`scripts/imap-doctor.php` runs the same nine steps as the panel, but from the CLI, with timings and fixes:

```bash
php scripts/imap-doctor.php --user=you@gmail.com --upi=name@fam --prompt
```

It checks credentials, DNS, TLS, capabilities, authentication, folder select, mailbox list, search and a live parse of the newest unseen alert, then prints the parsed amount, UTR and UPI. Exit code `0` means the cron will work.

Other useful forms:

```bash
# Use the stored account of merchant 12 (needs DB + APP_KEY)
php scripts/imap-doctor.php --merchant=12

# Save new credentials for that merchant first, then diagnose
php scripts/imap-doctor.php --merchant=12 --save --user=you@gmail.com --upi=name@fam --prompt

# Machine readable, for monitoring
php scripts/imap-doctor.php --merchant=12 --json
```

Options include `--host`, `--port`, `--encryption` (`ssl`, `starttls`, `tls`, `none`), `--folder`, `--sender`, `--days`, `--limit` and `--json`. Keep the App Password out of shell history with `GMAIL_APP_PASSWORD` in `.env` or `--prompt`.

The protocol layer has an offline regression test that runs a scripted IMAP server, so it works without a Gmail account or network:

```bash
php tests/imap_client_test.php
```

---

## Background job

```cron
*/5 * * * * php /path/to/maxxfam/cron/process_email_verification.php
```

The job is also part of `cron/run_all.php`. It scans all enabled accounts, prints per-run totals, and writes an audit record (`cron_process_email_verification`). Use `CRON_TOKEN` when invoking over HTTP.

Scans are limited by `email_verification_max_per_run` (default 20) and by the order lookback window (default 24 hours, maximum 168).

---

## Security and limitations

- Email content alone is not proof of payment. The sender, configured UPI, exact amount and unique 12-digit UTR must all agree with exactly one pending order.
- Gmail is a third-party service. Mailbox access requires a real App Password and outbound TLS connectivity.
- Only **unseen** messages are processed. A message that cannot be parsed or matched is recorded and marked seen so it is not retried forever; review the message log.
- Email verification cannot confirm refunds. Refunds remain manual because the provider documents no refund API.
- A merchant with control of the mailbox could attempt to craft alerts. The duplicate-UTR/reference checks, exact-amount match, single-order match and `email_imap` source label limit impact but do not make email equivalent to provider confirmation. Prefer provider API verification whenever credentials are available.
- App Passwords are encrypted at rest; rotating `APP_KEY` without re-encrypting credentials makes them unreadable by design.
- Administrator provisioning can set a temporary password with `must_change_password=1`. `Auth::requireAdmin()` redirects to `/admin/change-password.php` until a permanent password is saved.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `IMAP connection failed: certificate verify failed` | No trusted CA bundle. Set `IMAP_CA_BUNDLE` or configure `openssl.cafile`. |
| `IMAP command rejected: ... Invalid credentials` / `[AUTHENTICATIONFAILED]` | Wrong Gmail address, normal account password instead of an App Password, or the App Password was revoked. |
| `Application-specific password required` | The account has no App Password yet. Enable 2-Step Verification, create one, or switch the mailbox to OAuth 2.0. |
| `Web login required` / "Please log in via your web browser" | Google flagged the sign-in. Sign in to the mailbox once in a browser, then use an App Password or OAuth. |
| `IMAP access is disabled` | Gmail Settings → Forwarding and POP/IMAP → Enable IMAP. Workspace admins can enforce this. |
| `Too many simultaneous connections` | Gmail allows 15 IMAP sessions per account. Close other clients or lower the cron frequency. |
| `Mailbox not found` / `NONEXISTENT` | Wrong folder name. Gmail labels need their full path, e.g. `[Gmail]/All Mail`. |
| OAuth: `invalid_grant` / `expired` | Refresh token revoked or client secret changed. Reconnect with **Connect with Google**. |
| OAuth: no refresh token returned | Google only returns one on first consent. Remove the app from Google Account → Security → Third-party access, then reconnect. |
| Status `connected` but `checked = 0` | No unseen messages, everything already processed (`last_uid`), or the wrong folder. |
| Messages `ignored` | Sender not trusted, configured UPI missing, or body is not a credit alert. |
| Messages `unmatched` | No pending order with that exact amount inside the lookback window. |
| Messages `ambiguous` | Several pending orders share the amount; reconcile manually. |
| Messages `duplicate` | The UTR was already used by another verified order. |
