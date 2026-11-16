<?php
$title = 'UPI payments with server-side verification';
$nav = 'home';
$bodyClass = 'bg-ink-900';
?>
<section class="ambient-hero relative overflow-hidden pb-24 pt-[132px] sm:pb-28 sm:pt-[148px]">
    <div class="hero-grid absolute inset-0"></div>
    <div class="container-mf relative">
        <div class="grid items-center gap-14 lg:grid-cols-[1.05fr_.95fr] lg:gap-10">
            <div class="page-enter">
                <span class="eyebrow"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Built on provider-verified status</span>
                <h1 class="mt-6 max-w-[15ch] text-[42px] font-bold leading-[1.03] tracking-[-0.035em] text-white sm:text-[56px] xl:text-[64px]">
                    UPI payments you can <span class="text-gradient">actually verify.</span>
                </h1>
                <p class="mt-6 max-w-xl text-[15.5px] leading-8 text-slate-400 sm:text-[16.5px]">
                    MAXXfam creates genuine UPI orders, issues dynamic QR codes, and confirms every payment with the provider's authoritative API. A payment is marked successful only when the amount, order and 12-digit UTR all match.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="<?= e(url('/register.php')) ?>" class="btn-primary h-12 px-6 text-[14px]">Create free account <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                    <a href="<?= e(url('/docs')) ?>" class="btn-dark h-12 px-6 text-[14px]"><i data-lucide="terminal" class="h-4 w-4"></i> Read the docs</a>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-[12px] text-slate-400">
                    <span class="inline-flex items-center gap-2"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-400"></i> No fabricated success states</span>
                    <span class="inline-flex items-center gap-2"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-400"></i> Signed HMAC webhooks</span>
                    <span class="inline-flex items-center gap-2"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-400"></i> AES-256-GCM credentials</span>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-[430px] lg:mx-0 lg:ml-auto">
                <div class="absolute -inset-8 rounded-[40px] bg-brand-500/10 blur-3xl"></div>
                <div class="relative overflow-hidden rounded-[26px] border border-white/10 bg-[#0a1224]/90 shadow-float backdrop-blur-xl">
                    <div class="flex items-center justify-between border-b border-white/[0.07] px-5 py-4">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500/15 text-brand-300"><i data-lucide="qr-code" class="h-4 w-4"></i></span>
                            <div>
                                <p class="text-[11px] font-semibold text-white">Payment order</p>
                                <p class="font-mono text-[9.5px] text-slate-500">MF8C41D9A62E7B5F0</p>
                            </div>
                        </div>
                        <span class="badge-warning"><span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span> Pending</span>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-[1fr_150px]">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-500">Amount payable</p>
                            <p class="mt-1.5 text-[32px] font-bold tracking-tight text-white">₹<span data-count="2499.00" data-format="money">₹2,499.00</span></p>
                            <div class="mt-4 space-y-2.5 text-[11px]">
                                <div class="flex justify-between gap-3"><span class="text-slate-500">Reference</span><span class="font-mono text-slate-300">INV-2041</span></div>
                                <div class="flex justify-between gap-3"><span class="text-slate-500">Method</span><span class="text-slate-300">UPI · Dynamic QR</span></div>
                                <div class="flex justify-between gap-3"><span class="text-slate-500">Expires in</span><span class="font-mono text-amber-300" data-countdown="<?= e(gmdate('c', time() + 245)) ?>">04:05</span></div>
                            </div>
                            <div class="mt-5 rounded-xl border border-white/[0.07] bg-white/[0.03] p-3">
                                <p class="flex items-center gap-2 text-[10.5px] font-semibold text-emerald-300"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Server-authoritative status</p>
                                <p class="mt-1.5 text-[10px] leading-4 text-slate-500">This illustration is not payable. Live QR codes are issued by the provider for real orders.</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-center justify-center rounded-2xl bg-white p-3">
                            <div class="demo-qr w-full" data-demo-qr aria-hidden="true"></div>
                            <p class="mt-2 text-center text-[8.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Illustrative only</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/[0.07] bg-white/[0.02] px-5 py-3">
                        <span class="flex items-center gap-1.5 text-[10px] text-slate-500"><i data-lucide="refresh-cw" class="h-3 w-3"></i> Polling provider every 4s</span>
                        <span class="flex items-center gap-1.5 text-[10px] font-semibold text-brand-300">Verify status <i data-lucide="arrow-right" class="h-3 w-3"></i></span>
                    </div>
                </div>
                <div class="absolute -left-4 top-1/3 hidden rounded-xl border border-emerald-400/20 bg-[#081a16]/95 px-3.5 py-2.5 shadow-xl backdrop-blur sm:flex sm:items-center sm:gap-2.5">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-400/15 text-emerald-300"><i data-lucide="check" class="h-3.5 w-3.5"></i></span>
                    <div><p class="text-[10px] font-bold text-emerald-200">UTR verified</p><p class="font-mono text-[8.5px] text-emerald-400/70">4281 9930 7741</p></div>
                </div>
            </div>
        </div>

        <div class="mt-20 grid gap-px overflow-hidden rounded-2xl border border-white/[0.07] bg-white/[0.07] sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ([
                ['icon' => 'shield-check', 'title' => 'Authoritative checks', 'copy' => 'Provider API, not browser signals'],
                ['icon' => 'fingerprint', 'title' => 'UTR uniqueness', 'copy' => 'One payment, one reference'],
                ['icon' => 'webhook', 'title' => 'Signed delivery', 'copy' => 'HMAC-SHA256 + retries'],
                ['icon' => 'lock-keyhole', 'title' => 'Encrypted keys', 'copy' => 'AES-256-GCM at rest'],
            ] as $item): ?>
            <div class="bg-ink-900/90 px-5 py-5">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="<?= e($item['icon']) ?>" class="h-4 w-4 text-brand-400"></i>
                    <p class="text-[12px] font-semibold text-white"><?= e($item['title']) ?></p>
                </div>
                <p class="mt-1.5 text-[11px] text-slate-500"><?= e($item['copy']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="border-t border-white/[0.06] bg-[#050a15] py-20 sm:py-24">
    <div class="container-mf">
        <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:gap-20">
            <div>
                <span class="eyebrow">Why MAXXfam</span>
                <h2 class="section-title mt-5">Everything a payment page should never fake.</h2>
                <p class="section-copy">Most UPI integrations are thin screens around a QR image. MAXXfam is built around the part that matters: confirming that a real, matching, unused payment actually happened.</p>
                <a href="<?= e(url('/how-it-works')) ?>" class="btn-dark mt-7">See the verification flow <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ([
                    ['shield-check', 'Provider-authoritative status', 'Every success is confirmed through a server-to-server status call. Webhooks trigger verification; they never grant it.'],
                    ['badge-indian-rupee', 'Amount and order matching', 'Provider-confirmed payable amount and provider order ID must match the stored order exactly before success is written.'],
                    ['copy-check', 'Duplicate UTR protection', 'A unique database constraint plus row locks guarantee a UTR can verify at most one order, even under concurrent callbacks.'],
                    ['key-round', 'Hashed API keys', 'Merchant API keys are shown once and stored only as SHA-256 hashes. Provider credentials are encrypted with AES-256-GCM.'],
                    ['activity', 'Honest integration state', 'Providers read Not Configured, Testing, Connected, Connection Failed or Disabled — never an invented green badge.'],
                    ['file-check-2', 'Real receipts', 'Invoices and CSV/PDF reports are generated from verified transaction rows, not from template placeholders.'],
                ] as [$icon, $heading, $copy]): ?>
                <div class="public-card">
                    <span class="grid h-10 w-10 place-items-center rounded-xl border border-brand-400/20 bg-brand-500/10 text-brand-300"><i data-lucide="<?= e($icon) ?>" class="h-[18px] w-[18px]"></i></span>
                    <h3 class="mt-4 text-[14px] font-semibold text-white"><?= e($heading) ?></h3>
                    <p class="mt-2 text-[11.5px] leading-6 text-slate-400"><?= e($copy) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="border-t border-white/[0.06] bg-ink-900 py-20 sm:py-24">
    <div class="container-mf">
        <div class="mx-auto max-w-2xl text-center">
            <span class="eyebrow">How it works</span>
            <h2 class="section-title mt-5">From order to verified money in four steps.</h2>
            <p class="section-copy">No redirect tricks, no client-side confirmation, no “assume success”.</p>
        </div>
        <div class="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ([
                ['01', 'Create the order', 'Your server calls our API with an amount and reference. We create the order and request a genuine provider payment session.'],
                ['02', 'Present the QR', 'The customer scans the provider-issued UPI QR or uses the hosted checkout. The order remains Pending.'],
                ['03', 'Verify server-side', 'Our engine polls and reacts to signed callbacks, then asks the provider for the authoritative status, amount and UTR.'],
                ['04', 'Settle the truth', 'Only a matching, unique UTR flips the order to Success. You get a signed webhook and a real receipt.'],
            ] as [$step, $heading, $copy]): ?>
            <div class="relative rounded-2xl border border-white/[0.07] bg-white/[0.025] p-6">
                <span class="font-mono text-[11px] font-bold text-brand-400"><?= e($step) ?></span>
                <h3 class="mt-4 text-[14px] font-semibold text-white"><?= e($heading) ?></h3>
                <p class="mt-2 text-[11.5px] leading-6 text-slate-400"><?= e($copy) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-10 flex justify-center">
            <a href="<?= e(url('/how-it-works')) ?>" class="btn-secondary">Explore the full flow</a>
        </div>
    </div>
