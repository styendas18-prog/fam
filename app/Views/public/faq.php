<?php
$title = 'FAQ';
$nav = 'faq';
$eyebrow = 'Answers';
$heading = 'Frequently asked questions.';
$copy = 'Straight answers about providers, verification, money movement and what this platform does not do.';
require BASE_PATH . '/app/Views/public/_page-hero.php';
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[.36fr_.64fr] lg:gap-16">
            <aside class="space-y-4">
                <div class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5">
                    <h2 class="text-[12px] font-semibold text-white">Still stuck?</h2>
                    <p class="mt-2 text-[11px] leading-5 text-slate-400">Open a support ticket. Include your merchant code and order ID — never send a full API key.</p>
                    <a href="<?= e(url('/contact')) ?>" class="btn-primary btn-sm mt-4 w-full">Contact support</a>
                </div>
                <div class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5">
                    <h2 class="text-[12px] font-semibold text-white">For developers</h2>
                    <div class="mt-3 space-y-2">
                        <a href="<?= e(url('/docs')) ?>" class="flex items-center justify-between text-[11px] text-slate-400 hover:text-white">Integration guide <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
                        <a href="<?= e(url('/api-reference')) ?>" class="flex items-center justify-between text-[11px] text-slate-400 hover:text-white">API reference <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
                        <a href="<?= e(url('/status')) ?>" class="flex items-center justify-between text-[11px] text-slate-400 hover:text-white">System status <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
                    </div>
                </div>
            </aside>

            <div class="space-y-3" data-accordion-group>
                <?php foreach ([
                    ['Is MAXXfam a payment provider or a bank?', 'No. MAXXfam is independent payment software that connects to an authorized payment provider account. Funds settle according to your provider agreement. We are not affiliated with FamPay, NPCI, any bank or any wallet.'],
                    ['How is a payment actually verified?', 'We call the provider’s documented status endpoint server-to-server. A payment becomes Success only when the provider returns success for the same provider order ID, with a matching amount and a valid 12-digit UTR that has not been used before.'],
                    ['Can a browser redirect mark my payment successful?', 'No. Browser redirects only change what the customer sees. The server state machine ignores client claims. There is no public endpoint that can set an order to success.'],
                    ['What is a UTR and why does uniqueness matter?', 'A UTR (Unique Transaction Reference) identifies a UPI payment. Reusing one UTR across orders is a common replay attack. MAXXfam enforces uniqueness in the database and inside a row-locked transaction.'],
                    ['Do webhooks mark payments successful?', 'No. A valid HMAC signature authenticates the webhook sender. We then re-query the provider for authoritative status before changing any order. Forged payloads cannot grant success.'],
                    ['What happens if the provider is down when I create an order?', 'Order creation fails honestly, the reason is logged, and the order is recorded as failed. We never display a QR or success state for an order the provider did not create.'],
                    ['Why does my provider show “Not configured”?', 'No credential has been saved for that provider and merchant, or the platform default is disabled. Add an authorized API key under Integration, then run a real connection test.'],
                    ['Can I process refunds from MAXXfam?', 'The current provider adapter does not implement refunds because the provider does not document a refund API. Any refund must be handled in the merchant’s own UPI/provider tooling and recorded manually.'],
                    ['How are my credentials stored?', 'Provider credentials are encrypted with AES-256-GCM using an application key that is never stored in the database. Merchant API keys are stored only as SHA-256 hashes and are displayed once.'],
                    ['Do you show demo transactions in my dashboard?', 'No. Production seeds contain no users, orders, transactions or statistics. Every figure is computed from rows created by real activity.'],
                    ['Is there a sandbox?', 'The platform supports a test database and local testing, but genuine success requires a real provider sandbox or authorized merchant account. We cannot fake provider confirmations.'],
                    ['How do I secure my admin account?', 'Admin login uses a password, and administrators may enable TOTP two-factor authentication from the admin menu. If the authenticator is lost, recovery is CLI-only.'],
                ] as [$q, $a]): ?>
                <div class="rounded-xl border border-white/[0.08] bg-white/[0.025]" data-accordion>
                    <button type="button" data-accordion-trigger aria-expanded="false" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-[12.5px] font-semibold text-white">
                        <?= e($q) ?>
                        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-500"></i>
                    </button>
                    <div class="grid grid-rows-[0fr] overflow-hidden transition-all" data-accordion-panel>
                        <div class="min-h-0"><p class="border-t border-white/[0.06] px-5 py-4 text-[11.5px] leading-6 text-slate-400"><?= e($a) ?></p></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
