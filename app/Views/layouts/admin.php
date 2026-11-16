<?php
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Security;
use App\Core\Settings;

$currentAdmin = Auth::admin();
if (!$currentAdmin) {
    App\Core\Response::redirect(url('/admin/login.php'));
}
$siteName = (string) Settings::get('site_name', 'MAXXfam');
$nonce = Security::nonce();
$flash = Flash::consume();
$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Admin';
$pageSubtitle = $pageSubtitle ?? '';
$headerActions = $headerActions ?? '';
$pageScripts = $pageScripts ?? [];
$bodyClass = $bodyClass ?? '';
$roleLabel = match ((string) $currentAdmin['role']) {
    'super_admin' => 'Super admin',
    'admin' => 'Administrator',
    'support' => 'Support',
    default => 'Admin',
};

$groups = [
    'Overview' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'gauge', 'href' => '/admin/dashboard.php'],
    ],
    'Merchants' => [
        ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'href' => '/admin/users.php'],
        ['key' => 'merchants', 'label' => 'Merchants', 'icon' => 'building-2', 'href' => '/admin/merchants.php'],
        ['key' => 'transactions', 'label' => 'Transactions', 'icon' => 'receipt-text', 'href' => '/admin/transactions.php'],
        ['key' => 'orders', 'label' => 'Payment orders', 'icon' => 'shopping-bag', 'href' => '/admin/orders.php'],
        ['key' => 'plans', 'label' => 'Plans', 'icon' => 'credit-card', 'href' => '/admin/plans.php'],
    ],
    'Payments' => [
        ['key' => 'providers', 'label' => 'Payment providers', 'icon' => 'plug-zap', 'href' => '/admin/providers.php'],
        ['key' => 'email-verification', 'label' => 'Email verification', 'icon' => 'mail-check', 'href' => '/admin/email-verification.php'],
        ['key' => 'payment-settings', 'label' => 'Payment settings', 'icon' => 'sliders-horizontal', 'href' => '/admin/payment-settings.php'],
        ['key' => 'api', 'label' => 'API management', 'icon' => 'braces', 'href' => '/admin/api-management.php'],
        ['key' => 'webhooks', 'label' => 'Webhooks', 'icon' => 'webhook', 'href' => '/admin/webhooks.php'],
    ],
    'Operations' => [
        ['key' => 'support', 'label' => 'Support', 'icon' => 'life-buoy', 'href' => '/admin/support.php'],
        ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'href' => '/admin/announcements.php'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'file-bar-chart', 'href' => '/admin/reports.php'],
    ],
    'Observability' => [
        ['key' => 'api-logs', 'label' => 'API logs', 'icon' => 'terminal', 'href' => '/admin/api-logs.php'],
        ['key' => 'security-logs', 'label' => 'Security logs', 'icon' => 'shield-alert', 'href' => '/admin/security-logs.php'],
        ['key' => 'activity', 'label' => 'Admin activity', 'icon' => 'history', 'href' => '/admin/activity.php'],
    ],
    'System' => [
        ['key' => 'settings', 'label' => 'System config', 'icon' => 'settings-2', 'href' => '/admin/settings.php'],
        ['key' => 'smtp', 'label' => 'SMTP & email', 'icon' => 'mail-check', 'href' => '/admin/smtp.php'],
        ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'wrench', 'href' => '/admin/maintenance.php'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <title><?= e($pageTitle) ?> · Admin · <?= e($siteName) ?></title>
    <meta name="theme-color" content="#050a15">
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
<a href="#admin-main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-slate-900">Skip to content</a>

<div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden"></div>

<aside id="app-sidebar" class="sidebar-scroll fixed inset-y-0 left-0 z-50 flex w-[264px] -translate-x-full flex-col overflow-y-auto border-r border-white/[0.06] bg-[#050a15] px-4 pb-6 pt-5 transition-transform duration-300 lg:translate-x-0">
    <div class="flex items-center justify-between px-2">
        <a href="<?= e(url('/admin/dashboard.php')) ?>" class="flex items-center gap-2">
            <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-8 w-auto">
            <span class="rounded-md border border-brand-400/30 bg-brand-500/15 px-1.5 py-0.5 text-[8.5px] font-bold uppercase tracking-[0.1em] text-brand-200">Admin</span>
        </a>
        <button type="button" data-sidebar-close class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-white/5 hover:text-white lg:hidden" aria-label="Close navigation"><i data-lucide="x" class="h-4 w-4"></i></button>
    </div>

    <div class="mt-6 rounded-xl border border-white/[0.07] bg-white/[0.03] px-3 py-2.5">
        <div class="flex items-center gap-2.5">
            <span class="avatar h-8 w-8 text-[10px]"><?= e(initials((string) $currentAdmin['name'])) ?></span>
            <div class="min-w-0">
                <p class="truncate text-[11.5px] font-semibold text-white"><?= e($currentAdmin['name']) ?></p>
                <p class="truncate text-[9.5px] text-slate-500"><?= e($roleLabel) ?></p>
            </div>
            <span class="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"></span>
        </div>
    </div>

    <nav class="mt-4" aria-label="Admin">
        <?php foreach ($groups as $group => $items): ?>
        <p class="sidebar-group"><?= e($group) ?></p>
        <div class="space-y-0.5">
            <?php foreach ($items as $item): ?>
            <a href="<?= e(url($item['href'])) ?>" class="sidebar-link <?= $active === $item['key'] ? 'is-active' : '' ?>" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                <i data-lucide="<?= e($item['icon']) ?>"></i><span class="truncate"><?= e($item['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="mt-auto pt-6">
        <a href="<?= e(url('/admin/maintenance.php')) ?>" class="rounded-xl border border-white/[0.07] bg-white/[0.03] p-3.5 block hover:bg-white/[0.05]">
            <div class="flex items-center gap-2 text-[10.5px] font-semibold text-slate-300"><i data-lucide="activity" class="h-3.5 w-3.5 text-emerald-400"></i> Operations</div>
            <p class="mt-1.5 text-[9.5px] leading-4 text-slate-500">Cron status, maintenance mode and system health.</p>
        </a>
    </div>
</aside>

<div class="min-h-screen lg:pl-[264px]">
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-white/[0.07] dark:bg-ink-950/80">
        <div class="flex h-[68px] items-center gap-3 px-4 sm:px-6 xl:px-8">
            <button type="button" data-sidebar-open class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 lg:hidden" aria-label="Open navigation"><i data-lucide="menu" class="h-5 w-5"></i></button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-[17px] font-bold tracking-tight text-slate-900 dark:text-white"><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle !== ''): ?><p class="truncate text-[11.5px] text-slate-500 dark:text-slate-400"><?= e($pageSubtitle) ?></p><?php endif; ?>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <?= $headerActions ?>
                <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener" class="hidden h-10 items-center gap-1.5 rounded-xl border border-slate-200 px-3 text-[11px] font-semibold text-slate-500 hover:bg-slate-50 sm:inline-flex dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/5">View site <i data-lucide="arrow-up-right" class="h-3 w-3"></i></a>
                <button type="button" data-theme-toggle class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/5" aria-label="Toggle theme">
                    <i data-lucide="moon" data-theme-icon="moon" class="hidden h-[18px] w-[18px]"></i><i data-lucide="sun" data-theme-icon="sun" class="h-[18px] w-[18px]"></i>
                </button>
                <div class="relative" data-dropdown>
                    <button type="button" data-dropdown-trigger class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-2.5 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10">
                        <span class="avatar h-8 w-8 text-[10px]"><?= e(initials((string) $currentAdmin['name'])) ?></span>
                        <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-slate-400"></i>
                    </button>
                    <div data-dropdown-panel class="absolute right-0 mt-2 hidden w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-ink-800">
                        <div class="border-b border-slate-100 px-3 py-2.5 dark:border-white/10"><p class="truncate text-[12px] font-semibold text-slate-800 dark:text-white"><?= e($currentAdmin['name']) ?></p><p class="truncate text-[10.5px] text-slate-500"><?= e($currentAdmin['email']) ?> · <?= e($roleLabel) ?></p></div>
                        <a href="<?= e(url('/admin/settings.php')) ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><i data-lucide="settings-2" class="h-3.5 w-3.5"></i> Settings</a>
                        <a href="<?= e(url('/admin/change-password.php')) ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><i data-lucide="key-round" class="h-3.5 w-3.5"></i> Change password</a>
                        <?php if ((int) $currentAdmin['totp_enabled'] !== 1): ?>
                        <a href="<?= e(url('/admin/two-factor.php?setup=1')) ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Enable 2FA</a>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/admin/logout.php')) ?>" class="border-t border-slate-100 pt-1 dark:border-white/10"><?= csrf_field() ?><button class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[12px] text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"><i data-lucide="log-out" class="h-3.5 w-3.5"></i> End session</button></form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="admin-main" class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 xl:px-8"><?= $content ?></main>

    <footer class="mx-auto flex w-full max-w-[1440px] flex-col gap-2 px-4 py-6 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 xl:px-8 dark:text-slate-500">
        <p><?= e($siteName) ?> admin · <?= e($roleLabel) ?> session · All actions are audited.</p>
        <p class="font-mono text-[10px]"><?= e(gmdate('Y-m-d H:i')) ?> UTC</p>
    </footer>
</div>

<div id="confirm-modal" class="fixed inset-0 z-[90] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div data-confirm-backdrop class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-white/10 dark:bg-ink-800">
        <div class="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300"><i data-lucide="alert-triangle" class="h-5 w-5"></i></div>
        <h2 id="confirm-title" data-confirm-title class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Confirm action</h2>
        <p data-confirm-copy class="mt-2 text-[13px] leading-6 text-slate-500 dark:text-slate-400">This action will be written to the audit log.</p>
        <div class="mt-6 flex justify-end gap-2"><button type="button" data-confirm-cancel class="btn-secondary">Cancel</button><button type="button" data-confirm-proceed class="btn-danger">Continue</button></div>
    </div>
</div>

<script nonce="<?= e($nonce) ?>" type="application/json" id="flash-data"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script nonce="<?= e($nonce) ?>">window.MAXFAM={csrf:<?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,base:<?= json_encode(url('/'), JSON_UNESCAPED_SLASHES) ?>};</script>
</body>
</html>
