<?php
$statusOptions = ['' => 'All statuses', 'active' => 'Active', 'suspended' => 'Suspended', 'pending' => 'Pending'];
$canManage = App\Core\Auth::adminCan(App\Core\Auth::admin(), 'users.manage');
?>
<?php if ($detail): $meta = status_meta($detail['status']); ?>
<div class="mb-4 card">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
        <div class="flex items-center gap-3">
            <span class="avatar h-10 w-10 text-[12px]"><?= e(initials($detail['name'])) ?></span>
            <div><p class="text-[13px] font-bold text-slate-900 dark:text-white"><?= e($detail['name']) ?></p><p class="text-[10.5px] text-slate-500"><?= e($detail['email']) ?> · joined <?= e(ist($detail['created_at'], 'd M Y')) ?></p></div>
        </div>
        <div class="flex flex-wrap items-center gap-2"><span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span><a href="<?= e(url('/admin/users.php')) ?>" class="btn-ghost btn-sm">Close</a></div>
    </div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Merchant</p><p class="mt-1 text-[12px] font-semibold text-slate-800 dark:text-slate-100"><?= e($detail['business_name'] ?: 'No merchant profile') ?></p><p class="font-mono text-[9.5px] text-slate-400"><?= e($detail['merchant_code'] ?: '—') ?></p></div>
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Verified volume</p><p class="mt-1 text-[15px] font-bold text-slate-900 dark:text-white"><?= e(money((float) ($summary['volume'] ?? 0))) ?></p></div>
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Orders</p><p class="mt-1 text-[15px] font-bold text-slate-900 dark:text-white"><?= (int) ($summary['orders'] ?? 0) ?></p><p class="text-[9.5px] text-slate-400"><?= (int) ($summary['success'] ?? 0) ?> successful</p></div>
        <div><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Last sign-in</p><p class="mt-1 text-[11.5px] font-semibold text-slate-800 dark:text-slate-100"><?= $detail['last_login_at'] ? e(time_ago($detail['last_login_at'])) : 'Never' ?></p><p class="font-mono text-[9.5px] text-slate-400"><?= e($detail['last_login_ip'] ?: '—') ?></p></div>
    </div>
    <div class="grid gap-4 border-t border-slate-100 px-5 py-4 text-[11px] sm:grid-cols-2 lg:grid-cols-4 dark:border-white/[0.06]">
        <div><span class="text-slate-500">KYC:</span> <?php $k = status_meta((string) ($detail['kyc_status'] ?? 'not_submitted')); ?><span class="<?= e($k['class']) ?>"><?= e($k['label']) ?></span></div>
        <div><span class="text-slate-500">Business:</span> <span class="text-slate-700 dark:text-slate-300"><?= e($detail['business_type'] ? ucwords(str_replace('_', ' ', $detail['business_type'])) : '—') ?></span></div>
        <div><span class="text-slate-500">PAN:</span> <span class="font-mono text-slate-700 dark:text-slate-300"><?= e($detail['pan'] ?: '—') ?></span></div>
        <div><span class="text-slate-500">GSTIN:</span> <span class="font-mono text-slate-700 dark:text-slate-300"><?= e($detail['gstin'] ?: '—') ?></span></div>
    </div>
    <?php if ($canManage): ?>
    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-5 py-3.5 dark:border-white/[0.06]">
        <?php foreach (['active' => 'Activate', 'suspended' => 'Suspend', 'pending' => 'Set pending'] as $status => $label): if ($detail['status'] === $status) continue; ?>
        <form method="post" action="<?= e(url('/admin/users.php')) ?>" data-confirm="Change this user's status to <?= e($status) ?>? <?= $status === 'suspended' ? 'Their dashboard access and API keys will stop working.' : '' ?>" data-confirm-title="Change user status"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $detail['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($status) ?>"><button class="btn-secondary btn-sm <?= $status === 'suspended' ? '!text-rose-600 dark:!text-rose-400' : '' ?>"><?= e($label) ?></button></form>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('/admin/users.php')) ?>" class="ml-auto" data-confirm="Soft-delete this user? This revokes access immediately and keeps financial records intact." data-confirm-title="Delete user"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $detail['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-danger btn-sm"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete user</button></form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
        <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Accounts</h2><p class="text-[10.5px] text-slate-500"><?= (int) $result['total'] ?> user<?= $result['total'] === 1 ? '' : 's' ?> match this view.</p></div>
    </div>
    <form method="get" action="<?= e(url('/admin/users.php')) ?>" class="grid gap-2 border-b border-slate-100 px-5 py-3 sm:grid-cols-[1fr_180px_auto] dark:border-white/[0.06]">
        <input name="q" value="<?= e($filters['q']) ?>" class="input h-9 text-[11px]" placeholder="Name, email, business or merchant code">
        <select name="status" class="input h-9 text-[11px]"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <div class="flex gap-2"><button class="btn-primary btn-sm">Filter</button><a href="<?= e(url('/admin/users.php')) ?>" class="btn-ghost btn-sm">Reset</a></div>
    </form>
    <?php if (!$result['rows']): ?>
    <p class="px-5 py-16 text-center text-[11px] text-slate-500">No users match this view.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table-mf">
            <thead><tr><th>User</th><th>Business</th><th>Status</th><th>Orders</th><th>Verified volume</th><th>Last sign-in</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): $meta = status_meta($row['status']); $bizMeta = status_meta((string) ($row['merchant_status'] ?? 'pending')); ?>
            <tr>
                <td><p class="max-w-[170px] truncate text-[11.5px] font-semibold text-slate-800 dark:text-slate-100"><?= e($row['name']) ?></p><p class="max-w-[190px] truncate text-[9.5px] text-slate-400"><?= e($row['email']) ?></p></td>
                <td><p class="max-w-[140px] truncate text-[11px] text-slate-700 dark:text-slate-300"><?= e($row['business_name'] ?: '—') ?></p><p class="font-mono text-[9px] text-slate-400"><?= e($row['merchant_code'] ?: '') ?></p></td>
                <td><span class="<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span><?php if ($row['merchant_status']): ?><p class="mt-1"><span class="<?= e($bizMeta['class']) ?>">Merchant <?= e($bizMeta['label']) ?></span></p><?php endif; ?></td>
                <td class="font-semibold"><?= (int) $row['order_count'] ?></td>
                <td class="whitespace-nowrap font-semibold"><?= e(money((float) $row['verified_volume'])) ?></td>
                <td class="whitespace-nowrap text-[10px] text-slate-500"><?= $row['last_login_at'] ? e(time_ago($row['last_login_at'])) : 'Never' ?></td>
                <td><a href="<?= e(url('/admin/users.php?id=' . (int) $row['id'])) ?>" class="btn-ghost btn-sm" aria-label="View user"><i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/users.php')]); ?>
    <?php endif; ?>
</div>
