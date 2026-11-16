<?php
$plans = $plans ?? [];
$currentPlan = $currentPlan ?? null;
$usage = $usage ?? ['transactions' => 0, 'api' => 0, 'links' => 0];
$limits = $limits ?? ['transactions' => null, 'api' => null, 'links' => null];
$pending = $pending ?? null;
$history = $history ?? [];

$intervalLabel = static fn (string $i): string => match ($i) {
    'monthly' => 'per month',
    'yearly' => 'per year',
    default => 'one time',
};
$usable = $currentPlan !== null && \App\Services\PlanService::planIsUsable($currentPlan);
$usageDefs = [
    'transactions' => ['label' => 'Payment orders', 'icon' => 'receipt-text'],
    'api' => ['label' => 'API requests', 'icon' => 'braces'],
    'links' => ['label' => 'Payment links', 'icon' => 'link-2'],
];
?>
<div class="mb-6 grid gap-4 lg:grid-cols-[1.15fr_.85fr]">
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Current plan</p>
                <?php if ($currentPlan): ?>
                <h2 class="mt-1 text-[22px] font-bold text-slate-900 dark:text-white"><?= e((string) $currentPlan['name']) ?></h2>
                <p class="mt-0.5 text-[11px] text-slate-500"><?= e(money((float) $currentPlan['price'])) ?> <?= e($intervalLabel((string) $currentPlan['billing_interval'])) ?></p>
                <?php else: ?>
                <h2 class="mt-1 text-[20px] font-bold text-slate-900 dark:text-white">No plan assigned</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">You currently have no plan-level limits. Request a plan below to see usage limits.</p>
                <?php endif; ?>
            </div>
            <?php if ($currentPlan): ?>
                <span class="<?= $usable ? 'badge-success' : 'badge-danger' ?>"><?= $usable ? 'Active' : 'Expired / inactive' ?></span>
            <?php else: ?>
                <span class="badge-neutral">No plan</span>
            <?php endif; ?>
        </div>
        <?php if ($currentPlan): ?>
        <div class="mt-4 grid gap-2 text-[10px] sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10"><p class="font-bold uppercase tracking-[0.1em] text-slate-400">Started</p><p class="mt-1 text-[10.5px] text-slate-700 dark:text-slate-300"><?= $currentPlan['plan_started_at'] ? e(ist((string) $currentPlan['plan_started_at'], 'd M Y, H:i')) : '—' ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10"><p class="font-bold uppercase tracking-[0.1em] text-slate-400">Expires</p><p class="mt-1 text-[10.5px] text-slate-700 dark:text-slate-300"><?= $currentPlan['plan_expires_at'] ? e(ist((string) $currentPlan['plan_expires_at'], 'd M Y, H:i')) : 'Lifetime' ?></p></div>
        </div>
        <?php if (!$usable): ?>
        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[10px] leading-5 text-amber-800 dark:bg-amber-400/10 dark:text-amber-200">Your plan is no longer active. New payments and API requests are blocked until a new plan is approved.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card p-5">
        <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">This month's usage</p>
        <div class="mt-3 space-y-3">
            <?php foreach ($usageDefs as $key => $def): ?>
            <?php
            $used = (int) ($usage[$key] ?? 0);
            $limit = $limits[$key] ?? null;
            $pct = $limit !== null && $limit > 0 ? min(100, (int) round($used / $limit * 100)) : null;
            ?>
            <div>
                <div class="flex items-center justify-between gap-2 text-[10.5px]">
                    <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"><i data-lucide="<?= e($def['icon']) ?>" class="h-3.5 w-3.5 text-slate-400"></i> <?= e($def['label']) ?></span>
                    <span class="font-mono text-[10px] text-slate-500"><?= number_format($used) ?> / <?= $limit === null ? 'Unlimited' : number_format($limit) ?></span>
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                    <div class="h-full rounded-full <?= $pct !== null && $pct >= 100 ? 'bg-rose-500' : ($pct !== null && $pct >= 80 ? 'bg-amber-500' : 'bg-brand-500') ?>" style="width: <?= $pct === null ? 0 : $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-[9px] text-slate-400">Usage resets on the 1st of every month (UTC). A limit of 0 means unlimited.</p>
    </div>
</div>

<?php if ($pending): ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-400/20 dark:bg-amber-400/[0.06]">
    <div>
        <p class="text-[11px] font-semibold text-amber-800 dark:text-amber-200">Plan request pending: <?= e((string) $pending['plan_name']) ?></p>
        <p class="text-[10px] text-amber-700/80 dark:text-amber-200/70">Requested <?= e(ist((string) $pending['requested_at'], 'd M, H:i')) ?>. An administrator will review it.</p>
    </div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="request_id" value="<?= (int) $pending['id'] ?>"><button class="btn-ghost btn-sm text-amber-800 dark:text-amber-200">Cancel request</button></form>
