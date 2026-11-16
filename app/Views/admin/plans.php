<?php
$plans = $plans ?? [];
$editing = $editing ?? null;
$pendingRequests = $pendingRequests ?? [];
$recentRequests = $recentRequests ?? [];
$stats = $stats ?? ['plans' => 0, 'active' => 0, 'assigned' => 0, 'pending' => 0];
$canManage = $canManage ?? false;

$intervalLabel = static fn (string $i): string => match ($i) {
    'monthly' => 'Monthly',
    'yearly' => 'Yearly',
    default => 'One time',
};
$limitText = static fn (int $value): string => $value > 0 ? number_format($value) : 'Unlimited';
?>
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-4"><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Plans</p><p class="mt-1 text-[24px] font-bold text-slate-900 dark:text-white"><?= (int) $stats['plans'] ?></p><p class="text-[10px] text-slate-500"><?= (int) $stats['active'] ?> active</p></div>
    <div class="card p-4"><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Assigned merchants</p><p class="mt-1 text-[24px] font-bold text-slate-900 dark:text-white"><?= (int) $stats['assigned'] ?></p><p class="text-[10px] text-slate-500">With a subscription plan</p></div>
    <div class="card p-4"><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Pending requests</p><p class="mt-1 text-[24px] font-bold <?= (int) $stats['pending'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' ?>"><?= (int) $stats['pending'] ?></p><p class="text-[10px] text-slate-500">Awaiting approval</p></div>
    <div class="card p-4"><p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Limit policy</p><p class="mt-1 text-[13px] font-bold text-slate-900 dark:text-white">0 = Unlimited</p><p class="text-[10px] text-slate-500">Usage resets monthly (UTC)</p></div>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px] xl:items-start">
    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Plan catalogue</h2>
                <p class="text-[11px] text-slate-500">Merchants see active plans in their panel and request one; approval assigns it.</p>
            </div>
            <?php if (!$plans): ?>
            <div class="px-5 py-10 text-center text-[12px] text-slate-500">No plans yet. Create the first plan from the form.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table-mf">
                    <thead><tr><th>Plan</th><th>Price</th><th>Limits / month</th><th>Merchants</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td>
                                <p class="text-[11.5px] font-semibold text-slate-800 dark:text-slate-200"><?= e((string) $plan['name']) ?><?= (int) $plan['is_featured'] === 1 ? ' <span class="badge-info">Featured</span>' : '' ?></p>
                                <p class="font-mono text-[9.5px] text-slate-400"><?= e((string) $plan['slug']) ?></p>
                                <?php if (!empty($plan['description'])): ?><p class="mt-0.5 max-w-[280px] text-[9.5px] leading-4 text-slate-500"><?= e((string) $plan['description']) ?></p><?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px]"><?= e(money((float) $plan['price'])) ?><span class="block text-[9.5px] text-slate-400"><?= e($intervalLabel((string) $plan['billing_interval'])) ?></span></td>
                            <td class="whitespace-nowrap text-[9.5px] leading-5 text-slate-600 dark:text-slate-300">
                                <p>Orders: <?= e($limitText((int) $plan['transaction_limit'])) ?></p>
                                <p>API: <?= e($limitText((int) $plan['api_limit'])) ?></p>
                                <p>Links: <?= e($limitText((int) $plan['link_limit'])) ?></p>
                            </td>
                            <td class="text-[10.5px]"><?= (int) $plan['merchant_count'] ?></td>
                            <td><span class="<?= (int) $plan['is_active'] === 1 ? 'badge-success' : 'badge-neutral' ?>"><?= (int) $plan['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <?php if ($canManage): ?>
                                    <a href="<?= e(url('/admin/plans.php?edit=' . (int) $plan['id'])) ?>" class="btn-ghost btn-sm">Edit</a>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>"><button class="btn-ghost btn-sm"><?= (int) $plan['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form>
                                    <form method="post" data-confirm="Delete this plan? Pending requests for it will also be removed." data-confirm-title="Delete plan"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>"><button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10" <?= (int) $plan['merchant_count'] > 0 ? 'disabled title="Assigned to merchants"' : '' ?>>Delete</button></form>
                                    <?php else: ?><span class="text-[9.5px] text-slate-400">View only</span><?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Pending plan requests</h2>
                <p class="text-[11px] text-slate-500">Approve to assign the plan immediately, or reject with a note.</p>
            </div>
            <?php if (!$pendingRequests): ?>
            <div class="px-5 py-8 text-center text-[12px] text-slate-500">No pending requests.</div>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($pendingRequests as $request): ?>
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11.5px] font-semibold text-slate-800 dark:text-slate-200"><?= e((string) $request['business_name']) ?> <span class="font-mono text-[9.5px] text-slate-400"><?= e((string) $request['merchant_code']) ?></span></p>
                            <p class="text-[10px] text-slate-500"><?= e((string) ($request['owner_email'] ?? '')) ?> · requested <span class="font-semibold"><?= e((string) $request['plan_name']) ?></span></p>
                            <?php if (!empty($request['merchant_note'])): ?><p class="mt-1 rounded-lg bg-slate-50 px-2 py-1 text-[9.5px] text-slate-500 dark:bg-white/[0.03]"><?= e((string) $request['merchant_note']) ?></p><?php endif; ?>
                        </div>
                        <span class="text-[9.5px] text-slate-400"><?= e(ist((string) $request['requested_at'], 'd M, H:i')) ?></span>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                        <form method="post" class="contents"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input name="admin_note" maxlength="500" class="input h-8 text-[10.5px]" placeholder="Optional note"><input type="hidden" name="action" value="approve_request"><button class="btn-primary btn-sm">Approve</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="admin_note" value="Declined by administrator"><input type="hidden" name="action" value="reject_request"><button class="btn-ghost btn-sm text-rose-600 dark:text-rose-400">Reject</button></form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <h2 class="text-[13px] font-bold text-slate-900 dark:text-white"><?= $editing ? 'Edit plan' : 'Create plan' ?></h2>
                <p class="text-[10.5px] text-slate-500">Use 0 for unlimited. Limits reset on the 1st of each month (UTC).</p>
            </div>
            <form method="post" class="space-y-3 p-5">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                <div><label class="label" for="name">Plan name</label><input id="name" name="name" required minlength="2" maxlength="120" class="input" value="<?= e((string) ($editing['name'] ?? '')) ?>" placeholder="Growth" <?= $canManage ? '' : 'disabled' ?>></div>
                <div><label class="label" for="description">Short description</label><textarea id="description" name="description" rows="2" maxlength="1000" class="input" <?= $canManage ? '' : 'disabled' ?>><?= e((string) ($editing['description'] ?? '')) ?></textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="price">Price (INR)</label><input id="price" name="price" type="number" min="0" step="0.01" class="input font-mono text-[11px]" value="<?= e((string) ($editing['price'] ?? '0.00')) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                    <div><label class="label" for="billing_interval">Billing</label><select id="billing_interval" name="billing_interval" class="input" <?= $canManage ? '' : 'disabled' ?>><?php foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly', 'one_time' => 'One time'] as $k => $v): ?><option value="<?= e($k) ?>" <?= (string) ($editing['billing_interval'] ?? 'monthly') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div><label class="label" for="transaction_limit">Orders</label><input id="transaction_limit" name="transaction_limit" type="number" min="0" class="input font-mono text-[11px]" value="<?= (int) ($editing['transaction_limit'] ?? 0) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                    <div><label class="label" for="api_limit">API calls</label><input id="api_limit" name="api_limit" type="number" min="0" class="input font-mono text-[11px]" value="<?= (int) ($editing['api_limit'] ?? 0) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                    <div><label class="label" for="link_limit">Links</label><input id="link_limit" name="link_limit" type="number" min="0" class="input font-mono text-[11px]" value="<?= (int) ($editing['link_limit'] ?? 0) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                </div>
                <div><label class="label" for="features">Features (one per line)</label><textarea id="features" name="features" rows="4" class="input text-[11px]" placeholder="Unlimited payment links&#10;Priority support" <?= $canManage ? '' : 'disabled' ?>><?= e((string) ($editing['features'] ?? '')) ?></textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-[10.5px] text-slate-600 dark:text-slate-300"><input type="checkbox" name="is_active" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= !$editing || (int) $editing['is_active'] === 1 ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>> Active</label>
                    <label class="flex items-center gap-2 text-[10.5px] text-slate-600 dark:text-slate-300"><input type="checkbox" name="is_featured" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= $editing && (int) $editing['is_featured'] === 1 ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>> Featured</label>
                </div>
                <div><label class="label" for="sort_order">Sort order</label><input id="sort_order" name="sort_order" type="number" min="0" max="10000" class="input font-mono text-[11px]" value="<?= (int) ($editing['sort_order'] ?? 0) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                <?php if ($canManage): ?>
                <div class="flex gap-2">
                    <button class="btn-primary btn-sm flex-1"><?= $editing ? 'Save changes' : 'Create plan' ?></button>
                    <?php if ($editing): ?><a href="<?= e(url('/admin/plans.php')) ?>" class="btn-ghost btn-sm">Cancel</a><?php endif; ?>
                </div>
                <?php else: ?><p class="text-[9.5px] text-slate-400">Your role can view plans but not change them.</p><?php endif; ?>
            </form>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Recent request history</h2></div>
            <?php if (!$recentRequests): ?>
            <div class="px-5 py-6 text-center text-[11px] text-slate-500">No plan requests yet.</div>
            <?php else: ?>
            <div class="divide-y divide-slate-100 text-[10px] dark:divide-white/[0.06]">
                <?php foreach ($recentRequests as $request): ?>
                <div class="px-5 py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate font-semibold text-slate-700 dark:text-slate-300"><?= e((string) $request['business_name']) ?> · <?= e((string) $request['plan_name']) ?></span>
                        <span class="<?= $request['status'] === 'approved' ? 'badge-success' : ($request['status'] === 'rejected' ? 'badge-danger' : ($request['status'] === 'pending' ? 'badge-warning' : 'badge-neutral')) ?>"><?= e((string) $request['status']) ?></span>
                    </div>
                    <p class="mt-0.5 text-[9px] text-slate-400"><?= e(ist((string) $request['requested_at'], 'd M, H:i')) ?><?= !empty($request['reviewer_name']) ? ' · ' . e((string) $request['reviewer_name']) : '' ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
