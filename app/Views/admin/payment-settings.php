<?php
$settings = $settings ?? [];
?>
<div class="card max-w-3xl">
    <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Transaction Limits & Expiry</h2>
        <p class="text-[11px] text-slate-500">Global rules applied across payments, checkouts, and developer APIs.</p>
    </div>

    <form method="post" class="space-y-6 p-6">
        <?= csrf_field() ?>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="label" for="min_payment_amount">Minimum Payment (INR)</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">₹</span>
                    <input type="number" step="0.01" min="1" id="min_payment_amount" name="min_payment_amount"
                           value="<?= e($settings['min_payment_amount'] ?? '1.00') ?>" required class="input pl-8">
                </div>
                <p class="mt-1 text-[10.5px] text-slate-500">Minimum allowed amount for an individual transaction.</p>
            </div>

            <div>
                <label class="label" for="max_payment_amount">Maximum Payment (INR)</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">₹</span>
                    <input type="number" step="0.01" min="1" id="max_payment_amount" name="max_payment_amount"
                           value="<?= e($settings['max_payment_amount'] ?? '100000.00') ?>" required class="input pl-8">
                </div>
                <p class="mt-1 text-[10.5px] text-slate-500">Upper cap for standard UPI transaction creation.</p>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="label" for="payment_expiry_seconds">Dynamic Payment Expiry (seconds)</label>
                <input type="number" min="60" max="3600" id="payment_expiry_seconds" name="payment_expiry_seconds"
                       value="<?= e($settings['payment_expiry_seconds'] ?? '300') ?>" required class="input">
                <p class="mt-1 text-[10.5px] text-slate-500">Default: 300 seconds (5 minutes). Dynamic QRs expire after this.</p>
            </div>

            <div>
                <label class="label" for="default_link_expiry_days">Default Payment Link Expiry (days)</label>
                <input type="number" min="1" max="365" id="default_link_expiry_days" name="default_link_expiry_days"
                       value="<?= e($settings['default_link_expiry_days'] ?? '30') ?>" required class="input">
                <p class="mt-1 text-[10.5px] text-slate-500">Lifespan of reusable payment links if not overridden.</p>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="label" for="provider_poll_interval_seconds">Checkout Polling Interval (seconds)</label>
                <input type="number" min="2" max="15" id="provider_poll_interval_seconds" name="provider_poll_interval_seconds"
                       value="<?= e($settings['provider_poll_interval_seconds'] ?? '4') ?>" required class="input">
                <p class="mt-1 text-[10.5px] text-slate-500">Frequency of client status checks against provider verification.</p>
            </div>

            <div>
                <label class="label" for="invoice_prefix">Invoice Number Prefix</label>
                <input type="text" maxlength="10" id="invoice_prefix" name="invoice_prefix"
                       value="<?= e($settings['invoice_prefix'] ?? 'MF') ?>" required class="input uppercase font-mono">
                <p class="mt-1 text-[10.5px] text-slate-500">E.g. MF produces MF-2026-000001</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/5 dark:bg-white/[0.02]">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="allow_platform_default_credentials" value="1"
                       <?= ($settings['allow_platform_default_credentials'] ?? '0') === '1' ? 'checked' : '' ?>
                       class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <div>
                    <span class="text-[12px] font-semibold text-slate-900 dark:text-white">Allow Platform Default Credentials as Fallback</span>
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        When enabled, merchants who haven't entered their own provider API keys can use the admin-configured platform credentials. When disabled (recommended for production), every merchant must configure their own verified provider credentials.
                    </p>
                </div>
            </label>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primary">
                <i data-lucide="check" class="h-4 w-4"></i> Save Payment Rules
            </button>
        </div>
    </form>
</div>