</section>

<section class="border-t border-white/[0.06] bg-[#050a15] py-20 sm:py-24">
    <div class="container-mf grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div>
            <span class="eyebrow">Developer first</span>
            <h2 class="section-title mt-5">One clean API. Predictable states.</h2>
            <p class="section-copy">Create orders, fetch status, list payments and manage links over a versioned REST API. Every response is JSON, every error has a stable code, and every API call is logged for your account.</p>
            <ul class="mt-7 space-y-3">
                <?php foreach ([
                    'Bearer API keys with per-key scopes',
                    'Idempotent order creation with a unique reference',
                    'Signed webhooks with automatic retries',
                    'Sandboxable status checks and real UTR validation',
                    'Honest provider status — never a fabricated connection',
                ] as $line): ?>
                <li class="flex items-start gap-3 text-[12.5px] text-slate-300"><i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400"></i> <?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="<?= e(url('/api-reference')) ?>" class="btn-primary">API reference <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i></a>
                <a href="<?= e(url('/docs')) ?>" class="btn-dark">Quickstart guide</a>
            </div>
        </div>
        <div class="code-block shadow-float">
            <div class="flex items-center justify-between border-b border-white/[0.07] px-5 py-3">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-rose-400/70"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-400/70"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70"></span>
                    <span class="ml-2 font-mono text-[10px] text-slate-500">create-payment.sh</span>
                </div>
                <span class="rounded-md border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-[9px] text-slate-400">POST</span>
            </div>
            <pre class="!p-0"><code class="block p-5"><span class="text-slate-500"># Create a real provider order</span>
