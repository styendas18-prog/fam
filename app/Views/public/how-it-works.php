<?php
$title = 'How it works';
$nav = 'how';
$eyebrow = 'The flow';
$heading = 'A payment only succeeds when the provider proves it.';
$copy = 'MAXXfam separates order creation, customer payment and authoritative verification. Each stage has a real state and an audit trail.';
require BASE_PATH . '/app/Views/public/_page-hero.php';
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[1.1fr_.9fr] lg:gap-16">
            <div class="space-y-4">
                <?php foreach ([
                    ['01', 'Your server creates an order', 'Call POST /api/v1/payments/create with an amount, reference and optional customer details. MAXXfam validates limits, checks the provider is enabled, and creates a local Pending order.', ['Amount validation', 'Merchant status check', 'Idempotent reference']],
                    ['02', 'The provider issues a real payment request', 'We call the provider’s create-order endpoint with your authorized API key. The provider returns an order ID, payable amount, dynamic QR URL, optional hosted checkout and expiry.', ['Dynamic QR', 'Hosted checkout', 'Provider order ID']],
                    ['03', 'The customer pays over UPI', 'The customer scans the provider QR or opens the hosted checkout. Nothing on the browser can declare success. The order remains Pending while the payment is unconfirmed.', ['No client-side success', 'Countdown expiry', 'Cancel is explicit']],
                    ['04', 'MAXXfam verifies server-to-server', 'Signed webhooks wake the verification engine. It also polls pending orders. The provider status endpoint is the single source of truth for status, amount and UTR.', ['HMAC check first', 'Status re-query', 'Amount match']],
                    ['05', 'The state machine commits the truth', 'Only a matching, unique UTR flips the order to Success. A row lock serialises concurrent callbacks. The transaction, invoice, notification and webhook are written atomically.', ['Unique UTR', 'Atomic commit', 'Signed event']],
                ] as [$step, $headingText, $copyText, $tags]): ?>
                <article class="relative rounded-2xl border border-white/[0.07] bg-white/[0.025] p-6 sm:p-7">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="font-mono text-[11px] font-bold text-brand-400">STEP <?= e($step) ?></span>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($tags as $tag): ?><span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[9.5px] font-medium text-slate-400"><?= e($tag) ?></span><?php endforeach; ?>
                        </div>
                    </div>
                    <h2 class="mt-4 text-[17px] font-semibold text-white"><?= e($headingText) ?></h2>
                    <p class="mt-2 text-[12px] leading-6 text-slate-400"><?= e($copyText) ?></p>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="space-y-5">
                <div class="rounded-2xl border border-white/[0.07] bg-[#0a1224] p-6">
                    <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-slate-400">Order state machine</h2>
                    <div class="mt-5 space-y-3 font-mono text-[11px]">
                        <?php foreach ([
                            ['pending', 'bg-amber-400', 'text-amber-300', 'Order created; provider session active.'],
                            ['success', 'bg-emerald-400', 'text-emerald-300', 'Provider confirmed amount + unique UTR.'],
                            ['failed', 'bg-rose-400', 'text-rose-300', 'Provider or creation failure.'],
                            ['expired', 'bg-slate-400', 'text-slate-300', 'Window elapsed with no verified payment.'],
                            ['cancelled', 'bg-slate-400', 'text-slate-300', 'Merchant/customer cancelled before payment.'],
                        ] as [$state, $dotClass, $textClass, $copyText]): ?>
                        <div class="flex items-start gap-3 rounded-lg border border-white/[0.06] bg-white/[0.02] px-3 py-2.5">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full <?= e($dotClass) ?>"></span>
                            <div><p class="font-semibold <?= e($textClass) ?>"><?= e($state) ?></p><p class="mt-0.5 font-sans text-[10.5px] leading-5 text-slate-500"><?= e($copyText) ?></p></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-4 border-t border-white/[0.07] pt-4 text-[10.5px] leading-5 text-slate-500">Transitions into success are only possible from the verification service. There is no public endpoint that can force success.</p>
                </div>

                <div class="rounded-2xl border border-brand-400/20 bg-brand-500/[0.06] p-6">
                    <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-brand-200">Webhook is a trigger, not proof</h2>
                    <p class="mt-3 text-[12px] leading-6 text-slate-300">We verify the HMAC signature to authenticate the sender. We then re-query the provider’s authoritative status before changing any payment state. A forged or replayed payload cannot mark an order successful.</p>
                    <code class="mt-4 block overflow-x-auto rounded-lg border border-white/10 bg-black/30 p-3 font-mono text-[10px] text-slate-300">X-FamGateway-Signature: hmac_sha256(raw_body, api_key)</code>
                </div>

                <div class="rounded-2xl border border-white/[0.07] bg-white/[0.025] p-6">
                    <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-slate-400">Need the endpoints?</h2>
                    <div class="mt-4 flex flex-col gap-2">
                        <a href="<?= e(url('/api-reference')) ?>" class="btn-primary btn-sm justify-between">API reference <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
                        <a href="<?= e(url('/docs')) ?>" class="btn-secondary btn-sm justify-between">Integration guide <i data-lucide="book-open" class="h-3.5 w-3.5"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
