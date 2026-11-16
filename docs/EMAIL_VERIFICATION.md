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

The PHP `imap` extension is **not required**. `App\Core\ImapClient` is a small RFC 3501 client that:

- opens an implicit TLS socket (default `ssl://imap.gmail.com:993`);
- verifies the peer certificate and peer name;
- logs in with the Gmail address and App Password;
- selects the mailbox, searches `UNSEEN` UIDs and fetches `BODY.PEEK[]`;
- marks a processed message `\Seen` only after it has been recorded;
- never disables certificate verification.

If the host has no system CA bundle, set an absolute path:

```dotenv
IMAP_CA_BUNDLE=/etc/ssl/certs/cacert.pem
```

Without a valid CA bundle, Gmail TLS verification fails and the account status becomes `failed`. This is intentional: the platform does not silently accept invalid certificates.

### Google App Password

1. Enable 2-Step Verification on the FamPay-linked Google account.
2. Open Google Account → Security → App passwords.
3. Create an app password for Mail.
4. Paste the 16-character value into MAXXfam. Spaces are ignored.

Normal Google account passwords cannot be used for IMAP.

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
| `IMAP command rejected: ... Invalid credentials` | Wrong Gmail address, normal password instead of App Password, or App Password revoked. |
| Status `connected` but `checked = 0` | No unseen messages, or the mailbox folder is wrong. |
| Messages `ignored` | Sender not trusted, configured UPI missing, or body is not a credit alert. |
| Messages `unmatched` | No pending order with that exact amount inside the lookback window. |
| Messages `ambiguous` | Several pending orders share the amount; reconcile manually. |
| Messages `duplicate` | The UTR was already used by another verified order. |
