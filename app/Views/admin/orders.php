<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$filters = $filters ?? [];
$merchants = $merchants ?? [];
?>
<div class="card mb-6">
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-5 sm:items-end">
        <div>
            <label class="label" for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Order ID or Email" class="input">
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="input">
                <option value="">All States</option>
                <?php foreach (['pending', 'success', 'failed', 'expired', 'cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
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
            <label class="label" for="from">From Date</label>
            <input type="date" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>" class="input">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary flex-1 justify-center">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
            <?php if (!empty($filters['q']) || !empty($filters['status']) || !empty($filters['merchant_id'])): ?>
                <a href="<?= e(url('/admin/orders.php')) ?>" class="btn-secondary" title="Clear Filters">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <div>
            <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Orders List</h2>
            <p class="text-[11px] text-slate-500">Total <?= (int) $result['total'] ?> payment orders registered.</p>
        </div>
    </div>

    <?php if (empty($result['rows'])): ?>
        <div class="px-5 py-16 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                <i data-lucide="shopping-bag" class="h-6 w-6"></i>
            </span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No payment orders found</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Merchant</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Created (IST)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <p class="font-mono text-[11px] font-bold text-slate-900 dark:text-white"><?= e($row['public_id']) ?></p>
                                <p class="font-mono text-[9.5px] text-slate-400">PID: <?= e($row['provider_order_id'] ?: '—') ?></p>
                            </td>
                            <td>
                                <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"><?= e($row['business_name']) ?></p>
                                <p class="text-[9.5px] text-slate-400"><?= e($row['merchant_code']) ?></p>
                            </td>
                            <td>
                                <p class="text-[11px] text-slate-700 dark:text-slate-300"><?= e($row['customer_name'] ?: '—') ?></p>
                                <p class="text-[9.5px] text-slate-400"><?= e($row['customer_email'] ?: '') ?></p>
                            </td>
                            <td class="whitespace-nowrap font-bold text-slate-900 dark:text-white">
                                <?= e(money((float) ($row['payable_amount'] ?? $row['amount']))) ?>
                            </td>
                            <td>
                                <?php
                                $badge = match ($row['status']) {
                                    'success' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'failed' => 'badge-danger',
                                    'expired' => 'badge-neutral',
                                    'cancelled' => 'badge-neutral',
                                    default => 'badge-neutral',
                                };
                                ?>
                                <span class="<?= $badge ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="text-[11px] text-slate-600 dark:text-slate-400">
                                <?= e($row['provider_name']) ?>
                            </td>
                            <td class="whitespace-nowrap text-[11px] text-slate-500">
                                <?= e(ist($row['created_at'], 'd M Y, H:i')) ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <a href="<?= e(url('/checkout.php?token=' . urlencode((string) $row['checkout_token']))) ?>"
                                       target="_blank" class="btn-secondary btn-sm" title="View Checkout">
                                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                                    </a>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <form method="post" class="inline" data-loading>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn-secondary btn-sm" title="Check Provider Status">
                                                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Check
                                            </button>
                                        </form>
                                        <form method="post" class="inline" data-confirm="Are you sure you want to mark this pending order expired?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="action" value="expire">
                                            <button type="submit" class="btn-danger btn-sm" title="Expire Order">
                                                <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/orders.php')]); ?>
    <?php endif; ?>
</div>
