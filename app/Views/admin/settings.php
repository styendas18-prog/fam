<?php
$settings = $settings ?? [];
?>
<div class="card max-w-2xl">
    <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Branding & Platform Identity</h2>
        <p class="text-[11px] text-slate-500">Configures the user interface brand, support contact and registration behavior.</p>
    </div>

    <form method="post" class="space-y-5 p-6">
        <?= csrf_field() ?>

        <div>
            <label class="label" for="site_name">Brand Name</label>
            <input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name'] ?? 'MAXXfam') ?>" required class="input">
            <p class="mt-1 text-[10.5px] text-slate-500">Displayed in navigation headers, receipts, emails and checkout pages.</p>
        </div>

        <div>
            <label class="label" for="site_tagline">Platform Tagline</label>
            <input type="text" id="site_tagline" name="site_tagline" value="<?= e($settings['site_tagline'] ?? 'Smart Payments. Real Verification. Complete Control.') ?>" required class="input">
            <p class="mt-1 text-[10.5px] text-slate-500">Official tagline displayed on public landing pages and PDF receipts.</p>
        </div>

        <div>
            <label class="label" for="site_support_email">Official Platform Support Email</label>
            <input type="email" id="site_support_email" name="site_support_email" value="<?= e($settings['site_support_email'] ?? 'support@example.com') ?>" required class="input">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="terms_version">Terms of Service Version</label>
                <input type="text" id="terms_version" name="terms_version" value="<?= e($settings['terms_version'] ?? '1.0') ?>" class="input font-mono">
            </div>
            <div>
                <label class="label" for="privacy_version">Privacy Policy Version</label>
                <input type="text" id="privacy_version" name="privacy_version" value="<?= e($settings['privacy_version'] ?? '1.0') ?>" class="input font-mono">
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/5 dark:bg-white/[0.02]">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="registration_enabled" value="1"
                       <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                       class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <div>
                    <span class="text-[12px] font-semibold text-slate-900 dark:text-white">Allow Public Merchant Registration</span>
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        When enabled, new users can sign up for a merchant account via /register.php. When disabled, only administrators can invite or provision accounts.
                    </p>
                </div>
            </label>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primary">
                <i data-lucide="check" class="h-4 w-4"></i> Save System Configuration
            </button>
        </div>
    </form>
</div>
