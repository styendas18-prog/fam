<?php
$volumeChart = [
    'type' => 'line',
    'labels' => $series['labels'],
    'currency' => true,
    'legend' => true,
    'datasets' => [
        ['label' => 'Verified volume', 'data' => $series['volume'], 'fill' => true],
        ['label' => 'Verified payments', 'data' => $series['counts'], 'color' => '#10b981'],
    ],
];
$statusChart = [
    'type' => 'doughnut',
    'labels' => ['Success', 'Pending', 'Failed', 'Expired', 'Cancelled'],
    'legend' => true,
    'datasets' => [['label' => 'Orders', 'data' => array_values($statuses)]],
];
$apiChart = [
    'type' => 'bar',
    'labels' => $apiUsage['labels'],
    'legend' => true,
    'datasets' => [
        ['label' => 'Requests', 'data' => $apiUsage['counts']],
        ['label' => 'Errors', 'data' => $apiUsage['errors'], 'color' => '#ef4444'],
    ],
];
?>
<div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-ink-800">
        <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $value => $label): ?>
        <a href="<?= e(url('/user/analytics.php?days=' . $value)) ?>" class="rounded-lg px-3 py-1.5 text-[11px] font-semibold <?= $days === $value ? 'bg-brand-600 text-white' : 'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <a href="<?= e(url('/user/analytics.php') . query_string(['export' => 'csv'])) ?>" class="btn-secondary btn-sm self-start"><i data-lucide="download" class="h-3.5 w-3.5"></i> Export verified CSV</a>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card"><p class="stat-label">Verified volume</p><p class="stat-value"><?= e(money($stats['success_volume'])) ?></p><p class="mt-1 text-[10px] text-slate-500"><?= (int) $verifiedTotal ?> verified transaction<?= (int) $verifiedTotal === 1 ? '' : 's' ?></p></div>
    <div class="stat-card"><p class="stat-label">Average verified order</p><p class="stat-value"><?= e(money($avgOrder)) ?></p><p class="mt-1 text-[10px] text-slate-500">Derived from verified transactions</p></div>
    <div class="stat-card"><p class="stat-label">Order success rate</p><p class="stat-value"><?= number_format((float) $stats['success_rate'], 1) ?><span class="text-[14px] text-slate-400">%</span></p><p class="mt-1 text-[10px] text-slate-500"><?= (int) $stats['successful_orders'] ?> of <?= (int) $stats['total_orders'] ?> orders</p></div>
    <div class="stat-card"><p class="stat-label">API requests today</p><p class="stat-value"><?= (int) $stats['api_today'] ?></p><p class="mt-1 text-[10px] text-slate-500">Logged server-side</p></div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Verified revenue</h2><p class="text-[10.5px] text-slate-500">Last <?= (int) $days ?> days. Only successful, provider-confirmed orders.</p></div><span class="badge-success">SQL-derived</span></div>
        <div class="p-4"><div class="chart-wrap"><canvas data-chart="<?= e(json_encode($volumeChart, JSON_UNESCAPED_SLASHES)) ?>" aria-label="Verified revenue chart" role="img"></canvas></div></div>
    </div>
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Order mix</h2><p class="text-[10.5px] text-slate-500">All orders, all time.</p></div>
        <div class="p-4"><div class="chart-wrap !h-[240px]"><canvas data-chart="<?= e(json_encode($statusChart, JSON_UNESCAPED_SLASHES)) ?>" aria-label="Order status mix" role="img"></canvas></div></div>
    </div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">API usage</h2><p class="text-[10.5px] text-slate-500">Requests and failures recorded in <span class="font-mono">api_logs</span>.</p></div>
        <div class="p-4"><div class="chart-wrap !h-[240px]"><canvas data-chart="<?= e(json_encode($apiChart, JSON_UNESCAPED_SLASHES)) ?>" aria-label="API usage chart" role="img"></canvas></div></div>
    </div>
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Provider performance</h2></div>
        <?php if (!$providers): ?>
        <p class="px-5 py-10 text-center text-[11px] text-slate-500">No provider orders yet.</p>
        <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
            <?php foreach ($providers as $provider): $rate = (int) $provider['total'] > 0 ? round((int) $provider['success'] / (int) $provider['total'] * 100, 1) : 0; ?>
            <div class="px-5 py-3">
                <div class="flex items-center justify-between gap-3"><p class="text-[11.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($provider['name']) ?></p><span class="text-[10px] text-slate-500"><?= $rate ?>%</span></div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-500" style="width: <?= (int) $rate ?>%"></div></div>
                <p class="mt-1.5 text-[9.5px] text-slate-500"><?= (int) $provider['success'] ?> successful of <?= (int) $provider['total'] ?> · <?= e(money((float) $provider['volume'])) ?> verified</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-900">
    <p class="text-[10.5px] leading-5 text-slate-500">All figures on this page are calculated from the <span class="font-mono">transactions</span> and <span class="font-mono">payment_orders</span> tables. MAXXfam does not display estimated, projected or fabricated statistics. Failed, expired and cancelled orders are never included in revenue.</p>
</div>
