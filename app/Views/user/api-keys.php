<?php
use App\Models\ApiKeyModel;

$scopeLabels = ApiKeyModel::SCOPES;
?>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
    <div class="space-y-4">
        <?php if ($newKey): ?>
        <div class="card border-emerald-200 bg-emerald-50/70 dark:border-emerald-400/25 dark:bg-emerald-400/[0.06]">
            <div class="flex items-start gap-3 p-5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><i data-lucide="key-round" class="h-4 w-4"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-[12.5px] font-bold text-emerald-800 dark:text-emerald-200">Copy your new API key now</p>
                    <p class="mt-1 text-[10.5px] leading-5 text-emerald-700/80 dark:text-emerald-200/70">This is the only time the full key is displayed. MAXXfam stores only a SHA-256 hash.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <code class="min-w-0 flex-1 select-all break-all rounded-lg border border-emerald-200 bg-white px-3 py-2.5 font-mono text-[11px] text-emerald-800 dark:border-emerald-400/20 dark:bg-ink-900 dark:text-emerald-200"><?= e($newKey['key']) ?></code>
                        <button type="button" class="btn-primary btn-sm shrink-0" data-copy="<?= e($newKey['key']) ?>"><i data-lucide="copy" class="h-3.5 w-3.5"></i> Copy key</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
                <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Issued keys</h2><p class="text-[10.5px] text-slate-500"><?= (int) $activeCount ?> active, <?= count($keys) ?> total</p></div>
                <span class="badge-success self-start"><i data-lucide="shield-check" class="h-3 w-3"></i> Hashed at rest</span>
            </div>
            <?php if (!$keys): ?>
            <div class="px-5 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="key-round" class="h-5 w-5"></i></span>
                <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No API keys yet</p>
                <p class="mx-auto mt-1 max-w-sm text-[11px] leading-5 text-slate-500">Create a scoped key to create payments from your backend. Keys are only shown once.</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($keys as $key): $meta = status_meta($key['status']); ?>
                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[12px] font-semibold text-slate-800 dark:text-slate-100"><?= e($key['name']) ?></p>
                            <span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span>
                            <?php if ((int) $key['rotated_from'] > 0): ?><span class="badge-info">Rotated</span><?php endif; ?>
                        </div>
                        <p class="mt-1 font-mono text-[10.5px] text-slate-500"><?= e($key['key_prefix']) ?>••••••••<?= e($key['last4']) ?></p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <?php foreach ($key['scopes_array'] as $scope): ?><span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[9px] text-slate-600 dark:bg-white/5 dark:text-slate-400"><?= e($scope) ?></span><?php endforeach; ?>
                        </div>
                        <p class="mt-2 text-[9.5px] text-slate-400">Created <?= e(ist($key['created_at'], 'd M Y')) ?> · <?= $key['last_used_at'] ? 'Last used ' . e(time_ago($key['last_used_at'])) : 'Never used' ?></p>
                    </div>
                    <?php if ($key['status'] === 'active'): ?>
                    <div class="flex shrink-0 items-center gap-2">
                        <form method="post" action="<?= e(url('/user/api-keys.php')) ?>" data-confirm="Rotate this key? The current key stops working immediately and a new one is shown once." data-confirm-title="Rotate API key"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $key['id'] ?>"><input type="hidden" name="action" value="rotate"><button class="btn-secondary btn-sm"><i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Rotate</button></form>
                        <form method="post" action="<?= e(url('/user/api-keys.php')) ?>" data-confirm="Revoke this key? Any integration using it will stop working immediately." data-confirm-title="Revoke API key"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $key['id'] ?>"><input type="hidden" name="action" value="revoke"><button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Revoke</button></form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[12.5px] font-bold text-slate-900 dark:text-white">Create an API key</h2><p class="mt-0.5 text-[10px] text-slate-500">Least privilege is enforced per scope.</p></div>
            <form method="post" action="<?= e(url('/user/api-keys.php')) ?>" class="space-y-4 p-5" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div><label for="name" class="label">Key name</label><input id="name" name="name" required minlength="3" maxlength="80" class="input" placeholder="Production server"></div>
                <div>
                    <p class="label">Scopes</p>
                    <div class="space-y-2">
                        <?php foreach ($scopeLabels as $scope => $label): ?>
                        <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-[11px] hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5">
                            <input type="checkbox" name="scopes[]" value="<?= e($scope) ?>" class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= in_array($scope, ['payments:read', 'payments:write', 'profile:read'], true) ? 'checked' : '' ?>>
                            <span><span class="block font-semibold text-slate-700 dark:text-slate-200"><?= e($label) ?></span><span class="font-mono text-[9.5px] text-slate-400"><?= e($scope) ?></span></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full"><i data-lucide="plus" class="h-4 w-4"></i> Create key</button>
            </form>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-400/20 dark:bg-amber-400/[0.06]">
            <p class="flex items-center gap-2 text-[11px] font-semibold text-amber-800 dark:text-amber-200"><i data-lucide="alert-triangle" class="h-4 w-4"></i> Keep keys server-side</p>
            <p class="mt-1.5 text-[10px] leading-5 text-amber-700/80 dark:text-amber-200/70">Never embed an API key in a browser, mobile app or public repository. All API calls are logged with redacted bodies.</p>
        </div>
    </div>
</div>
