<?php
$chart = [
    'type' => 'line',
    'labels' => $series['labels'],
    'currency' => true,
    'legend' => true,
    'datasets' => [
        ['label' => 'Verified volume', 'data' => $series['volume'], 'fill' => true],
        ['label' => 'Verified count', 'data' => $series['counts'], 'color' => '#10b981'],
    ],
];
$providerConnected = false;
foreach ($providers as $p) {
    if ($p['connection_status'] === 'connected') {
        $providerConnected = true;
    }
}
?>
<div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-300">Operations</p>
        <h2 class="mt-1 text-[23px] font-bold tracking-tight text-slate-900 dark:text-white">Platform overview</h2>
        <p class="mt-0.5 text-[11.5px] text-slate-500"><?= $stats['orders_total'] > 0 || $stats['users_total'] > 0 ? 'All figures are derived from live database rows.' : 'No live data yet. Configure a provider to begin.' ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('/admin/reports.php')) ?>" class="btn-secondary btn-sm"><i data-lucide="file-bar-chart" class="h-3.5 w-3.5"></i> Reports</a>
        <a href="<?= e(url('/admin/providers.php')) ?>" class="btn-primary btn-sm"><i data-lucide="plug-zap" class="h-3.5 w-3.5"></i> Providers</a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">Verified volume</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><i data-lucide="badge-indian-rupee" class="h-4 w-4"></i></span></div>
        <p class="stat-value" data-count="<?= e((string) $stats['volume_total']) ?>" data-format="money">₹0.00</p>
        <p class="mt-1 text-[10.5px] text-slate-500">Today: <?= e(money((float) $stats['volume_today'])) ?></p>
    </div>
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">Verified today</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-400/10 dark:text-brand-300"><i data-lucide="shield-check" class="h-4 w-4"></i></span></div>
        <p class="stat-value"><?= (int) $verifiedToday['count'] ?></p>
        <p class="mt-1 text-[10.5px] text-slate-500"><?= (int) $stats['orders_pending'] ?> pending order<?= (int) $stats['orders_pending'] === 1 ? '' : 's' ?></p>
    </div>
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">Merchants</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300"><i data-lucide="building-2" class="h-4 w-4"></i></span></div>
        <p class="stat-value"><?= (int) $stats['merchants_total'] ?></p>
        <p class="mt-1 text-[10.5px] text-slate-500"><?= (int) $stats['merchants_active'] ?> active · <?= (int) $stats['users_total'] ?> user<?= (int) $stats['users_total'] === 1 ? '' : 's' ?></p>
    </div>
    <div class="stat-card">
        <div class="flex items-start justify-between"><span class="stat-label">API requests today</span><span class="grid h-8 w-8 place-items-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-300"><i data-lucide="terminal" class="h-4 w-4"></i></span></div>
        <p class="stat-value"><?= (int) $stats['api_today'] ?></p>
        <p class="mt-1 text-[10.5px] text-slate-500"><?= (int) $email['due'] ?> email<?= (int) $email['due'] === 1 ? '' : 's' ?> queued</p>
    </div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center dark:border-white/[0.06]">
            <div><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Verified payments — last 14 days</h3><p class="text-[10.5px] text-slate-500">Only provider-verified transactions are counted.</p></div>
            <span class="badge-success self-start"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> SQL-derived</span>
        </div>
        <div class="p-4"><div class="chart-wrap"><canvas data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_SLASHES)) ?>" aria-label="Verified volume chart" role="img"></canvas></div></div>
    </div>
    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Operational alerts</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php
                $alerts = [
                    ['Provider connectivity', $providerConnected ? 'At least one provider is connected.' : 'No provider is connected yet.', $providerConnected ? 'success' : 'warning', '/admin/providers.php'],
                    ['Failed webhook deliveries', $failedWebhooks . ' delivery record(s) permanently failed.', $failedWebhooks > 0 ? 'danger' : 'success', '/admin/webhooks.php'],
                    ['Open support tickets', $pendingTickets . ' ticket(s) open or awaiting reply.', $pendingTickets > 0 ? 'warning' : 'success', '/admin/support.php'],
                    ['Critical security events (24h)', $security['critical_24h'] . ' critical event(s).', $security['critical_24h'] > 0 ? 'danger' : 'success', '/admin/security-logs.php'],
                ];
                foreach ($alerts as [$label, $detail, $tone, $href]):
                    $toneClass = $tone === 'success' ? 'text-emerald-600 dark:text-emerald-300' : ($tone === 'warning' ? 'text-amber-600 dark:text-amber-300' : 'text-rose-600 dark:text-rose-300');
                ?>
                <a href="<?= e(url($href)) ?>" class="flex items-start gap-3 px-5 py-3.5 hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full <?= $tone === 'success' ? 'bg-emerald-100 dark:bg-emerald-400/15' : ($tone === 'warning' ? 'bg-amber-100 dark:bg-amber-400/15' : 'bg-rose-100 dark:bg-rose-400/15') ?> <?= $toneClass ?>"><i data-lucide="<?= $tone === 'success' ? 'check' : 'alert-triangle' ?>" class="h-3 w-3"></i></span>
                    <span><span class="block text-[11.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($label) ?></span><span class="mt-0.5 block text-[10px] text-slate-500"><?= e($detail) ?></span></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Cron workers</h3><p class="text-[10px] text-slate-500">Last recorded run of each worker.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($cron as [$label, $script, $lastRun]): ?>
                <div class="flex items-center justify-between gap-3 px-5 py-2.5">
                    <div><p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200"><?= e($label) ?></p><p class="font-mono text-[9px] text-slate-400"><?= e($script) ?></p></div>
                    <span class="<?= $lastRun !== '' ? 'badge-success' : 'badge-muted' ?> shrink-0"><?= $lastRun !== '' ? e(time_ago($lastRun)) : 'Never run' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="border-t border-slate-100 px-5 py-3 dark:border-white/[0.06]"><a href="<?= e(url('/admin/maintenance.php')) ?>" class="text-[10.5px] font-semibold text-brand-600 dark:text-brand-300">Maintenance & health →</a></div>
        </div>
    </div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
            <div><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Recent payment orders</h3><p class="text-[10.5px] text-slate-500">Platform-wide, newest first.</p></div>
            <a href="<?= e(url('/admin/orders.php')) ?>" class="text-[10.5px] font-semibold text-brand-600 dark:text-brand-300">View all</a>
        </div>
        <?php if (!$recentOrders): ?>
        <p class="px-5 py-12 text-center text-[11px] text-slate-500">No payment orders exist yet.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead><tr><th>Order</th><th>Merchant</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($recentOrders as $order): $meta = status_meta($order['status']); ?>
                <tr>
                    <td><a href="<?= e(url('/admin/orders.php?order=' . urlencode($order['public_id']))) ?>" class="font-mono text-[11px] font-semibold text-brand-600 dark:text-brand-300"><?= e($order['public_id']) ?></a><p class="mt-0.5 text-[9.5px] text-slate-400"><?= e($order['provider_name']) ?></p></td>
                    <td><p class="max-w-[150px] truncate text-[11px] text-slate-700 dark:text-slate-300"><?= e($order['business_name']) ?></p><p class="font-mono text-[9px] text-slate-400"><?= e($order['merchant_code']) ?></p></td>
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

    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Admin activity</h3><a href="<?= e(url('/admin/activity.php')) ?>" class="text-[10px] font-semibold text-brand-600 dark:text-brand-300">All</a></div>
        <?php if (!$recentActivity): ?>
        <p class="px-5 py-10 text-center text-[11px] text-slate-500">No administrator actions recorded yet.</p>
        <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
            <?php foreach ($recentActivity as $row): ?>
            <div class="px-5 py-3">
                <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200"><?= e(str_replace('_', ' ', ucfirst($row['action']))) ?></p>
                <p class="mt-0.5 text-[9.5px] text-slate-400"><?= e($row['admin_name'] ?: 'System') ?> · <?= e(time_ago($row['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Provider connections</h3><a href="<?= e(url('/admin/providers.php')) ?>" class="text-[10px] font-semibold text-brand-600 dark:text-brand-300">Manage</a></div>
        <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
            <?php foreach ($providers as $provider): $meta = status_meta($provider['connection_status']); ?>
            <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                <div><p class="text-[11.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($provider['name']) ?></p><p class="font-mono text-[9.5px] text-slate-400"><?= e($provider['code']) ?> · <?= e($provider['base_url']) ?></p></div>
                <span class="<?= e($meta['class']) ?> shrink-0"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$providers): ?><p class="px-5 py-8 text-center text-[11px] text-slate-500">No providers installed.</p><?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Email queue</h3><a href="<?= e(url('/admin/smtp.php')) ?>" class="text-[10px] font-semibold text-brand-600 dark:text-brand-300">SMTP</a></div>
        <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4 dark:bg-white/[0.06]">
            <?php foreach ([['Total', $email['total']], ['Pending', $email['pending']], ['Sent', $email['sent']], ['Failed', $email['failed']]] as [$label, $value]): ?>
            <div class="bg-white px-4 py-4 text-center dark:bg-ink-800"><p class="text-[18px] font-bold text-slate-900 dark:text-white"><?= (int) $value ?></p><p class="text-[9.5px] uppercase tracking-[0.1em] text-slate-500"><?= e($label) ?></p></div>
            <?php endforeach; ?>
        </div>
        <div class="px-5 py-3.5 text-[10px] leading-5 text-slate-500">Mail is queued transactionally, then delivered by <span class="font-mono text-[9.5px]">cron/process_email.php</span>. Nothing is marked sent until SMTP accepts it.</div>
    </div>
</div>
