<?php
use App\Core\Flash;
use App\Core\Security;
use App\Core\Settings;

$nonce = Security::nonce();
$flash = Flash::consume();
$siteName = (string) Settings::get('site_name', 'MAXXfam');
$pageTitle = $title ?? 'Sign in';
$authHeading = $authHeading ?? 'Welcome back';
$authCopy = $authCopy ?? 'Sign in to manage payments, keys and verification.';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#070d1c">
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(asset('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script nonce="<?= e($nonce) ?>">try{if(localStorage.getItem('maxxfam-theme')==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
    <script defer src="<?= e(asset('assets/vendor/lucide.min.js')) ?>"></script>
    <script defer src="<?= e(asset('assets/js/app.js')) ?>"></script>
</head>
<body class="min-h-screen bg-[#f6f8fc] text-slate-800 dark:bg-ink-950 dark:text-slate-200 <?= e($bodyClass) ?>">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    <aside class="ambient-hero relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
        <div class="hero-grid absolute inset-0"></div>
        <div class="relative">
            <a href="<?= e(url('/')) ?>" class="inline-flex items-center">
                <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-10 w-auto">
            </a>
        </div>
        <div class="relative max-w-xl">
            <span class="eyebrow"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Provider-verified payments</span>
            <h1 class="mt-7 text-[42px] font-bold leading-[1.08] tracking-tight text-white xl:text-[50px]">Payments that only succeed when the money is real.</h1>
            <p class="mt-5 max-w-lg text-[15px] leading-7 text-slate-400">MAXXfam creates genuine UPI orders, verifies every payment server-to-server, checks the UTR for duplicates, and delivers signed webhooks. No client-side success flags.</p>
            <div class="mt-9 grid gap-3 sm:grid-cols-2">
                <div class="glass rounded-xl p-4">
                    <i data-lucide="scan-line" class="h-5 w-5 text-brand-300"></i>
                    <p class="mt-3 text-[12px] font-semibold text-white">Dynamic QR + hosted checkout</p>
                    <p class="mt-1 text-[11px] leading-5 text-slate-400">Provider-issued QR and payment session for every order.</p>
                </div>
                <div class="glass rounded-xl p-4">
                    <i data-lucide="fingerprint" class="h-5 w-5 text-brand-300"></i>
                    <p class="mt-3 text-[12px] font-semibold text-white">Atomic UTR verification</p>
                    <p class="mt-1 text-[11px] leading-5 text-slate-400">Row locks and unique UTR constraints stop replays.</p>
                </div>
            </div>
        </div>
        <div class="relative flex items-center gap-3 text-[11px] text-slate-500">
            <i data-lucide="shield-check" class="h-4 w-4 text-emerald-400"></i>
            Independent software. Not affiliated with FamPay, NPCI or any bank.
        </div>
    </aside>

    <main class="flex min-h-screen flex-col">
        <div class="flex items-center justify-between px-5 py-5 sm:px-8 lg:justify-end">
            <a href="<?= e(url('/')) ?>" class="lg:hidden"><img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-8 w-auto"></a>
            <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Back to site
            </a>
        </div>
        <div class="flex flex-1 items-center justify-center px-5 pb-12 sm:px-8">
            <div class="w-full max-w-[430px] page-enter">
                <h2 class="text-[28px] font-bold tracking-tight text-slate-900 dark:text-white"><?= e($authHeading) ?></h2>
                <p class="mt-2 text-[13px] leading-6 text-slate-500 dark:text-slate-400"><?= e($authCopy) ?></p>
                <div class="mt-7"><?= $content ?></div>
            </div>
        </div>
        <footer class="px-5 pb-6 text-center text-[11px] text-slate-400 sm:px-8 dark:text-slate-500">
            &copy; <?= date('Y') ?> <?= e($siteName) ?> · <a class="hover:text-slate-600 dark:hover:text-slate-300" href="<?= e(url('/terms')) ?>">Terms</a> · <a class="hover:text-slate-600 dark:hover:text-slate-300" href="<?= e(url('/privacy')) ?>">Privacy</a>
        </footer>
    </main>
</div>
<script nonce="<?= e($nonce) ?>" type="application/json" id="flash-data"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</body>
</html>
