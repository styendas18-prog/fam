<?php
$summary = $summary ?? ['total_count' => 0, 'total_volume' => 0, 'avg_amount' => 0];
$orderSummary = $orderSummary ?? ['total_orders' => 0, 'success_orders' => 0, 'failed_orders' => 0, 'expired_orders' => 0];
$rows = $rows ?? [];
$merchants = $merchants ?? [];
$providers = $providers ?? [];
$filters = $filters ?? [];
?>
<div class="mb-6 grid gap-4 sm:grid-cols-4">
    <div class="stat-card">
        <p class="stat-label">Verified Volume</p>
        <p class="stat-value text-brand-600 dark:text-brand-400"><?= e(money((float) $summary['total_volume'])) ?></p>
        <p class="mt-1 text-[10px] text-slate-500">Filtered timeframe</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">Verified Payments</p>
        <p class="stat-value"><?= number_format((int) $summary['total_count']) ?></p>
        <p class="mt-1 text-[10px] text-slate-500">Authoritative UTRs</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">Avg Transaction</p>
        <p class="stat-value"><?= e(money((float) $summary['avg_amount'])) ?></p>
        <p class="mt-1 text-[10px] text-slate-500">Per verified payment</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">Platform Success Rate</p>
        <?php
        $tot = (int) $orderSummary['total_orders'];
        $suc = (int) $orderSummary['success_orders'];
        $rate = $tot > 0 ? round(($suc / $tot) * 100, 1) : 0.0;
        ?>
        <p class="stat-value text-emerald-600 dark:text-emerald-400"><?= $rate ?>%</p>
        <p class="mt-1 text-[10px] text-slate-500"><?= $suc ?> of <?= $tot ?> total sessions</p>
    </div>
</div>

<div class="card mb-6">
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-5 sm:items-end">
        <div>
            <label class="label" for="from">From Date</label>
            <input type="date" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>" class="input">
        </div>
        <div>
            <label class="label" for="to">To Date</label>
            <input type="date" id="to" name="to" value="<?= e($filters['to'] ?? '') ?>" class="input">
        </div>
        <div>
            <label class="label" for="merchant_id">Merchant</label>
            <select id="merchant_id" name="merchant_id" class="input">
                <option value="">All Merchants</option>
                <?php foreach ($merchants as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= ($filters['merchant_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['business_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="provider_id">Provider</label>
            <select id="provider_id" name="provider_id" class="input">
                <option value="">All Providers</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= ($filters['provider_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary flex-1 justify-center">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
            <a href="<?= e(url('/admin/reports.php?export=csv&' . http_build_query($filters))) ?>" class="btn-secondary" title="Export CSV">
                <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
            </a>
            <a href="<?= e(url('/admin/reports.php?export=pdf&' . http_build_query($filters))) ?>" target="_blank" class="btn-secondary" title="Export PDF">
                <i data-lucide="file-text" class="h-4 w-4"></i>
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Transaction Breakdown (<?= count($rows) ?> rows)</h2>
    </div>

    <?php if (empty($rows)): ?>
        <div class="px-5 py-16 text-center text-slate-500">
            <p>No transactions match the selected criteria.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>UTR</th>
                        <th>Merchant</th>
                        <th>Provider</th>
                        <th>Amount</th>
                        <th>Verified At (IST)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="font-mono text-[11px] font-bold text-slate-900 dark:text-white"><?= e($r['public_id']) ?></td>
                            <td class="font-mono text-[11px] text-brand-600 dark:text-brand-400"><?= e($r['utr']) ?></td>
                            <td class="text-[11px] font-medium text-slate-700 dark:text-slate-300"><?= e($r['business_name']) ?></td>
                            <td class="text-[11px] text-slate-500"><?= e($r['provider_name']) ?></td>
                            <td class="whitespace-nowrap font-bold text-slate-900 dark:text-white"><?= e(money((float) $r['amount'])) ?></td>
                            <td class="whitespace-nowrap text-[11px] text-slate-500"><?= e(ist($r['verified_at'], 'd M Y, H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
