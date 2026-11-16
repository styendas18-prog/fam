<?php
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Security;
use App\Core\Settings;

$siteName = (string) Settings::get('site_name', 'MAXXfam');
$tagline = (string) Settings::get('site_tagline', 'Smart Payments. Real Verification. Complete Control.');
$supportEmail = (string) Settings::get('site_support_email', 'support@example.com');
$currentUser = Auth::user();
$nonce = Security::nonce();
$flash = Flash::consume();
$nav = $activeNav ?? '';
$pageTitle = $title ?? $siteName;
$metaDescription = $metaDescription ?? 'MAXXfam is a developer-focused UPI payment platform with server-side verification, signed webhooks, and honest integration status.';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#070d1c">
    <meta property="og:title" content="<?= e($pageTitle) ?> · <?= e($siteName) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= e(asset('assets/images/logo.svg')) ?>">
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(asset('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script nonce="<?= e($nonce) ?>">try{var t=localStorage.getItem('maxxfam-theme');if(t==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
    <?php if (!empty($useCharts)): ?>
    <script defer src="<?= e(asset('assets/vendor/chart.umd.min.js')) ?>"></script>
    <?php endif; ?>
    <script defer src="<?= e(asset('assets/vendor/lucide.min.js')) ?>"></script>
    <script defer src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <?php foreach (($pageScripts ?? []) as $script): ?>
    <script defer src="<?= e(asset($script)) ?>"></script>
    <?php endforeach; ?>
</head>
<body class="min-h-screen bg-ink-900 text-slate-100 selection:bg-brand-500/30 <?= e($bodyClass) ?>">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-slate-900">Skip to content</a>

<header class="fixed inset-x-0 top-0 z-50 border-b border-white/[0.06] bg-ink-900/80 backdrop-blur-xl transition-all" id="site-header">
    <div class="container-mf flex h-[72px] items-center justify-between gap-4">
        <a href="<?= e(url('/')) ?>" class="flex shrink-0 items-center text-white" aria-label="<?= e($siteName) ?> home">
            <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-9 w-auto text-white">
        </a>
        <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary">
            <a href="<?= e(url('/features')) ?>" class="public-link <?= $nav === 'features' ? 'text-white' : '' ?>">Features</a>
            <a href="<?= e(url('/how-it-works')) ?>" class="public-link <?= $nav === 'how' ? 'text-white' : '' ?>">How it works</a>
            <a href="<?= e(url('/docs')) ?>" class="public-link <?= $nav === 'docs' ? 'text-white' : '' ?>">Developers</a>
            <a href="<?= e(url('/pricing')) ?>" class="public-link <?= $nav === 'pricing' ? 'text-white' : '' ?>">Pricing</a>
            <a href="<?= e(url('/status')) ?>" class="public-link <?= $nav === 'status' ? 'text-white' : '' ?>">Status</a>
            <a href="<?= e(url('/faq')) ?>" class="public-link <?= $nav === 'faq' ? 'text-white' : '' ?>">FAQ</a>
        </nav>
        <div class="hidden items-center gap-3 lg:flex">
            <?php if ($currentUser): ?>
                <a href="<?= e(url('/user/dashboard.php')) ?>" class="btn-primary btn-sm">
                    <i data-lucide="layout-dashboard" class="h-3.5 w-3.5"></i> Dashboard
                </a>
            <?php else: ?>
                <a href="<?= e(url('/login.php')) ?>" class="text-sm font-semibold text-slate-300 transition hover:text-white">Sign in</a>
                <a href="<?= e(url('/register.php')) ?>" class="btn-primary btn-sm">Start building <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i></a>
            <?php endif; ?>
        </div>
        <button type="button" class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-white lg:hidden" id="mobile-menu-button" aria-label="Open navigation" aria-expanded="false">
            <i data-lucide="menu" class="h-5 w-5"></i>
        </button>
    </div>
    <div class="hidden border-t border-white/[0.06] bg-ink-900 lg:hidden" id="mobile-menu">
        <nav class="container-mf flex flex-col gap-1 py-4" aria-label="Mobile">
            <a href="<?= e(url('/features')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Features</a>
            <a href="<?= e(url('/how-it-works')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">How it works</a>
            <a href="<?= e(url('/docs')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Developer docs</a>
            <a href="<?= e(url('/api-reference')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">API reference</a>
            <a href="<?= e(url('/pricing')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Pricing</a>
            <a href="<?= e(url('/status')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">System status</a>
            <a href="<?= e(url('/faq')) ?>" class="rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">FAQ</a>
            <div class="my-2 h-px bg-white/10"></div>
            <?php if ($currentUser): ?>
                <a href="<?= e(url('/user/dashboard.php')) ?>" class="btn-primary">Open dashboard</a>
            <?php else: ?>
                <a href="<?= e(url('/login.php')) ?>" class="btn-secondary">Sign in</a>
                <a href="<?= e(url('/register.php')) ?>" class="btn-primary">Create account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main" class="<?= $nav === 'home' ? '' : 'pt-[72px]' ?>">
    <?= $content ?>
</main>

<footer class="border-t border-white/[0.07] bg-[#050a15]">
    <div class="container-mf py-14">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-9 w-auto">
                <p class="mt-4 max-w-sm text-[13px] leading-6 text-slate-400"><?= e($tagline) ?></p>
                <p class="mt-4 max-w-sm text-[12px] leading-6 text-slate-500">Independent payment software. Not affiliated with FamPay, NPCI, or any bank. Use only provider accounts and payment integrations you are authorized to operate.</p>
                <div class="mt-5 flex items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/[0.07] px-3 py-1.5 text-[11px] font-medium text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Server-side verification</span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[11px] font-medium text-slate-300"><i data-lucide="lock-keyhole" class="h-3 w-3"></i> AES-256-GCM</span>
                </div>
            </div>
            <div>
                <h3 class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Product</h3>
                <ul class="mt-4 space-y-3 text-[13px] text-slate-400">
                    <li><a class="hover:text-white" href="<?= e(url('/features')) ?>">Features</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/how-it-works')) ?>">How it works</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/pricing')) ?>">Pricing</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/status')) ?>">System status</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Developers</h3>
                <ul class="mt-4 space-y-3 text-[13px] text-slate-400">
                    <li><a class="hover:text-white" href="<?= e(url('/docs')) ?>">Documentation</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/api-reference')) ?>">API reference</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/docs#webhooks')) ?>">Webhooks</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/docs#security')) ?>">Security</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Company</h3>
                <ul class="mt-4 space-y-3 text-[13px] text-slate-400">
                    <li><a class="hover:text-white" href="<?= e(url('/contact')) ?>">Contact support</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/faq')) ?>">FAQ</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/privacy')) ?>">Privacy policy</a></li>
                    <li><a class="hover:text-white" href="<?= e(url('/terms')) ?>">Terms &amp; conditions</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-12 flex flex-col gap-4 border-t border-white/[0.07] pt-6 text-[11.5px] text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
            <div class="flex flex-wrap items-center gap-5">
                <a href="mailto:<?= e($supportEmail) ?>" class="hover:text-slate-300"><?= e($supportEmail) ?></a>
                <span class="inline-flex items-center gap-1.5"><i data-lucide="shield-check" class="h-3.5 w-3.5 text-brand-400"></i> Built for verified payments</span>
            </div>
        </div>
    </div>
</footer>

<script nonce="<?= e($nonce) ?>" type="application/json" id="flash-data"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script nonce="<?= e($nonce) ?>">
(function(){
  var b=document.getElementById('mobile-menu-button'),m=document.getElementById('mobile-menu');
  if(b&&m){b.addEventListener('click',function(){var open=m.classList.toggle('hidden')===false;b.setAttribute('aria-expanded',open?'true':'false');b.innerHTML='<i data-lucide="'+(open?'x':'menu')+'" class="h-5 w-5"></i>';if(window.lucide)lucide.createIcons();});}
  var h=document.getElementById('site-header');
  if(h){window.addEventListener('scroll',function(){if(window.scrollY>8){h.classList.add('shadow-2xl','shadow-black/20')}else{h.classList.remove('shadow-2xl','shadow-black/20')}},{passive:true});}
})();
</script>
</body>
</html>
