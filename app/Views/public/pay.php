<?php
$link = $link ?? [];
$fixed = $link['amount'] !== null;
$flash = \App\Core\Flash::consume();
$siteName = (string) \App\Core\Settings::get('site_name', 'MAXXfam');
?>
<div class="min-h-screen bg-[#f4f7fb] dark:bg-ink-950">
    <header class="border-b border-slate-200 bg-white dark:border-white/10 dark:bg-ink-900">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-5">
            <a href="<?= e(url('/')) ?>"><img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-8 w-auto [filter:invert(1)] dark:[filter:none]"></a>
            <span class="inline-flex items-center gap-1.5 text-[10.5px] font-semibold text-slate-500"><i data-lucide="shield-check" class="h-3.5 w-3.5 text-emerald-500"></i> Secure payment link</span>
        </div>
    </header>
    <main class="mx-auto grid max-w-5xl gap-6 px-5 py-10 lg:grid-cols-[1fr_360px] lg:items-start">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:p-8 dark:border-white/10 dark:bg-ink-900">
            <p class="text-[10.5px] font-bold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-300">Payment request</p>
            <h1 class="mt-3 text-[26px] font-bold tracking-tight text-slate-900 dark:text-white"><?= e($link['title']) ?></h1>
            <?php if (!empty($link['description'])): ?><p class="mt-2 text-[12.5px] leading-6 text-slate-500 dark:text-slate-400"><?= e($link['description']) ?></p><?php endif; ?>
            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3"><span class="text-[11px] text-slate-500">Amount</span><span class="text-[22px] font-bold text-slate-900 dark:text-white"><?= $fixed ? e(money((float) $link['amount'])) : 'Customer enters amount' ?></span></div>
                <?php if (!$fixed): ?><p class="mt-1 text-[10px] text-slate-500">Allowed: <?= e(money((float) $link['min_amount'])) ?> – <?= e(money((float) $link['max_amount'])) ?></p><?php endif; ?>
                <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 text-[10.5px] dark:border-white/10"><span class="text-slate-500">Merchant</span><span class="font-semibold text-slate-700 dark:text-slate-300"><?= e($link['business_name']) ?></span></div>
            </div>

            <form method="post" action="<?= e(url('/pay/' . $link['slug'])) ?>" class="mt-6 space-y-4" data-loading>
                <?= csrf_field() ?>
                <?php if (!$fixed): ?>
                <div><label for="amount" class="label">Amount (INR)</label><input id="amount" name="amount" type="number" step="0.01" min="<?= e((string) $link['min_amount']) ?>" max="<?= e((string) $link['max_amount']) ?>" required class="input text-[18px] font-bold" placeholder="0.00"></div>
                <?php endif; ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="customer_name" class="label">Your name <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_name" name="customer_name" maxlength="120" class="input" placeholder="Full name"></div>
                    <div><label for="customer_email" class="label">Email <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_email" name="customer_email" type="email" maxlength="190" class="input" placeholder="you@example.com"></div>
                </div>
                <div><label for="customer_phone" class="label">Phone <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_phone" name="customer_phone" maxlength="30" class="input" placeholder="+91"></div>
                <button type="submit" class="btn-primary w-full">Continue to secure payment <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
                <p class="text-center text-[10px] leading-5 text-slate-500">You will receive a genuine provider UPI QR. A payment is confirmed only after server-side verification.</p>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-ink-900">
                <h2 class="text-[12px] font-semibold text-slate-900 dark:text-white">What happens next</h2>
                <ol class="mt-3 space-y-3">
                    <?php foreach (['A provider payment session is created.', 'Scan the QR or use hosted checkout.', 'The server verifies amount and UTR.', 'You see a real success or failure state.'] as $i => $step): ?>
                    <li class="flex items-start gap-2.5 text-[10.5px] leading-5 text-slate-500 dark:text-slate-400"><span class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-brand-500/10 font-mono text-[8px] font-bold text-brand-600 dark:text-brand-300"><?= $i + 1 ?></span><?= e($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-400/20 dark:bg-emerald-400/[0.06]">
                <p class="flex items-center gap-2 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"><i data-lucide="shield-check" class="h-4 w-4"></i> No card or PIN is collected here</p>
                <p class="mt-1.5 text-[10px] leading-5 text-emerald-700/70 dark:text-emerald-200/60">Payment happens in your UPI app. This page never asks for a UPI PIN, OTP or bank credentials.</p>
            </div>
            <?php if (!empty($link['support_email'])): ?><p class="text-center text-[10.5px] text-slate-500">Questions? <a href="mailto:<?= e($link['support_email']) ?>" class="font-semibold text-brand-600 dark:text-brand-300"><?= e($link['support_email']) ?></a></p><?php endif; ?>
        </aside>
    </main>
</div>
<script type="application/json" id="flash-data"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
