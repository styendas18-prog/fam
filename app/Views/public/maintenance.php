<?php
$title = 'Scheduled maintenance';
$nav = '';
$message = (string) \App\Core\Settings::get('maintenance_message', 'MAXXfam is undergoing scheduled maintenance. Please try again shortly.');
$supportEmail = (string) \App\Core\Settings::get('site_support_email', 'support@example.com');
?>
<section class="ambient-hero relative flex min-h-[calc(100vh-72px)] items-center overflow-hidden py-20">
    <div class="hero-grid absolute inset-0"></div>
    <div class="container-mf relative">
        <div class="mx-auto max-w-xl rounded-2xl border border-white/10 bg-ink-850/80 p-8 text-center shadow-float backdrop-blur sm:p-10">
            <span class="grid mx-auto h-14 w-14 place-items-center rounded-2xl border border-amber-400/20 bg-amber-400/10 text-amber-300"><i data-lucide="wrench" class="h-6 w-6"></i></span>
            <h1 class="mt-6 text-3xl font-bold text-white">We’re doing maintenance</h1>
            <p class="mt-3 text-[13px] leading-7 text-slate-400"><?= e($message) ?></p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="<?= e(url('/status')) ?>" class="btn-secondary">System status</a>
                <a href="mailto:<?= e($supportEmail) ?>" class="btn-primary">Contact support</a>
            </div>
            <p class="mt-6 text-[10.5px] text-slate-500">Existing payments are unaffected. Verification and webhook workers continue to run.</p>
        </div>
    </div>
</section>
