<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$filters = $filters ?? [];
$merchants = $merchants ?? [];
?>
<div class="card mb-6">
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-4 sm:items-end">
        <div>
            <label class="label" for="q">Search UTR / Order / Customer</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search UTR or Order ID" class="input">
        </div>
        <div>
            <label class="label" for="merchant_id">Merchant</label>
            <select id="merchant_id" name="merchant_id" class="input">
                <option value="">All Merchants</option>
                <?php foreach ($merchants as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= ($filters['merchant_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['business_name']) ?> (<?= e($m['merchant_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="from">From Date</label>
            <input type="date" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>" class="input">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary flex-1 justify-center">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
            <a href="<?= e(url('/admin/transactions.php?export=csv&' . http_build_query($filters))) ?>" class="btn-secondary" title="Export CSV">
                <i data-lucide="download" class="h-4 w-4"></i>
            </a>
            <?php if (!empty($filters['q']) || !empty($filters['merchant_id']) || !empty($filters['from'])): ?>
                <a href="<?= e(url('/admin/transactions.php')) ?>" class="btn-secondary" title="Clear Filters">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <div>
            <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Transaction Records</h2>
            <p class="text-[11px] text-slate-500">Total <?= (int) $result['total'] ?> verified transactions across the platform.</p>
        </div>
    </div>

    <?php if (empty($result['rows'])): ?>
        <div class="px-5 py-16 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                <i data-lucide="receipt" class="h-6 w-6"></i>
            </span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No transactions found</p>
            <p class="mt-1 text-[11px] text-slate-500">Transactions appear here once payment is verified with an authoritative UTR.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Order / UTR</th>
                        <th>Merchant</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Verified At (IST)</th>
                        <th>Refund Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <p class="font-mono text-[11px] font-bold text-slate-900 dark:text-white"><?= e($row['public_id']) ?></p>
                                <p class="font-mono text-[10px] text-brand-600 dark:text-brand-400">UTR: <?= e($row['utr']) ?></p>
                            </td>
                            <td>
                                <p class="text-[11.5px] font-semibold text-slate-800 dark:text-slate-200"><?= e($row['business_name']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= e($row['merchant_code']) ?></p>
                            </td>
                            <td class="whitespace-nowrap font-bold text-slate-900 dark:text-white">
                                <?= e(money((float) $row['amount'])) ?>
                            </td>
                            <td>
                                <span class="badge-success">
                                    <i data-lucide="check" class="h-3 w-3"></i> Verified
                                </span>
                                <?php if (($row['refund_status'] ?? 'none') !== 'none'): ?>
                                    <span class="mt-1 block text-[9.5px] font-medium text-amber-600 dark:text-amber-400">
                                        Refund: <?= e($row['refund_status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-[11px] text-slate-600 dark:text-slate-400">
                                <?= e($row['provider_name']) ?>
                            </td>
                            <td class="whitespace-nowrap text-[11px] text-slate-500">
                                <?= e(ist($row['verified_at'], 'd M Y, H:i:s')) ?>
                            </td>
                            <td class="max-w-[140px] truncate text-[10.5px] text-slate-500">
                                <?= e($row['refund_note'] ?: '—') ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <a href="<?= e(url('/receipt.php?invoice=' . urlencode((string) ($row['invoice_number'] ?? '')) . '&token=' . urlencode((string) ($row['checkout_token'] ?? '')))) ?>"
                                   target="_blank" class="btn-secondary btn-sm" title="View PDF Receipt">
                                    <i data-lucide="file-text" class="h-3.5 w-3.5"></i> PDF
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/transactions.php')]); ?>
    <?php endif; ?>
</div>