</div>
<?php endif; ?>

<div class="card overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Available plans</h2>
        <p class="text-[11px] text-slate-500">Choose a plan and submit a request. The plan activates only after administrator approval.</p>
    </div>
    <?php if (!$plans): ?>
    <div class="px-5 py-10 text-center text-[12px] text-slate-500">No plans are available right now. Contact support.</div>
    <?php else: ?>
    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($plans as $plan): ?>
            <?php
            $isCurrent = $currentPlan && (int) $currentPlan['id'] === (int) $plan['id'];
            $features = \App\Services\PlanService::featureList($plan['features'] ?? null);
            $planLimits = \App\Services\PlanService::limits($plan);
            ?>
            <div class="relative flex flex-col rounded-2xl border <?= (int) $plan['is_featured'] === 1 ? 'border-brand-500/40 bg-brand-50/40 dark:border-brand-400/30 dark:bg-brand-400/[0.04]' : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.02]' ?> p-4">
                <?php if ((int) $plan['is_featured'] === 1): ?><span class="badge-info absolute right-3 top-3">Featured</span><?php endif; ?>
                <h3 class="text-[14px] font-bold text-slate-900 dark:text-white"><?= e((string) $plan['name']) ?></h3>
                <p class="mt-1 text-[19px] font-bold text-brand-600 dark:text-brand-300"><?= e(money((float) $plan['price'])) ?> <span class="text-[10px] font-medium text-slate-400"><?= e($intervalLabel((string) $plan['billing_interval'])) ?></span></p>
                <?php if (!empty($plan['description'])): ?><p class="mt-1.5 text-[10px] leading-5 text-slate-500"><?= e((string) $plan['description']) ?></p><?php endif; ?>
                <ul class="mt-3 space-y-1.5 text-[10px] text-slate-600 dark:text-slate-300">
                    <li class="flex items-center gap-1.5"><i data-lucide="receipt-text" class="h-3 w-3 text-emerald-500"></i> <?= $planLimits['transactions'] === null ? 'Unlimited payment orders' : number_format($planLimits['transactions']) . ' payment orders / month' ?></li>
                    <li class="flex items-center gap-1.5"><i data-lucide="braces" class="h-3 w-3 text-emerald-500"></i> <?= $planLimits['api'] === null ? 'Unlimited API requests' : number_format($planLimits['api']) . ' API requests / month' ?></li>
                    <li class="flex items-center gap-1.5"><i data-lucide="link-2" class="h-3 w-3 text-emerald-500"></i> <?= $planLimits['links'] === null ? 'Unlimited payment links' : number_format($planLimits['links']) . ' payment links / month' ?></li>
                </ul>
                <?php if ($features): ?>
                <ul class="mt-3 space-y-1 text-[9.5px] text-slate-500">
                    <?php foreach ($features as $feature): ?><li class="flex items-start gap-1.5"><i data-lucide="check" class="mt-0.5 h-3 w-3 shrink-0 text-emerald-500"></i><span><?= e($feature) ?></span></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="mt-4 flex-1"></div>
                <?php if ($isCurrent): ?>
                    <span class="badge-success w-full justify-center py-2">Current plan</span>
                <?php elseif ($pending): ?>
                    <button disabled class="btn-secondary btn-sm w-full justify-center opacity-50">Request pending</button>
                <?php else: ?>
                    <form method="post" class="space-y-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request">
                        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                        <input name="note" maxlength="500" class="input h-8 text-[10px]" placeholder="Optional note to admin">
                        <button class="btn-primary btn-sm w-full justify-center">Request <?= e((string) $plan['name']) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($history): ?>
<div class="card mt-6 overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Request history</h2></div>
    <div class="overflow-x-auto">
        <table class="table-mf">
            <thead><tr><th>Requested</th><th>Plan</th><th>Status</th><th>Admin note</th><th>Reviewed</th></tr></thead>
            <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td class="whitespace-nowrap text-[10px]"><?= e(ist((string) $row['requested_at'], 'd M, H:i')) ?></td>
                    <td class="text-[10.5px] font-semibold"><?= e((string) $row['plan_name']) ?></td>
                    <td><span class="<?= $row['status'] === 'approved' ? 'badge-success' : ($row['status'] === 'rejected' ? 'badge-danger' : ($row['status'] === 'pending' ? 'badge-warning' : 'badge-neutral')) ?>"><?= e((string) $row['status']) ?></span></td>
                    <td class="max-w-[260px] text-[9.5px] text-slate-500"><?= e((string) ($row['admin_note'] ?? '—')) ?></td>
                    <td class="whitespace-nowrap text-[9.5px] text-slate-500"><?= $row['reviewed_at'] ? e(ist((string) $row['reviewed_at'], 'd M, H:i')) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
