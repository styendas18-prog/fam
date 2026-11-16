<?php
$amount = old('amount');
$provider = $providers[0] ?? null;
$providerState = $provider['merchant_connection_status'] ?? ($provider['connection_status'] ?? 'not_configured');
$stateMeta = status_meta((string) $providerState);
?>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
            <h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Payment details</h2>
            <p class="mt-0.5 text-[10.5px] text-slate-500">MAXXfam validates the amount, creates a local Pending order, then requests a real provider payment session.</p>
        </div>
        <?php if (!$configured): ?>
        <div class="m-5 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-400/20 dark:bg-amber-400/[0.06]">
            <div class="flex items-start gap-3">
                <i data-lucide="plug-zap" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-300"></i>
                <div><p class="text-[12px] font-semibold text-amber-800 dark:text-amber-200">Integration Not Configured</p><p class="mt-1 text-[10.5px] leading-5 text-amber-700/80 dark:text-amber-200/70">Save an authorized provider API key and run a real connection test before creating live payments. Order creation will fail honestly until then.</p><a href="<?= e(url('/user/integration.php')) ?>" class="btn-secondary btn-sm mt-3">Open integration settings</a></div>
            </div>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/user/create-payment.php')) ?>" class="space-y-4 p-5" data-loading>
            <?= csrf_field() ?>
            <div>
                <label for="amount" class="label">Amount (INR)</label>
                <div class="relative"><span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[15px] font-semibold text-slate-400">₹</span><input id="amount" name="amount" type="number" step="0.01" min="1" max="100000" required value="<?= e($amount) ?>" class="input pl-8 text-[20px] font-bold" placeholder="0.00" inputmode="decimal"></div>
                <p class="hint">Between <?= e(money((float) \App\Core\Settings::get('min_payment_amount', 1))) ?> and <?= e(money((float) \App\Core\Settings::get('max_payment_amount', 100000))) ?> per order.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="customer_name" class="label">Customer name <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_name" name="customer_name" maxlength="120" value="<?= old('customer_name') ?>" class="input" placeholder="Aarav Mehta"></div>
                <div><label for="customer_email" class="label">Customer email <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_email" name="customer_email" type="email" maxlength="190" value="<?= old('customer_email') ?>" class="input" placeholder="aarav@example.com"></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="customer_phone" class="label">Customer phone <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_phone" name="customer_phone" maxlength="30" value="<?= old('customer_phone') ?>" class="input" placeholder="+91"></div>
                <div><label for="customer_reference" class="label">Your reference <span class="font-normal text-slate-400">(optional)</span></label><input id="customer_reference" name="customer_reference" maxlength="120" value="<?= old('customer_reference') ?>" class="input font-mono" placeholder="INV-2041"></div>
            </div>
            <div><label for="description" class="label">Description <span class="font-normal text-slate-400">(optional)</span></label><input id="description" name="description" maxlength="500" value="<?= old('description') ?>" class="input" placeholder="Annual plan"></div>
            <div><label for="success_redirect_url" class="label">Success redirect URL <span class="font-normal text-slate-400">(optional)</span></label><input id="success_redirect_url" name="success_redirect_url" type="url" maxlength="500" value="<?= old('success_redirect_url') ?>" class="input font-mono text-[11px]" placeholder="https://your-site.com/thank-you"><p class="hint">HTTPS only. The customer is redirected after provider verification.</p></div>
            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
                <p class="flex items-start gap-2 text-[10px] leading-5 text-slate-500"><i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500"></i> The order stays Pending until the provider confirms amount and UTR.</p>
                <button type="submit" class="btn-primary shrink-0" <?= $configured ? '' : 'disabled' ?>>Create provider order <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
            </div>
        </form>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[12.5px] font-bold text-slate-900 dark:text-white">Provider state</h3></div>
            <div class="space-y-3 p-5">
                <div class="flex items-center justify-between gap-3"><span class="text-[11px] text-slate-500">Provider</span><span class="text-[11.5px] font-semibold text-slate-800 dark:text-slate-200"><?= e($provider['name'] ?? 'None installed') ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-[11px] text-slate-500">Connection</span><span class="<?= e($stateMeta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($stateMeta['dot']) ?>"></span> <?= e(str_replace('_', ' ', $providerState)) ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-[11px] text-slate-500">Credential</span><span class="font-mono text-[10.5px] text-slate-600 dark:text-slate-300"><?= $configured ? '•••• ' . e((string) ($provider['key_last4'] ?? '')) : 'Not saved' ?></span></div>
                <?php if ($configured): ?><p class="rounded-lg bg-slate-50 px-3 py-2 text-[10px] leading-4 text-slate-500 dark:bg-white/[0.03]">Order calls are made server-side with your encrypted credential. The key is never sent to the browser.</p><?php endif; ?>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-900">
            <h3 class="text-[12.5px] font-bold text-slate-900 dark:text-white">What gets created</h3>
            <ul class="mt-3 space-y-2.5 text-[10.5px] leading-5 text-slate-500">
                <?php foreach ([
                    'A local order with a public MF… ID and separate checkout token.',
                    'A provider order ID and payable amount checked against your request.',
                    'A genuine dynamic QR URL and optional hosted checkout.',
                    'An expiry window, after which the order can expire.',
                ] as $line): ?>
                <li class="flex items-start gap-2"><i data-lucide="check" class="mt-0.5 h-3 w-3 shrink-0 text-emerald-500"></i><?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-900">
            <h3 class="text-[12.5px] font-bold text-slate-900 dark:text-white">Need an API instead?</h3>
            <p class="mt-1.5 text-[10.5px] leading-5 text-slate-500">Create orders from your backend with a scoped API key.</p>
            <a href="<?= e(url('/user/documentation.php')) ?>" class="btn-secondary btn-sm mt-3 w-full">View API guide</a>
        </div>
    </div>
</div>
