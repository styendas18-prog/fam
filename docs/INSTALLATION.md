# Installation, deployment and operations

## 1. Requirements

| Component | Minimum |
|---|---|
| PHP | 8.2 (8.3 recommended) |
| Extensions | `pdo`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json` |
| MySQL | 8.0 (8.4 tested) |
| Web server | Apache 2.4+ / Nginx / LiteSpeed |
| TLS | Valid HTTPS certificate required in production |
| Cron | One cron-capable user |

The PHP `imap` extension is not required. Gmail IMAP uses the bundled `App\Core\ImapClient` over TLS. If the host has no CA bundle, set `IMAP_CA_BUNDLE` to a current `cacert.pem` path.

Node.js 18+ is only needed if you rebuild the Tailwind stylesheet.

## 2. Upload and permissions

1. Upload the project outside the web root, or set the docroot to `public/`.
2. Make these directories writable by the web-server user:
   - `storage/logs`
   - `storage/cache`
   - `storage/invoices`
3. Never expose `.env`, `app/`, `config/`, `database/`, `cron/`, `docs/` or `storage/` over HTTP.

## 3. Environment file

```bash
cp .env.example .env
```

Generate a 32-byte AES key:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Set at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://payments.your-domain.example
APP_KEY=<base64 32-byte key>
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=maxxfam
DB_USER=maxxfam
DB_PASS=<strong password>
SESSION_SECURE=true
FAMGATEWAY_BASE_URL=https://famgateway.in
CRON_TOKEN=<long random token>
# Optional: absolute path to a CA bundle for outbound Gmail IMAP TLS
IMAP_CA_BUNDLE=
```

Important:

- `APP_URL` must be the exact HTTPS origin. It is used for checkout, receipt and webhook URLs.
- Changing `APP_KEY` makes previously encrypted provider credentials and webhook secrets unreadable. Back it up securely.
- `ALLOW_PLATFORM_DEFAULT_CREDENTIALS=1` means merchant payments settle to the platform's provider account. Leave at `0` unless that is explicitly intended.

## 4. Database

Create a dedicated database and user:

```sql
CREATE DATABASE maxxfam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'maxxfam'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON maxxfam.* TO 'maxxfam'@'localhost';
FLUSH PRIVILEGES;
```

Run migrations and the safe seed:

```bash
php database/migrate.php
```

The migrations create the full schema, including the `email_verification_accounts` and `email_verification_messages` tables, and seed only the provider catalogue and system settings. No users, payments, transactions, dashboards or fake statistics are inserted.

## 5. Create the first administrator

```bash
php cron/create_admin.php "Platform Owner" owner@your-domain.example super_admin
```

The password is read from STDIN, must be at least 12 characters and is never written to logs. Sign in at `/admin/login.php` with the email and password; TOTP 2FA is optional and can be enabled from the admin menu. If 2FA is lost, rerun the same command to reset the account.

Add further administrators with roles `super_admin`, `admin` or `support`.

## 6. Web server configuration

### Apache — docroot pointing at `public/`

```apache
<VirtualHost *:443>
    ServerName payments.your-domain.example
    DocumentRoot /var/www/maxxfam/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/payments.your-domain.example/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/payments.your-domain.example/privkey.pem

    <Directory /var/www/maxxfam/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Defence in depth if the docroot is ever misconfigured
    <DirectoryMatch "^/var/www/maxxfam/(app|config|database|storage|cron|docs)">
        Require all denied
    </DirectoryMatch>

    ErrorLog  ${APACHE_LOG_DIR}/maxxfam-error.log
    CustomLog ${APACHE_LOG_DIR}/maxxfam-access.log combined
</VirtualHost>
```

