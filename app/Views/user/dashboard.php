<?php
$readiness = [
    ['Configure a provider', $providerConfigured, 'Save an authorized provider API key under Integration.', '/user/integration.php'],
    ['Verify the connection', $providerConnected, 'Run a real authenticated connection test.', '/user/integration.php'],
    ['Create an API key', $activeKeys > 0, 'Issue a scoped key for server-to-server calls.', '/user/api-keys.php'],
    ['Add a webhook', $activeHooks > 0, 'Receive signed payment events with retries.', '/user/webhooks.php'],
    ['Configure SMTP', $smtpConfigured, 'Deliver real email notifications from the platform.', '/admin/smtp.php'],
    ['Verify a payment', $lastVerified, 'A genuine provider-confirmed UTR appears here.', '/user/create-payment.php'],
];
$completed = count(array_filter($readiness, static fn (array $r): bool => $r[1]));
$chart = [
    'type' => 'line',
    'labels' => $series['labels'],
    'currency' => true,
    'legend' => true,
    'datasets' => [
        ['label' => 'Verified volume', 'data' => $series['volume'], 'fill' => true],
        ['label' => 'Successful orders', 'data' => $series['counts'], 'color' => '#10b981'],
    ],
];
$statusChart = [
    'type' => 'doughnut',
    'labels' => ['Success', 'Pending', 'Failed', 'Expired', 'Cancelled'],
    'legend' => true,
    'datasets' => [['label' => 'Orders', 'data' => array_values($statuses)]],
];
?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-300"><?= e($merchant['merchant_code']) ?></p>
        <h2 class="mt-1 text-[24px] font-bold tracking-tight text-slate-900 dark:text-white">Welcome back, <?= e(explode(' ', (string) $user['name'])[0]) ?>.</h2>
        <p class="mt-0.5 text-[12px] text-slate-500 dark:text-slate-400"><?= $stats['verified_count'] > 0 ? 'Your verified payment data is shown below.' : 'No verified payment yet. Start by connecting a provider.' ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('/user/transactions.php')) ?>" class="btn-secondary btn-sm"><i data-lucide="arrow-left-right" class="h-3.5 w-3.5"></i> Transactions</a>
        <a href="<?= e(url('/user/create-payment.php')) ?>" class="btn-primary btn-sm"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Create payment</a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">Verified volume</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><i data-lucide="badge-indian-rupee" class="h-4 w-4"></i></span></div>
        <p class="stat-value" data-count="<?= e((string) $stats['success_volume']) ?>" data-format="money">₹0.00</p>
        <p class="mt-1 text-[10.5px] text-slate-500"><?= (int) $stats['verified_count'] ?> verified transaction<?= $stats['verified_count'] === 1 ? '' : 's' ?></p>
    </div>
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">Success rate</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-400/10 dark:text-brand-300"><i data-lucide="percent" class="h-4 w-4"></i></span></div>
        <p class="stat-value"><?= number_format((float) $stats['success_rate'], 1) ?><span class="text-[15px] text-slate-400">%</span></p>
        <p class="mt-1 text-[10.5px] text-slate-500"><?= (int) $stats['successful_orders'] ?> of <?= (int) $stats['total_orders'] ?> orders</p>
    </div>
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">Pending orders</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300"><i data-lucide="clock-3" class="h-4 w-4"></i></span></div>
        <p class="stat-value"><?= (int) $stats['pending_orders'] ?></p>
        <p class="mt-1 text-[10.5px] text-slate-500">Awaiting provider confirmation</p>
    </div>
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">API requests today</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300"><i data-lucide="terminal" class="h-4 w-4"></i></span></div>
        <p class="stat-value"><?= (int) $stats['api_today'] ?></p>
        <p class="mt-1 text-[10.5px] text-slate-500"><?= $activeKeys ?> active API key<?= $activeKeys === 1 ? '' : 's' ?></p>
    </div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center dark:border-white/[0.06]">
            <div><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Verified volume — last 14 days</h3><p class="text-[10.5px] text-slate-500">Derived from successful orders only.</p></div>
            <span class="badge-success self-start"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> SQL-derived</span>
        </div>
        <div class="p-4"><div class="chart-wrap"><canvas data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_SLASHES)) ?>" aria-label="Verified volume chart" role="img"></canvas></div></div>
    </div>
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
            <div class="flex items-center justify-between"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Go-live readiness</h3><span class="badge-info"><?= $completed ?>/<?= count($readiness) ?></span></div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-500" style="width: <?= (int) round($completed / count($readiness) * 100) ?>%"></div></div>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
            <?php foreach ($readiness as [$label, $done, $hint, $href]): ?>
            <a href="<?= e(url($href)) ?>" class="flex items-start gap-3 px-5 py-3 transition hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full <?= $done ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-400 dark:bg-white/10 dark:text-slate-500' ?>"><i data-lucide="<?= $done ? 'check' : 'circle' ?>" class="h-3 w-3"></i></span>
                <span class="min-w-0"><span class="block text-[11.5px] font-semibold <?= $done ? 'text-slate-400 line-through dark:text-slate-500' : 'text-slate-700 dark:text-slate-200' ?>"><?= e($label) ?></span><span class="mt-0.5 block text-[10px] leading-4 text-slate-500"><?= e($hint) ?></span></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
            <div><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Recent orders</h3><p class="text-[10.5px] text-slate-500">Newest first. Pending is not paid.</p></div>
            <a href="<?= e(url('/user/transactions.php')) ?>" class="text-[10.5px] font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">View all</a>
        </div>
        <?php if (!$recent): ?>
        <div class="px-5 py-14 text-center">
            <span class="grid mx-auto h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="inbox" class="h-5 w-5"></i></span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No payment orders yet</p>
            <p class="mx-auto mt-1 max-w-sm text-[11px] leading-5 text-slate-500">Create a payment after connecting your provider. Orders appear here with their honest provider status.</p>
            <a href="<?= e(url('/user/create-payment.php')) ?>" class="btn-primary btn-sm mt-4">Create your first payment</a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $order): $meta = status_meta($order['status']); ?>
                <tr>
                    <td><a href="<?= e(url('/user/transactions.php?order=' . urlencode($order['public_id']))) ?>" class="font-mono text-[11px] font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300"><?= e($order['public_id']) ?></a><p class="mt-0.5 text-[9.5px] text-slate-400"><?= e($order['provider_name']) ?></p></td>
                    <td><p class="max-w-[150px] truncate text-[11.5px] font-medium text-slate-700 dark:text-slate-200"><?= e($order['customer_name'] ?: '—') ?></p><p class="max-w-[150px] truncate text-[9.5px] text-slate-400"><?= e($order['customer_email'] ?: ($order['customer_reference'] ?: '')) ?></p></td>
                    <td class="whitespace-nowrap font-semibold"><?= e(money((float) ($order['payable_amount'] ?? $order['amount']))) ?></td>
                    <td><span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span></td>
                    <td class="whitespace-nowrap text-[10.5px] text-slate-500"><?= e(ist($order['created_at'], 'd M, H:i')) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Order mix</h3></div>
            <div class="p-4"><div class="chart-wrap !h-[190px]"><canvas data-chart="<?= e(json_encode($statusChart, JSON_UNESCAPED_SLASHES)) ?>" aria-label="Order status mix" role="img"></canvas></div></div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Latest notifications</h3><a href="<?= e(url('/user/notifications.php')) ?>" class="text-[10px] font-semibold text-brand-600 dark:text-brand-300">All</a></div>
            <?php if (!$notifications): ?>
            <p class="px-5 py-8 text-center text-[11px] text-slate-500">No notifications yet.</p>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($notifications as $note): ?>
                <a href="<?= e(url($note['link'] ?: '/user/notifications.php')) ?>" class="block px-5 py-3 hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                    <div class="flex items-start gap-2.5"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full <?= $note['read_at'] ? 'bg-slate-300 dark:bg-slate-600' : 'bg-brand-500' ?>"></span><div class="min-w-0"><p class="truncate text-[11.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($note['title']) ?></p><p class="mt-0.5 line-clamp-2 text-[10px] leading-4 text-slate-500"><?= e($note['message']) ?></p><p class="mt-1 text-[9px] text-slate-400"><?= e(time_ago($note['created_at'])) ?></p></div></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
