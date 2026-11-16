<?php
$title = 'Pricing';
$nav = 'pricing';
$eyebrow = 'Pricing';
$heading = 'Simple platform pricing. Provider fees stay transparent.';
$copy = 'MAXXfam charges for the software layer, not for hiding provider costs. Your authorized provider may charge its own fees under your separate agreement.';
require BASE_PATH . '/app/Views/public/_page-hero.php';
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-5 lg:grid-cols-3">
            <?php foreach ([
                ['Starter', '₹0', 'per month', 'For developers validating a real integration.', ['1 merchant account', 'Genuine provider orders', 'Server-side verification', 'Signed webhooks', 'API logs (30 days)', 'Email support'], false],
                ['Growth', '₹0', 'platform fee during launch', 'For live merchants processing verified UPI.', ['Unlimited payment links', 'Scoped API keys + rotation', 'CSV and PDF reports', 'Invoice PDFs', 'Priority support queue', 'Provider connection testing'], true],
                ['Scale', 'Custom', 'operations & support', 'For teams needing onboarding and review.', ['Multi-provider roadmap', 'Manual reconciliation support', 'Custom webhook retry policy', 'Security review assistance', 'Dedicated onboarding', 'Named support contact'], false],
            ] as [$name, $price, $period, $blurb, $features, $featured]): ?>
            <article class="relative flex flex-col rounded-2xl border <?= $featured ? 'border-brand-400/40 bg-brand-500/[0.07] shadow-glow' : 'border-white/[0.08] bg-white/[0.025]' ?> p-6 sm:p-7">
                <?php if ($featured): ?><span class="absolute -top-3 left-6 rounded-full bg-brand-500 px-3 py-1 text-[9.5px] font-bold uppercase tracking-[0.13em] text-white">Most popular</span><?php endif; ?>
                <h2 class="text-[14px] font-semibold text-white"><?= e($name) ?></h2>
                <div class="mt-3 flex items-end gap-2"><span class="text-[38px] font-bold tracking-tight text-white"><?= e($price) ?></span><span class="pb-1 text-[11px] text-slate-500"><?= e($period) ?></span></div>
                <p class="mt-3 min-h-[40px] text-[11.5px] leading-5 text-slate-400"><?= e($blurb) ?></p>
                <ul class="mt-5 flex-1 space-y-2.5 border-t border-white/[0.07] pt-5">
                    <?php foreach ($features as $feature): ?>
                    <li class="flex items-start gap-2.5 text-[11.5px] text-slate-300"><i data-lucide="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-400"></i> <?= e($feature) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= e($name === 'Scale' ? url('/contact') : url('/register.php')) ?>" class="<?= $featured ? 'btn-primary' : 'btn-secondary' ?> mt-6 w-full"><?= $name === 'Scale' ? 'Contact sales' : 'Get started' ?></a>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="mt-10 grid gap-4 sm:grid-cols-3">
            <?php foreach ([
                ['receipt-indian-rupee', 'Provider fees are separate', 'Any UPI or provider fee is governed by your own agreement with the provider. MAXXfam does not mark those fees up invisibly.'],
                ['shield-check', 'No charge for failed orders', 'Platform pricing applies to verified payments. Failed, expired and cancelled orders do not become success states.'],
                ['file-check-2', 'Cancel anytime', 'No long-term contract on Starter or Growth. Export your data and revoke API keys whenever you choose.'],
            ] as [$icon, $headingText, $copyText]): ?>
            <div class="rounded-xl border border-white/[0.07] bg-white/[0.025] p-5">
                <i data-lucide="<?= e($icon) ?>" class="h-4 w-4 text-brand-300"></i>
                <h3 class="mt-3 text-[12px] font-semibold text-white"><?= e($headingText) ?></h3>
                <p class="mt-1.5 text-[10.5px] leading-5 text-slate-500"><?= e($copyText) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-12 rounded-2xl border border-white/[0.07] bg-[#050a15] p-6 sm:p-8">
            <h2 class="text-lg font-bold text-white">Common pricing questions</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2" data-accordion-group>
                <?php foreach ([
                    ['Do I need my own provider account?', 'Yes. MAXXfam connects to a payment provider using credentials you are authorized to use. We do not provide a shared merchant account or pooled funds.'],
                    ['Is there a per-transaction platform fee?', 'Growth has a zero platform fee during launch. Provider charges, taxes and settlement terms remain between you and your provider.'],
                    ['What happens during provider downtime?', 'Order creation fails honestly and is recorded as failed. Existing pending orders continue to be verified when the provider is reachable.'],
                    ['Can I export my data?', 'Yes. Verified transactions and reports export to CSV and PDF from your dashboard.'],
                ] as [$q, $a]): ?>
                <div class="rounded-xl border border-white/[0.07] bg-white/[0.025]" data-accordion>
                    <button type="button" data-accordion-trigger aria-expanded="false" class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-[12px] font-semibold text-white"><?= e($q) ?><i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-500"></i></button>
                    <div class="grid grid-rows-[0fr] overflow-hidden transition-all" data-accordion-panel><div class="min-h-0"><p class="border-t border-white/[0.06] px-4 py-3.5 text-[11px] leading-5 text-slate-400"><?= e($a) ?></p></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