curl -X POST <?= e(url('/api/v1/payments/create')) ?> \
  -H <span class="text-emerald-300">"Authorization: Bearer mf_live_…"</span> \
  -H <span class="text-emerald-300">"Content-Type: application/json"</span> \
  -d <span class="text-amber-200">'{
    "amount": 2499.00,
    "customer_name": "Aarav Mehta",
    "customer_email": "aarav@example.com",
    "customer_reference": "INV-2041",
    "description": "Annual plan"
  }'</span>

<span class="text-slate-500"># → 201 Created</span>
{
  <span class="text-sky-300">"success"</span>: true,
  <span class="text-sky-300">"data"</span>: {
    <span class="text-sky-300">"order_id"</span>: <span class="text-emerald-300">"MF8C41D9A62E7B5F0"</span>,
    <span class="text-sky-300">"status"</span>: <span class="text-emerald-300">"pending"</span>,
    <span class="text-sky-300">"amount"</span>: <span class="text-amber-200">"2499.00"</span>,
    <span class="text-sky-300">"qr_url"</span>: <span class="text-emerald-300">"https://…"</span>,
    <span class="text-sky-300">"checkout_url"</span>: <span class="text-emerald-300">"https://…"</span>
  }
}</code></pre>
        </div>
    </div>
</section>

<section class="border-t border-white/[0.06] bg-ink-900 py-20 sm:py-24">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[1fr_.9fr] lg:items-center">
            <div>
                <span class="eyebrow">Security by construction</span>
                <h2 class="section-title mt-5">Designed for the failure cases.</h2>
                <p class="section-copy">Replay attempts, duplicate UTRs, forged webhooks and SSRF-prone callback URLs are treated as first-class threats — not edge notes.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ([
                    ['lock-keyhole', 'AES-256-GCM', 'Provider credentials encrypted'],
                    ['timer-reset', 'Rate limits', 'Login and API throttling'],
                    ['globe-lock', 'SSRF guard', 'Private destinations blocked'],
                    ['scan-face', 'Admin 2FA', 'Optional TOTP for admin access'],
                ] as [$icon, $title, $copy]): ?>
                <div class="rounded-xl border border-white/[0.07] bg-white/[0.03] p-4">
                    <i data-lucide="<?= e($icon) ?>" class="h-4 w-4 text-brand-300"></i>
                    <p class="mt-2.5 text-[12px] font-semibold text-white"><?= e($title) ?></p>
                    <p class="mt-0.5 text-[10.5px] text-slate-500"><?= e($copy) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="border-t border-white/[0.06] bg-[#050a15] py-20 sm:py-24">
    <div class="container-mf">
        <div class="overflow-hidden rounded-[28px] border border-brand-400/20 bg-gradient-to-br from-brand-700/25 via-ink-850 to-ink-900 p-8 sm:p-12">
            <div class="grid items-center gap-8 lg:grid-cols-[1fr_auto]">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Start with honest payments.</h2>
                    <p class="mt-3 max-w-xl text-[14px] leading-7 text-slate-400">Create an account, connect an authorized provider key, and test the integration. Nothing is marked successful until the provider proves it.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="<?= e(url('/register.php')) ?>" class="btn-primary h-12 px-6">Create free account</a>
                    <a href="<?= e(url('/contact')) ?>" class="btn-dark h-12 px-6">Talk to us</a>
                </div>
            </div>
        </div>
    </div>
</section>
