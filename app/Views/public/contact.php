<?php
$title = 'Contact support';
$nav = '';
$supportEmail = (string) \App\Core\Settings::get('site_support_email', 'support@example.com');
?>
<section class="relative overflow-hidden border-b border-white/[0.06] bg-[#050a15] py-16 sm:py-20">
    <div class="absolute inset-0 bg-grid opacity-40"></div>
    <div class="container-mf relative grid gap-10 lg:grid-cols-[.9fr_1.1fr] lg:items-start">
        <div>
            <span class="eyebrow">Support</span>
            <h1 class="mt-5 text-[38px] font-bold leading-[1.08] tracking-[-0.03em] text-white sm:text-[46px]">Talk to a human who understands verification.</h1>
            <p class="mt-4 max-w-lg text-[14px] leading-7 text-slate-400">Open a ticket for integration help, account questions, provider connectivity issues or security reports. Include your merchant code and order ID where relevant.</p>
            <div class="mt-8 space-y-3">
                <div class="flex items-start gap-3 rounded-xl border border-white/[0.07] bg-white/[0.025] p-4">
                    <i data-lucide="mail" class="mt-0.5 h-4 w-4 text-brand-300"></i>
                    <div><p class="text-[11px] font-semibold text-white">Email</p><a href="mailto:<?= e($supportEmail) ?>" class="text-[11px] text-slate-400 hover:text-white"><?= e($supportEmail) ?></a></div>
                </div>
                <div class="flex items-start gap-3 rounded-xl border border-white/[0.07] bg-white/[0.025] p-4">
                    <i data-lucide="timer" class="mt-0.5 h-4 w-4 text-brand-300"></i>
                    <div><p class="text-[11px] font-semibold text-white">Response window</p><p class="text-[11px] text-slate-400">Support tickets are reviewed during business hours. Urgent security reports are prioritized.</p></div>
                </div>
                <div class="rounded-xl border border-amber-400/20 bg-amber-400/[0.05] p-4">
                    <p class="text-[10.5px] leading-5 text-amber-200">Never send full API keys, provider credentials, OTPs, UPI PINs or bank passwords. We will never ask for them.</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/[0.08] bg-white/[0.03] p-6 sm:p-7">
            <h2 class="text-[15px] font-bold text-white">Open a ticket</h2>
            <p class="mt-1 text-[11px] text-slate-400">A guest ticket is created and a ticket number is shown after submission.</p>
            <form method="post" action="<?= e(url('/contact')) ?>" class="mt-5 space-y-4" data-loading>
                <?= csrf_field() ?>
                <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="name" class="label text-slate-300">Your name</label><input id="name" name="name" required maxlength="120" value="<?= old('name') ?>" class="input border-white/10 bg-white/5 text-white placeholder:text-slate-500" placeholder="Your name"></div>
                    <div><label for="email" class="label text-slate-300">Email</label><input id="email" name="email" type="email" required value="<?= old('email') ?>" class="input border-white/10 bg-white/5 text-white placeholder:text-slate-500" placeholder="you@business.com"></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="category" class="label text-slate-300">Category</label><select id="category" name="category" class="input border-white/10 bg-white/5 text-white"><?php foreach (['general' => 'General', 'integration' => 'Integration', 'payment' => 'Payment issue', 'account' => 'Account', 'security' => 'Security', 'billing' => 'Billing'] as $value => $label): ?><option value="<?= e($value) ?>" class="bg-ink-900" <?= old('category') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div><label for="priority" class="label text-slate-300">Priority</label><select id="priority" name="priority" class="input border-white/10 bg-white/5 text-white"><?php foreach (['normal' => 'Normal', 'low' => 'Low', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?><option value="<?= e($value) ?>" class="bg-ink-900" <?= old('priority') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                </div>
                <div><label for="subject" class="label text-slate-300">Subject</label><input id="subject" name="subject" required maxlength="190" value="<?= old('subject') ?>" class="input border-white/10 bg-white/5 text-white placeholder:text-slate-500" placeholder="Short summary"></div>
                <div><label for="message" class="label text-slate-300">Message</label><textarea id="message" name="message" required minlength="10" maxlength="5000" rows="5" class="input border-white/10 bg-white/5 text-white placeholder:text-slate-500" placeholder="What happened? Include order IDs, timestamps and exact error codes. Do not include secrets."><?= old('message') ?></textarea></div>
                <button type="submit" class="btn-primary w-full">Submit ticket <i data-lucide="send" class="h-4 w-4"></i></button>
                <p class="text-[10px] leading-5 text-slate-500">By submitting, you agree to our <a href="<?= e(url('/privacy')) ?>" class="text-brand-300 hover:text-brand-200">privacy policy</a>. Ticket content is stored to handle your request.</p>
            </form>
        </div>
    </div>
</section>
