<?php
$eventOptions = $events ?? \App\Models\WebhookModel::EVENTS;
?>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
    <div class="space-y-4">
        <?php if ($newSecret): ?>
        <div class="card border-emerald-200 bg-emerald-50/70 dark:border-emerald-400/25 dark:bg-emerald-400/[0.06]">
            <div class="flex items-start gap-3 p-5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><i data-lucide="webhook" class="h-4 w-4"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-[12.5px] font-bold text-emerald-800 dark:text-emerald-200">Signing secret — displayed once</p>
                    <p class="mt-1 text-[10.5px] leading-5 text-emerald-700/80 dark:text-emerald-200/70">Verify every delivery with HMAC-SHA256 over the raw body. The secret is encrypted at rest and never returned by the API.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <code class="min-w-0 flex-1 select-all break-all rounded-lg border border-emerald-200 bg-white px-3 py-2.5 font-mono text-[11px] text-emerald-800 dark:border-emerald-400/20 dark:bg-ink-900 dark:text-emerald-200"><?= e($newSecret['secret']) ?></code>
                        <button type="button" class="btn-primary btn-sm shrink-0" data-copy="<?= e($newSecret['secret']) ?>"><i data-lucide="copy" class="h-3.5 w-3.5"></i> Copy secret</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Endpoints</h2><p class="text-[10.5px] text-slate-500">Every delivery is signed and retried with backoff.</p></div>
                <span class="badge-info"><?= count($hooks) ?> endpoint<?= count($hooks) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (!$hooks): ?>
            <div class="px-5 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="webhook" class="h-5 w-5"></i></span>
                <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No webhook endpoint</p>
                <p class="mx-auto mt-1 max-w-sm text-[11px] leading-5 text-slate-500">Add an HTTPS endpoint to receive provider-verified payment events.</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($hooks as $hook): $meta = status_meta((int) $hook['is_active'] === 1 ? 'active' : 'disabled'); ?>
                <div class="px-5 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="break-all font-mono text-[11px] font-semibold text-slate-800 dark:text-slate-100"><?= e($hook['url']) ?></p>
                                <span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1"><?php foreach ($hook['events_array'] as $event): ?><span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[9px] text-slate-600 dark:bg-white/5 dark:text-slate-400"><?= e($event) ?></span><?php endforeach; ?></div>
                            <p class="mt-2 text-[9.5px] text-slate-400">Last delivery <?= $hook['last_delivery_at'] ? e(time_ago($hook['last_delivery_at'])) : 'never' ?> · Failures <?= (int) $hook['failure_count'] ?></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <form method="post" action="<?= e(url('/user/webhooks.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $hook['id'] ?>"><input type="hidden" name="action" value="test"><button class="btn-secondary btn-sm"><i data-lucide="send" class="h-3.5 w-3.5"></i> Test</button></form>
                            <form method="post" action="<?= e(url('/user/webhooks.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $hook['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn-ghost btn-sm"><?= (int) $hook['is_active'] === 1 ? 'Pause' : 'Resume' ?></button></form>
                            <form method="post" action="<?= e(url('/user/webhooks.php')) ?>" data-confirm="Rotate the signing secret? Your endpoint verifier must be updated immediately or deliveries will fail verification." data-confirm-title="Rotate signing secret"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $hook['id'] ?>"><input type="hidden" name="action" value="rotate"><button class="btn-ghost btn-sm">Rotate secret</button></form>
                            <form method="post" action="<?= e(url('/user/webhooks.php')) ?>" data-confirm="Delete this endpoint and all of its queued deliveries?" data-confirm-title="Delete webhook"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $hook['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Delete</button></form>
                        </div>
                    </div>
                    <details class="mt-3 rounded-lg border border-slate-200 dark:border-white/10">
                        <summary class="cursor-pointer px-3 py-2 text-[10.5px] font-semibold text-slate-600 dark:text-slate-300">Edit endpoint and events</summary>
                        <form method="post" action="<?= e(url('/user/webhooks.php')) ?>" class="space-y-3 border-t border-slate-200 p-3 dark:border-white/10" data-loading>
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $hook['id'] ?>"><input type="hidden" name="action" value="update">
                            <input name="url" required maxlength="500" value="<?= e($hook['url']) ?>" class="input h-9 font-mono text-[11px]">
                            <div class="grid gap-1.5 sm:grid-cols-2"><?php foreach ($eventOptions as $event): ?><label class="flex items-center gap-2 text-[10.5px] text-slate-600 dark:text-slate-300"><input type="checkbox" name="events[]" value="<?= e($event) ?>" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= in_array($event, $hook['events_array'], true) ? 'checked' : '' ?>> <?= e($event) ?></label><?php endforeach; ?></div>
                            <button class="btn-primary btn-sm">Save changes</button>
                        </form>
                    </details>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
                <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Delivery log</h2><p class="text-[10.5px] text-slate-500"><?= (int) $deliveries['total'] ?> deliveries recorded.</p></div>
                <form method="get" action="<?= e(url('/user/webhooks.php')) ?>" class="flex flex-wrap gap-2">
                    <select name="delivery_status" class="input h-8 w-auto text-[10.5px]"><option value="">All statuses</option><?php foreach (['pending', 'success', 'failed'] as $s): ?><option value="<?= $s ?>" <?= $deliveryFilters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
                    <select name="delivery_event" class="input h-8 w-auto text-[10.5px]"><option value="">All events</option><?php foreach ($eventOptions as $event): ?><option value="<?= e($event) ?>" <?= $deliveryFilters['event'] === $event ? 'selected' : '' ?>><?= e($event) ?></option><?php endforeach; ?></select>
                    <button class="btn-secondary btn-sm">Filter</button>
                </form>
            </div>
            <?php if (!$deliveries['rows']): ?>
            <p class="px-5 py-12 text-center text-[11px] text-slate-500">No deliveries match this view.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table-mf">
                    <thead><tr><th>Event</th><th>Endpoint</th><th>Status</th><th>Attempts</th><th>Response</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($deliveries['rows'] as $row): $meta = status_meta($row['status']); ?>
                    <tr>
                        <td class="whitespace-nowrap font-mono text-[10px] text-slate-700 dark:text-slate-300"><?= e($row['event']) ?></td>
                        <td class="max-w-[220px] truncate font-mono text-[9.5px] text-slate-500" title="<?= e($row['url']) ?>"><?= e($row['url']) ?></td>
                        <td><span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span></td>
                        <td class="text-[10.5px]"><?= (int) $row['attempts'] ?></td>
                        <td class="text-[10.5px] text-slate-500"><?= $row['response_code'] ? 'HTTP ' . (int) $row['response_code'] : '—' ?></td>
                        <td class="whitespace-nowrap text-[10px] text-slate-500"><?= e(time_ago($row['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php \App\Core\View::partial('partials.pagination', ['pagination' => $deliveries, 'paginationBase' => url('/user/webhooks.php')]); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[12.5px] font-bold text-slate-900 dark:text-white">Add endpoint</h2><p class="mt-0.5 text-[10px] text-slate-500">HTTPS only. Private network targets are blocked.</p></div>
            <form method="post" action="<?= e(url('/user/webhooks.php')) ?>" class="space-y-4 p-5" data-loading>
                <?= csrf_field() ?><input type="hidden" name="action" value="create">
                <div><label for="url" class="label">Endpoint URL</label><input id="url" name="url" type="url" required maxlength="500" class="input font-mono text-[11px]" placeholder="https://api.your-site.com/maxxfam/webhook"></div>
                <div>
                    <p class="label">Events</p>
                    <div class="space-y-2">
                        <?php foreach ($eventOptions as $event): ?>
                        <label class="flex items-center gap-2.5 text-[11px] text-slate-600 dark:text-slate-300"><input type="checkbox" name="events[]" value="<?= e($event) ?>" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= in_array($event, ['payment.success', 'payment.failed', 'payment.expired'], true) ? 'checked' : '' ?>> <span class="font-mono text-[10px]"><?= e($event) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full"><i data-lucide="plus" class="h-4 w-4"></i> Add endpoint</button>
            </form>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-900">
            <h3 class="text-[12px] font-bold text-slate-900 dark:text-white">Verify a delivery</h3>
            <p class="mt-1.5 text-[10px] leading-5 text-slate-500">The signature is HMAC-SHA256 of the raw request body using your secret. Compare it in constant time.</p>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 p-3 font-mono text-[9.5px] leading-5 text-slate-300">$expected = hash_hmac('sha256', $raw, $secret);
hash_equals($expected, $_SERVER['HTTP_X_MAXXFAM_SIGNATURE']);</pre>
        </div>
    </div>
</div>
