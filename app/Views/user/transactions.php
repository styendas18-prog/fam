<?php
$detailMeta = $detail ? status_meta($detail['status']) : null;
$statusOptions = ['' => 'All statuses', 'pending' => 'Pending', 'success' => 'Success', 'failed' => 'Failed', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];
?>
<?php if ($detail): ?>
<div class="mb-5 card overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-400/10 dark:text-brand-300"><i data-lucide="receipt-text" class="h-5 w-5"></i></span>
            <div><p class="font-mono text-[12px] font-bold text-slate-900 dark:text-white"><?= e($detail['public_id']) ?></p><p class="text-[10.5px] text-slate-500">Created <?= e(ist($detail['created_at'])) ?> · <?= e($detail['provider_name']) ?></p></div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="<?= e($detailMeta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($detailMeta['dot']) ?>"></span> <?= e($detailMeta['label']) ?></span>
            <a href="<?= e(url('/user/transactions.php')) ?>" class="btn-ghost btn-sm">Close</a>
        </div>
    </div>
    <div class="grid gap-6 p-5 lg:grid-cols-[1fr_280px]">
        <div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Requested amount</p><p class="mt-1 text-[22px] font-bold text-slate-900 dark:text-white"><?= e(money((float) $detail['amount'])) ?></p></div>
                <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Payable amount</p><p class="mt-1 text-[22px] font-bold <?= $detail['payable_amount'] !== null ? 'text-slate-900 dark:text-white' : 'text-slate-400' ?>"><?= $detail['payable_amount'] !== null ? e(money((float) $detail['payable_amount'])) : '—' ?></p></div>
            </div>
            <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-slate-100 pt-4 text-[11px] sm:grid-cols-2 dark:border-white/[0.06]">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Provider order</dt><dd class="truncate font-mono text-[10.5px] text-slate-700 dark:text-slate-300"><?= e($detail['provider_order_id'] ?: 'Not created') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Your reference</dt><dd class="truncate text-slate-700 dark:text-slate-300"><?= e($detail['customer_reference'] ?: '—') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer</dt><dd class="truncate text-slate-700 dark:text-slate-300"><?= e($detail['customer_name'] ?: '—') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="truncate text-slate-700 dark:text-slate-300"><?= e($detail['customer_email'] ?: '—') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Expires</dt><dd class="text-slate-700 dark:text-slate-300"><?= e(ist($detail['expires_at'], 'd M Y, H:i')) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Last checked</dt><dd class="text-slate-700 dark:text-slate-300"><?= $detail['last_checked_at'] ? e(time_ago($detail['last_checked_at'])) : 'Never' ?></dd></div>
            </dl>
            <?php if ($detail['status'] === 'success' && $transaction): ?>
            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-400/20 dark:bg-emerald-400/[0.06]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="flex items-center gap-2 text-[11px] font-bold text-emerald-700 dark:text-emerald-300"><i data-lucide="shield-check" class="h-4 w-4"></i> Provider-verified payment</p>
                        <p class="mt-2 font-mono text-[13px] font-bold tracking-wide text-emerald-800 dark:text-emerald-200">UTR <?= e($transaction['utr']) ?></p>
                        <p class="mt-1 text-[10px] text-emerald-700/70 dark:text-emerald-200/60">Verified <?= e(ist($transaction['verified_at'])) ?> · Source: <?= e($transaction['verification_source']) ?></p>
                    </div>
                    <?php if (!empty($transaction['invoice_number'])): ?>
                    <a href="<?= e(url('/receipt.php?token=' . urlencode((string) $detail['checkout_token']))) ?>" class="btn-primary btn-sm"><i data-lucide="download" class="h-3.5 w-3.5"></i> Receipt <?= e($transaction['invoice_number']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($detail['failure_message']): ?>
            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-400/20 dark:bg-rose-400/[0.06]">
                <p class="flex items-center gap-2 text-[11px] font-bold text-rose-700 dark:text-rose-300"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= e($detail['failure_code'] ?: 'Failure') ?></p>
                <p class="mt-1.5 text-[10.5px] leading-5 text-rose-700/80 dark:text-rose-200/70"><?= e($detail['failure_message']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <div class="space-y-3">
            <?php if ($detail['qr_url'] && $detail['status'] === 'pending'): ?>
            <div class="rounded-xl border border-slate-200 p-3 text-center dark:border-white/10">
                <p class="mb-2 text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-500">Provider QR</p>
                <img src="<?= e($detail['qr_url']) ?>" alt="Provider payment QR" class="mx-auto h-40 w-40 rounded-lg bg-white object-contain" loading="lazy">
                <p class="mt-2 text-[9px] leading-4 text-slate-400">Issued by <?= e($detail['provider_name']) ?>. Never pay a QR from an untrusted page.</p>
            </div>
            <?php endif; ?>
            <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-500">Actions</p>
                <div class="mt-3 space-y-2">
                    <?php if ($detail['provider_order_id'] && $detail['status'] !== 'success'): ?>
                    <form method="post" action="<?= e(url('/user/transactions.php')) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= e($detail['public_id']) ?>"><input type="hidden" name="action" value="recheck"><button class="btn-secondary btn-sm w-full justify-center"><i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Recheck provider</button></form>
                    <?php endif; ?>
                    <?php if ($detail['status'] === 'pending'): ?>
                    <a href="<?= e(url('/checkout.php?token=' . urlencode((string) $detail['checkout_token']))) ?>" target="_blank" rel="noopener" class="btn-primary btn-sm w-full justify-center"><i data-lucide="external-link" class="h-3.5 w-3.5"></i> Open checkout</a>
                    <form method="post" action="<?= e(url('/user/transactions.php')) ?>" data-confirm="Cancel this pending order? Only cancel if the customer has not paid. A payment already sent cannot be undone here." data-confirm-title="Cancel payment order"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= e($detail['public_id']) ?>"><input type="hidden" name="action" value="cancel"><button class="btn-ghost btn-sm w-full justify-center text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Cancel order</button></form>
                    <?php endif; ?>
                </div>
                <p class="mt-3 text-[9.5px] leading-4 text-slate-400">Recheck asks the provider for authoritative status. It cannot force success.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/[0.06]">
        <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Payment orders</h2><p class="text-[10.5px] text-slate-500"><?= (int) $result['total'] ?> order<?= $result['total'] === 1 ? '' : 's' ?> match this view.</p></div>
        <a href="<?= e(url('/user/transactions.php') . query_string(['export' => 'csv'])) ?>" class="btn-secondary btn-sm self-start"><i data-lucide="download" class="h-3.5 w-3.5"></i> Export CSV</a>
    </div>
    <form method="get" action="<?= e(url('/user/transactions.php')) ?>" class="grid gap-2 border-b border-slate-100 px-5 py-3 sm:grid-cols-2 lg:grid-cols-6 dark:border-white/[0.06]">
        <input name="q" value="<?= e($filters['q']) ?>" class="input h-9 text-[11px] lg:col-span-2" placeholder="Order, UTR, email or reference">
        <select name="status" class="input h-9 text-[11px]"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= e($filters['from']) ?>" class="input h-9 text-[11px]" aria-label="From date">
        <input type="date" name="to" value="<?= e($filters['to']) ?>" class="input h-9 text-[11px]" aria-label="To date">
        <div class="flex gap-2"><button class="btn-primary btn-sm flex-1">Filter</button><a href="<?= e(url('/user/transactions.php')) ?>" class="btn-ghost btn-sm">Reset</a></div>
    </form>
    <?php if (!$result['rows']): ?>
    <div class="px-5 py-16 text-center">
        <span class="grid mx-auto h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="search-x" class="h-5 w-5"></i></span>
        <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No orders found</p>
        <p class="mt-1 text-[11px] text-slate-500">Adjust the filters or create a new payment.</p>
        <a href="<?= e(url('/user/create-payment.php')) ?>" class="btn-primary btn-sm mt-4">Create payment</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table-mf">
            <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Provider</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): $meta = status_meta($row['status']); ?>
            <tr>
                <td><a href="<?= e(url('/user/transactions.php') . query_string(['order' => $row['public_id']])) ?>" class="font-mono text-[11px] font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300"><?= e($row['public_id']) ?></a><p class="mt-0.5 max-w-[120px] truncate text-[9.5px] text-slate-400"><?= e($row['provider_order_id'] ?: 'No provider ID') ?></p></td>
                <td><p class="max-w-[150px] truncate text-[11.5px] font-medium text-slate-700 dark:text-slate-200"><?= e($row['customer_name'] ?: '—') ?></p><p class="max-w-[150px] truncate text-[9.5px] text-slate-400"><?= e($row['customer_email'] ?: ($row['customer_reference'] ?: '')) ?></p></td>
                <td class="whitespace-nowrap font-semibold"><?= e(money((float) ($row['payable_amount'] ?? $row['amount']))) ?></td>
                <td><span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span></td>
                <td class="whitespace-nowrap text-[10.5px] text-slate-500"><?= e($row['provider_code']) ?></td>
                <td class="whitespace-nowrap text-[10.5px] text-slate-500"><?= e(ist($row['created_at'], 'd M, H:i')) ?></td>
                <td><a href="<?= e(url('/user/transactions.php') . query_string(['order' => $row['public_id']])) ?>" class="btn-ghost btn-sm" aria-label="View order"><i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/user/transactions.php')]); ?>
    <?php endif; ?>
</div>
