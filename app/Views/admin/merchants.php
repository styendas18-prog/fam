<?php
$statusOptions = ['' => 'All statuses', 'active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'];
$kycOptions = ['' => 'All KYC', 'not_submitted' => 'Not submitted', 'pending' => 'Pending review', 'verified' => 'Verified', 'rejected' => 'Rejected'];
$canManage = App\Core\Auth::adminCan(App\Core\Auth::admin(), 'users.manage');
?>
<?php if ($detail): $m = status_meta($detail['status']); $k = status_meta($detail['kyc_status'] ?? 'not_submitted'); ?>
<div class="mb-4 card">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
        <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white"><?= e($detail['business_name']) ?></h2><span class="<?= e($m['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($m['dot']) ?>"></span> <?= e($m['label']) ?></span><span class="<?= e($k['class']) ?>">KYC <?= e($k['label']) ?></span></div><p class="mt-1 font-mono text-[9.5px] text-slate-400"><?= e($detail['merchant_code']) ?> · owner <?= e($detail['owner_name']) ?> (<?= e($detail['owner_email']) ?>)</p></div>
        <a href="<?= e(url('/admin/merchants.php')) ?>" class="btn-ghost btn-sm self-start">Close</a>
    </div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Verified volume</p><p class="mt-1 text-[16px] font-bold text-slate-900 dark:text-white"><?= e(money((float) $stats['success_volume'])) ?></p><p class="text-[9.5px] text-slate-400"><?= (int) $stats['verified_count'] ?> transactions</p></div>
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Orders</p><p class="mt-1 text-[16px] font-bold text-slate-900 dark:text-white"><?= (int) $stats['total_orders'] ?></p><p class="text-[9.5px] text-slate-400"><?= (int) $stats['pending_orders'] ?> pending · <?= (int) $stats['failed_orders'] ?> failed</p></div>
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Success rate</p><p class="mt-1 text-[16px] font-bold text-slate-900 dark:text-white"><?= number_format((float) $stats['success_rate'], 1) ?>%</p><p class="text-[9.5px] text-slate-400"><?= (int) $stats['today_count'] ?> today</p></div>
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Owner status</p><p class="mt-1 text-[13px] font-semibold text-slate-800 dark:text-slate-100"><?= e(ucfirst((string) $detail['owner_status'])) ?></p><p class="text-[9.5px] text-slate-400">Last login <?= $detail['last_login_at'] ? e(time_ago($detail['last_login_at'])) : 'never' ?></p></div>
    </div>
    <div class="grid gap-4 border-t border-slate-100 px-5 py-4 text-[11px] sm:grid-cols-2 lg:grid-cols-4 dark:border-white/[0.06]">
        <div><span class="text-slate-500">Legal name:</span> <span class="text-slate-700 dark:text-slate-300"><?= e($detail['legal_name'] ?: '—') ?></span></div>
        <div><span class="text-slate-500">Type:</span> <span class="text-slate-700 dark:text-slate-300"><?= e($detail['business_type'] ? ucwords(str_replace('_', ' ', $detail['business_type'])) : '—') ?></span></div>
        <div><span class="text-slate-500">PAN:</span> <span class="font-mono text-slate-700 dark:text-slate-300"><?= e($detail['pan'] ?: '—') ?></span></div>
        <div><span class="text-slate-500">GSTIN:</span> <span class="font-mono text-slate-700 dark:text-slate-300"><?= e($detail['gstin'] ?: '—') ?></span></div>
        <div><span class="text-slate-500">Website:</span> <span class="truncate text-slate-700 dark:text-slate-300"><?= e($detail['website'] ?: '—') ?></span></div>
        <div><span class="text-slate-500">Support email:</span> <span class="text-slate-700 dark:text-slate-300"><?= e($detail['support_email'] ?: '—') ?></span></div>
        <div class="sm:col-span-2"><span class="text-slate-500">Address:</span> <span class="text-slate-700 dark:text-slate-300"><?= e(trim(implode(', ', array_filter([$detail['address_line1'], $detail['address_line2'], $detail['city'], $detail['state'], $detail['postal_code']]))) ?: '—') ?></span></div>
    </div>
    <?php if ($canManage): ?>
    <div class="grid gap-4 border-t border-slate-100 px-5 py-4 lg:grid-cols-2 dark:border-white/[0.06]">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Merchant status</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <?php foreach (['active' => 'Activate', 'pending' => 'Set pending', 'suspended' => 'Suspend'] as $status => $label): if ($detail['status'] === $status) continue; ?>
                <form method="post" action="<?= e(url('/admin/merchants.php')) ?>" data-confirm="Change merchant status to <?= e($status) ?>? Suspension blocks new payments and API access." data-confirm-title="Change merchant status"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $detail['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($status) ?>"><button class="btn-secondary btn-sm <?= $status === 'suspended' ? '!text-rose-600 dark:!text-rose-400' : '' ?>"><?= e($label) ?></button></form>
                <?php endforeach; ?>
            </div>
        </div>
        <form method="post" action="<?= e(url('/admin/merchants.php')) ?>" class="space-y-2" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
            <input type="hidden" name="action" value="kyc">
            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">KYC review</p>
            <div class="grid gap-2 sm:grid-cols-[150px_1fr_auto]">
                <select name="kyc_status" class="input h-9 text-[11px]"><?php foreach (['not_submitted' => 'Not submitted', 'pending' => 'Pending review', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($detail['kyc_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                <input name="kyc_notes" maxlength="2000" class="input h-9 text-[11px]" value="<?= e($detail['kyc_notes'] ?? '') ?>" placeholder="Internal review notes">
                <button class="btn-primary btn-sm">Save KYC</button>
            </div>
            <p class="text-[9.5px] text-slate-400">Only mark verified after reviewing genuine documents outside this demo environment.</p>
        </form>
    </div>
    <?php endif; ?>
    <div class="border-t border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Provider readiness</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <?php foreach ($providerState as $p): $pm = status_meta((string) ($p['connection_status'] ?? 'not_configured')); ?>
            <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2.5 dark:border-white/10"><div><p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200"><?= e($p['name']) ?></p><p class="font-mono text-[9px] text-slate-400"><?= $p['key_last4'] ? 'Key ••••' . e($p['key_last4']) : 'No merchant credential' ?></p></div><span class="<?= e($pm['class']) ?> shrink-0"><?= e($pm['label']) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$providerState): ?><p class="text-[10.5px] text-slate-500">No providers installed.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Merchant directory</h2><p class="text-[10.5px] text-slate-500"><?= (int) $result['total'] ?> merchant<?= $result['total'] === 1 ? '' : 's' ?> match this view.</p></div>
    <form method="get" action="<?= e(url('/admin/merchants.php')) ?>" class="grid gap-2 border-b border-slate-100 px-5 py-3 sm:grid-cols-2 lg:grid-cols-[1fr_150px_150px_auto] dark:border-white/[0.06]">
        <input name="q" value="<?= e($filters['q']) ?>" class="input h-9 text-[11px]" placeholder="Business, code, owner name or email">
        <select name="status" class="input h-9 text-[11px]"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="kyc" class="input h-9 text-[11px]"><?php foreach ($kycOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['kyc'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <div class="flex gap-2"><button class="btn-primary btn-sm">Filter</button><a href="<?= e(url('/admin/merchants.php')) ?>" class="btn-ghost btn-sm">Reset</a></div>
    </form>
    <?php if (!$result['rows']): ?>
    <p class="px-5 py-16 text-center text-[11px] text-slate-500">No merchants match this view.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table-mf">
            <thead><tr><th>Merchant</th><th>Owner</th><th>Status</th><th>KYC</th><th>Orders</th><th>Verified</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): $sm = status_meta($row['status']); $km = status_meta($row['kyc_status'] ?? 'not_submitted'); ?>
            <tr>
                <td><p class="max-w-[170px] truncate text-[11.5px] font-semibold text-slate-800 dark:text-slate-100"><?= e($row['business_name']) ?></p><p class="font-mono text-[9px] text-slate-400"><?= e($row['merchant_code']) ?></p></td>
                <td><p class="max-w-[160px] truncate text-[11px] text-slate-700 dark:text-slate-300"><?= e($row['owner_name']) ?></p><p class="max-w-[180px] truncate text-[9px] text-slate-400"><?= e($row['owner_email']) ?></p></td>
                <td><span class="<?= e($sm['class']) ?>"><?= e($sm['label']) ?></span></td>
                <td><span class="<?= e($km['class']) ?>"><?= e($km['label']) ?></span></td>
                <td class="font-semibold"><?= (int) $row['order_count'] ?></td>
                <td class="whitespace-nowrap"><p class="font-semibold"><?= e(money((float) $row['verified_volume'])) ?></p><p class="text-[9px] text-slate-400"><?= (int) $row['verified_count'] ?> txns</p></td>
                <td><a href="<?= e(url('/admin/merchants.php?id=' . (int) $row['id'])) ?>" class="btn-ghost btn-sm" aria-label="View merchant"><i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/merchants.php')]); ?>
    <?php endif; ?>
</div>
