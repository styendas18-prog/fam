<?php
$title = 'Features';
$nav = 'features';
$eyebrow = 'Platform';
$heading = 'Built around proof, not promises.';
$copy = 'Every feature exists to answer one question reliably: did a real, matching, previously unused payment arrive for this order?';
require BASE_PATH . '/app/Views/public/_page-hero.php';
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ([
                ['qr-code', 'Dynamic provider QR', 'Each order receives a genuine provider-issued UPI QR and optional hosted checkout URL. QR images are never generated locally as a pretend payment target.'],
                ['shield-check', 'Authoritative verification', 'Success is written only after a server-to-server provider call confirms status, order ID, amount, currency and a valid 12-digit UTR.'],
                ['fingerprint', 'Unique UTR enforcement', 'A database-level unique constraint plus SELECT … FOR UPDATE serialises concurrent callbacks. A UTR can settle one order, once.'],
                ['webhook', 'Signed webhooks with retries', 'Events are delivered with an HMAC-SHA256 signature over the exact raw body. Failed deliveries back off through a configurable retry schedule.'],
                ['key-round', 'Scoped API keys', 'Issue, rotate and revoke keys with per-key scopes. Only a SHA-256 hash is stored; the raw key is shown exactly once.'],
                ['link-2', 'Payment links', 'Create shareable fixed or open-amount links with expiry windows, success redirects and per-link order history.'],
                ['bar-chart-3', 'SQL-derived analytics', 'Volume, success rate, failure reasons, provider performance and API usage come directly from your transaction rows. No seeded charts.'],
                ['file-text', 'Invoices & exports', 'Verified payments generate numbered PDF receipts. Reports export to CSV and PDF from the same filtered dataset.'],
                ['bell', 'Real notifications', 'In-app notifications and SMTP email are triggered by actual events — verification, failure, expiry, API key creation and support replies.'],
                ['activity', 'Honest provider status', 'A provider shows Not Configured, Testing, Connected, Connection Failed or Disabled based on a real authenticated connectivity check.'],
                ['globe-lock', 'SSRF-safe callbacks', 'Webhook URLs are resolved and checked against private and reserved address ranges before any outbound request is made.'],
                ['scan-face', 'Admin 2FA and RBAC', 'Administrators may enable TOTP two-factor authentication. Roles gate every privileged action and all admin changes are written to an activity log.'],
            ] as [$icon, $headingText, $copyText]): ?>
            <article class="public-card">
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-brand-400/20 bg-brand-500/10 text-brand-300"><i data-lucide="<?= e($icon) ?>" class="h-[18px] w-[18px]"></i></span>
                <h2 class="mt-4 text-[14.5px] font-semibold text-white"><?= e($headingText) ?></h2>
                <p class="mt-2 text-[11.5px] leading-6 text-slate-400"><?= e($copyText) ?></p>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="mt-12 grid gap-5 lg:grid-cols-2">
            <div class="rounded-2xl border border-white/[0.07] bg-white/[0.025] p-6 sm:p-8">
                <h2 class="text-xl font-bold text-white">What MAXXfam will never do</h2>
                <ul class="mt-5 space-y-3.5">
                    <?php foreach ([
                        'Mark a payment successful from a browser redirect or frontend flag.',
                        'Show a green “Connected” provider badge without a successful authenticated request.',
                        'Fabricate dashboard totals, demo transactions or sample revenue in production.',
                        'Store raw provider credentials or API keys in plaintext.',
                        'Claim affiliation with FamPay, NPCI, a bank or a payment provider.',
                        'Offer a refund “success” when the upstream provider has no refund API.',
                    ] as $line): ?>
                    <li class="flex items-start gap-3 text-[12px] leading-6 text-slate-300"><i data-lucide="x-circle" class="mt-0.5 h-4 w-4 shrink-0 text-rose-400"></i> <?= e($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="rounded-2xl border border-emerald-400/15 bg-emerald-400/[0.04] p-6 sm:p-8">
                <h2 class="text-xl font-bold text-white">What “verified” means here</h2>
                <ol class="mt-5 space-y-4">
                    <?php foreach ([
                        'The provider returns status=success for the exact provider order ID we stored.',
                        'The provider-confirmed amount equals the payable amount in our database.',
                        'The UTR is exactly 12 digits and has never been used by another order.',
                        'A row lock prevents two callbacks from both writing success.',
                        'The transaction, order state, invoice, notification and webhook are committed together.',
                    ] as $i => $line): ?>
                    <li class="flex items-start gap-3 text-[12px] leading-6 text-slate-300">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-400/15 font-mono text-[9px] font-bold text-emerald-300"><?= $i + 1 ?></span>
                        <?= e($line) ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <a href="<?= e(url('/how-it-works')) ?>" class="mt-6 inline-flex items-center gap-1.5 text-[12px] font-semibold text-emerald-300 hover:text-emerald-200">Read the verification flow <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
            </div>
        </div>
    </div>
</section>