The shipped `public/.htaccess` enables clean URLs and security headers.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name payments.your-domain.example;
    root /var/www/maxxfam/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/payments.your-domain.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/payments.your-domain.example/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) { deny all; }
    location ~* ^/(app|config|database|storage|cron|docs)/ { deny all; }
}
```

### cPanel / shared hosting

1. Upload the project outside `public_html`.
2. In **Domains → Document Root**, point the domain/subdomain to `/home/USER/maxxfam/public`.
   If the host cannot change the docroot, upload only the contents of `public/` into `public_html` and place `app/`, `config/`, `database/`, `storage/`, `cron/`, `docs/` one level above it, then update the `require` paths accordingly — this is not recommended because it is easy to misconfigure.
3. Create the MySQL database/user in cPanel and run `php database/migrate.php` through **Terminal** or a one-off cron.
4. Add cron jobs (section 8).
5. Enable AutoSSL / Let's Encrypt and force HTTPS.

## 7. SMTP

In **Admin → SMTP settings** configure host, port, encryption (`tls`/`ssl`), username, password, from name and from address. Use **Send test email** to verify real delivery. You can also set the same values in `.env`.

Emails are queued in `email_queue` and sent by `cron/process_email.php`. Failed messages retry with backoff up to six attempts. Never place SMTP secrets in frontend code.

## 8. Cron jobs

```cron
* * * * * /usr/bin/php /home/USER/maxxfam/cron/expire_orders.php  >> /home/USER/maxxfam/storage/logs/cron.log 2>&1
* * * * * /usr/bin/php /home/USER/maxxfam/cron/verify_payments.php >> /home/USER/maxxfam/storage/logs/cron.log 2>&1
* * * * * /usr/bin/php /home/USER/maxxfam/cron/retry_webhooks.php   >> /home/USER/maxxfam/storage/logs/cron.log 2>&1
* * * * * /usr/bin/php /home/USER/maxxfam/cron/process_email.php    >> /home/USER/maxxfam/storage/logs/cron.log 2>&1
* * * * * /usr/bin/php /home/USER/maxxfam/cron/process_email_verification.php >> /home/USER/maxxfam/storage/logs/cron.log 2>&1
```

Or use one scheduler:

```cron
* * * * * /usr/bin/php /home/USER/maxxfam/cron/run_all.php >> /home/USER/maxxfam/storage/logs/cron.log 2>&1
```

HTTP fallback (less secure; token required):

```text
https://payments.your-domain.example/cron/run_all.php?token=CRON_TOKEN
```

`cron/` is outside the public docroot, so HTTP invocation only works if you deliberately expose it. Prefer CLI.

## 9. Provider setup

1. Sign in as an administrator.
2. Open **Payment providers → FamGateway UPI**.
3. Enter the authorized merchant API key and optional webhook secret/base URL.
4. Configure the provider webhook URL at FamGateway if webhooks are enabled.
5. Merchants can additionally configure Gmail IMAP verification under **User → Integration**; see [EMAIL_VERIFICATION.md](EMAIL_VERIFICATION.md).
4. Click **Test connection**. The stored status becomes `Connected` only after an authenticated HTTP 200 from the provider.
5. In the FamGateway merchant dashboard, set the webhook URL to:
   `https://payments.your-domain.example/api/webhooks/famgateway.php`
6. Merchants can also store their own provider credential under **User → Integration**.

See [PROVIDER.md](PROVIDER.md).

## 10. HTTPS and headers

- Force HTTPS at the load balancer, Apache or Nginx.
- Keep `SESSION_SECURE=true`.
- The application sends `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and a content security policy nonce for inline scripts.
- Do not enable `APP_DEBUG` in production.

## 11. Backups and upgrades

Back up:

- the MySQL database,
- `.env` (including `APP_KEY`),
- `storage/invoices`,
- the application code.

Upgrade procedure:

1. Enable maintenance mode in **Admin → Maintenance**.
2. Back up the database and files.
3. Deploy the new code.
4. Run `php database/migrate.php` (migrations are idempotent).
5. Run `php tests/run_tests.php` against a staging database.
6. Disable maintenance mode.

## 12. Health checks

- Public status page: `/status`
- Merchant dashboard API usage and logs: `/user/api-logs.php`
- Admin security/audit log: `/admin/security-logs.php`, `/admin/activity.php`
- Queues: `email_queue`, `webhook_deliveries`, `payment_orders` (pending), `provider_webhook_events`

## 13. Troubleshooting

| Symptom | Check |
|---|---|
| 500 on every page | `storage/logs/app-*.log`, PHP error log, `APP_KEY` validity |
| Database connection refused | `.env` credentials, MySQL bind address/port, user grants |
| Provider never connects | API key authorization, outbound HTTPS/firewall, base URL |
| Webhooks not delivered | endpoint HTTPS + public reachability, DNS, SSRF restrictions, response code |
| Outbound HTTPS fails with `OpenSSL verify result: unable to get local issuer certificate (20)` | PHP/curl has no CA bundle. Set `curl.cainfo`/`openssl.cafile` in `php.ini` (or `CURL_CA_BUNDLE`) to a current `cacert.pem`; restart PHP-FPM. Production hosts normally ship one. |
| Emails not sent | SMTP credentials, port/firewall, `cron/process_email.php`, `email_queue.last_error` |
| Gmail IMAP test fails with certificate error | Set `IMAP_CA_BUNDLE` to a valid `cacert.pem`, or configure `openssl.cafile`; restart PHP-FPM |
| Gmail IMAP login rejected | Use a 16-character Google App Password, not the normal password; confirm 2-Step Verification |
| Email alerts are not verified | Sender allowlist, configured `@fam`/`@yesfam` UPI ID, exact amount, 12-digit UTR, and exactly one pending order must all match |
| 419 on forms | session cookie blocked, session expiry, reverse-proxy cookie settings |
| Admin locked out | `php cron/create_admin.php` recovery |
