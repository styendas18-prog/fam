<?php
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Security;
use App\Core\Settings;
use App\Models\MerchantModel;
use App\Models\NotificationModel;

$currentUser = Auth::user();
if (!$currentUser) {
    App\Core\Response::redirect(url('/login.php'));
}
$merchant = MerchantModel::findByUser((int) $currentUser['id']);
$siteName = (string) Settings::get('site_name', 'MAXXfam');
$supportEmail = (string) Settings::get('site_support_email', 'support@example.com');
$unreadCount = NotificationModel::unreadCount((int) $currentUser['id']);
$nonce = Security::nonce();
$flash = Flash::consume();
$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$headerActions = $headerActions ?? '';
$pageScripts = $pageScripts ?? [];
$bodyClass = $bodyClass ?? '';

$groups = [
    'Workspace' => [
        ['key' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard', 'href' => '/user/dashboard.php'],
        ['key' => 'create', 'label' => 'Create payment', 'icon' => 'circle-plus', 'href' => '/user/create-payment.php'],
        ['key' => 'transactions', 'label' => 'Transactions', 'icon' => 'arrow-left-right', 'href' => '/user/transactions.php'],
        ['key' => 'links', 'label' => 'Payment links', 'icon' => 'link-2', 'href' => '/user/payment-links.php'],
    ],
    'Developers' => [
        ['key' => 'api-keys', 'label' => 'API keys', 'icon' => 'key-round', 'href' => '/user/api-keys.php'],
        ['key' => 'webhooks', 'label' => 'Webhooks', 'icon' => 'webhook', 'href' => '/user/webhooks.php'],
        ['key' => 'integration', 'label' => 'Integration', 'icon' => 'plug-zap', 'href' => '/user/integration.php'],
        ['key' => 'api-logs', 'label' => 'API logs', 'icon' => 'terminal-square', 'href' => '/user/api-logs.php'],
        ['key' => 'docs', 'label' => 'Documentation', 'icon' => 'book-open', 'href' => '/user/documentation.php'],
    ],
    'Business' => [
        ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bar-chart-3', 'href' => '/user/analytics.php'],
        ['key' => 'invoices', 'label' => 'Invoices', 'icon' => 'file-text', 'href' => '/user/invoices.php'],
        ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'href' => '/user/notifications.php', 'badge' => $unreadCount],
        ['key' => 'support', 'label' => 'Support', 'icon' => 'life-buoy', 'href' => '/user/support.php'],
    ],
    'Account' => [
        ['key' => 'plans', 'label' => 'Plans', 'icon' => 'credit-card', 'href' => '/user/plans.php'],
        ['key' => 'profile', 'label' => 'Profile', 'icon' => 'user-round', 'href' => '/user/profile.php'],
        ['key' => 'security', 'label' => 'Security', 'icon' => 'shield-check', 'href' => '/user/security.php'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <meta name="theme-color" content="#070d1c">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(asset('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script nonce="<?= e($nonce) ?>">try{if(localStorage.getItem('maxxfam-theme')==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
    <?php if (!empty($useCharts)): ?><script defer src="<?= e(asset('assets/vendor/chart.umd.min.js')) ?>"></script><?php endif; ?>
    <?php foreach ($pageScripts as $script): ?>
    <script defer src="<?= e(asset($script)) ?>"></script>
    <?php endforeach; ?>
    <script defer src="<?= e(asset('assets/vendor/lucide.min.js')) ?>"></script>
    <script defer src="<?= e(asset('assets/js/app.js')) ?>"></script>
</head>
<body class="min-h-screen bg-[#f5f7fb] text-slate-800 dark:bg-ink-950 dark:text-slate-200 <?= e($bodyClass) ?>">
<a href="#dashboard-main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-slate-900">Skip to content</a>

<div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden"></div>

<aside id="app-sidebar" class="sidebar-scroll fixed inset-y-0 left-0 z-50 flex w-[264px] -translate-x-full flex-col overflow-y-auto border-r border-white/[0.06] bg-ink-900 px-4 pb-6 pt-5 transition-transform duration-300 lg:translate-x-0">
    <div class="flex items-center justify-between px-2">
        <a href="<?= e(url('/')) ?>" class="flex items-center" aria-label="<?= e($siteName) ?> home">
            <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-8 w-auto">
        </a>
        <button type="button" data-sidebar-close class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-white/5 hover:text-white lg:hidden" aria-label="Close navigation">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
    </div>

    <a href="<?= e(url('/user/create-payment.php')) ?>" class="btn-primary mt-6 w-full justify-start gap-2.5 text-[13px]">
        <i data-lucide="plus" class="h-4 w-4"></i> New payment
        <span class="ml-auto rounded-md border border-white/20 bg-white/10 px-1.5 py-0.5 font-mono text-[9.5px] text-white/80">N</span>
    </a>

    <nav class="mt-5" aria-label="Dashboard">
        <?php foreach ($groups as $group => $items): ?>
        <p class="sidebar-group"><?= e($group) ?></p>
        <div class="space-y-0.5">
            <?php foreach ($items as $item): ?>
            <a href="<?= e(url($item['href'])) ?>" class="sidebar-link <?= $active === $item['key'] ? 'is-active' : '' ?>" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                <i data-lucide="<?= e($item['icon']) ?>"></i>
                <span class="truncate"><?= e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="ml-auto rounded-full bg-brand-500/20 px-2 py-0.5 text-[10px] font-bold text-brand-200"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="mt-auto pt-6">
        <div class="rounded-2xl border border-white/[0.07] bg-white/[0.03] p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-300">
                <i data-lucide="shield-check" class="h-3.5 w-3.5 text-emerald-400"></i>
                Server-side verification
            </div>
            <p class="mt-2 text-[11px] leading-5 text-slate-500">Success is only recorded after the provider confirms the UTR and amount.</p>
            <a href="<?= e(url('/status')) ?>" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold text-brand-300 hover:text-brand-200">System status <i data-lucide="arrow-up-right" class="h-3 w-3"></i></a>
        </div>
        <a href="<?= e(url('/user/support.php')) ?>" class="mt-3 flex items-center gap-2 px-2 text-[11.5px] text-slate-500 hover:text-slate-300">
            <i data-lucide="mail" class="h-3.5 w-3.5"></i> <?= e($supportEmail) ?>
        </a>
    </div>
</aside>

<div class="min-h-screen lg:pl-[264px]">
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-white/[0.07] dark:bg-ink-950/80">
        <div class="flex h-[68px] items-center gap-3 px-4 sm:px-6 xl:px-8">
            <button type="button" data-sidebar-open class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 lg:hidden" aria-label="Open navigation">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-[17px] font-bold tracking-tight text-slate-900 dark:text-white"><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle !== ''): ?>
                <p class="truncate text-[11.5px] text-slate-500 dark:text-slate-400"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-500 md:inline-flex dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                    <span class="nav-dot"></span> <?= $merchant && $merchant['status'] === 'active' ? 'Live mode' : 'Account review' ?>
                </span>
                <button type="button" data-theme-toggle class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/5" aria-label="Toggle theme">
                    <i data-lucide="moon" data-theme-icon="moon" class="hidden h-[18px] w-[18px]"></i>
                    <i data-lucide="sun" data-theme-icon="sun" class="h-[18px] w-[18px]"></i>
                </button>
                <a href="<?= e(url('/user/notifications.php')) ?>" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/5" aria-label="Notifications">
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    <?php if ($unreadCount > 0): ?><span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-ink-950"></span><?php endif; ?>
                </a>
                <div class="relative" data-dropdown>
                    <button type="button" data-dropdown-trigger class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-2.5 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10" aria-haspopup="menu">
                        <span class="avatar h-8 w-8 text-[11px]"><?= e(initials((string) $currentUser['name'])) ?></span>
                        <span class="hidden text-left sm:block">
                            <span class="block max-w-[130px] truncate text-[12px] font-semibold text-slate-800 dark:text-white"><?= e($currentUser['name']) ?></span>
                            <span class="block text-[10px] text-slate-500"><?= e($merchant['merchant_code'] ?? 'Merchant') ?></span>
                        </span>
                        <i data-lucide="chevron-down" class="hidden h-3.5 w-3.5 text-slate-400 sm:block"></i>
                    </button>
                    <div data-dropdown-panel class="absolute right-0 mt-2 hidden w-60 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-ink-800" role="menu">
                        <div class="border-b border-slate-100 px-3 py-2.5 dark:border-white/10">
                            <p class="truncate text-[12px] font-semibold text-slate-800 dark:text-white"><?= e($currentUser['name']) ?></p>
                            <p class="truncate text-[11px] text-slate-500"><?= e($currentUser['email']) ?></p>
                        </div>
                        <a href="<?= e(url('/user/profile.php')) ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><i data-lucide="user-round" class="h-3.5 w-3.5"></i> Profile & business</a>
                        <a href="<?= e(url('/user/security.php')) ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Security</a>
                        <a href="<?= e(url('/user/support.php')) ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><i data-lucide="life-buoy" class="h-3.5 w-3.5"></i> Support</a>
                        <form method="post" action="<?= e(url('/logout.php')) ?>" class="border-t border-slate-100 pt-1 dark:border-white/10">
                            <?= csrf_field() ?>
                            <button class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[12px] text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"><i data-lucide="log-out" class="h-3.5 w-3.5"></i> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="dashboard-main" class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 xl:px-8">
        <?= $content ?>
    </main>

    <footer class="mx-auto flex w-full max-w-[1440px] flex-col gap-2 px-4 py-6 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 xl:px-8 dark:text-slate-500">
        <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. Independent payment software.</p>
        <div class="flex flex-wrap gap-4">
            <a href="<?= e(url('/docs')) ?>" class="hover:text-slate-600 dark:hover:text-slate-300">Docs</a>
            <a href="<?= e(url('/terms')) ?>" class="hover:text-slate-600 dark:hover:text-slate-300">Terms</a>
            <a href="<?= e(url('/privacy')) ?>" class="hover:text-slate-600 dark:hover:text-slate-300">Privacy</a>
            <a href="<?= e(url('/status')) ?>" class="hover:text-slate-600 dark:hover:text-slate-300">Status</a>
        </div>
    </footer>
</div>

<div id="confirm-modal" class="fixed inset-0 z-[90] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div data-confirm-backdrop class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-white/10 dark:bg-ink-800">
        <div class="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300"><i data-lucide="alert-triangle" class="h-5 w-5"></i></div>
        <h2 id="confirm-title" data-confirm-title class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Confirm action</h2>
        <p data-confirm-copy class="mt-2 text-[13px] leading-6 text-slate-500 dark:text-slate-400">This action cannot be undone.</p>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-confirm-cancel class="btn-secondary">Cancel</button>
            <button type="button" data-confirm-proceed class="btn-danger">Continue</button>
        </div>
    </div>
</div>

<script nonce="<?= e($nonce) ?>" type="application/json" id="flash-data"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script nonce="<?= e($nonce) ?>">
window.MAXFAM = {csrf: <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, base: <?= json_encode(url('/'), JSON_UNESCAPED_SLASHES) ?>};
</script>
</body>
</html>
