<?php
$deliveries = $deliveries ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$endpoints = $endpoints ?? ['rows' => []];
$filters = $filters ?? [];
?>
<div class="card mb-6">
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-4 sm:items-end">
        <div>
            <label class="label" for="q">Search URL / Merchant</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search URL..." class="input">
        </div>
        <div>
            <label class="label" for="status">Delivery Status</label>
            <select id="status" name="status" class="input">
                <option value="">All States</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="success" <?= ($filters['status'] ?? '') === 'success' ? 'selected' : '' ?>>Success</option>
                <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <div>
            <label class="label" for="event">Event Type</label>
            <select id="event" name="event" class="input">
                <option value="">All Events</option>
                <?php foreach (\App\Models\WebhookModel::EVENTS as $ev): ?>
                    <option value="<?= $ev ?>" <?= ($filters['event'] ?? '') === $ev ? 'selected' : '' ?>><?= $ev ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary flex-1 justify-center">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
            <?php if (!empty($filters['q']) || !empty($filters['status']) || !empty($filters['event'])): ?>
                <a href="<?= e(url('/admin/webhooks.php')) ?>" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Delivery Queue & History</h2>
        <p class="text-[11px] text-slate-500">Every webhook is cryptographically signed with HMAC-SHA256 and retried with exponential backoff.</p>
    </div>

    <?php if (empty($deliveries['rows'])): ?>
        <div class="px-5 py-16 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                <i data-lucide="webhook" class="h-6 w-6"></i>
            </span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No webhook deliveries recorded</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>ID / Event</th>
                        <th>Merchant</th>
                        <th>Destination Endpoint</th>
                        <th>Status</th>
                        <th>HTTP Code</th>
                        <th>Attempts</th>
                        <th>Created (IST)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries['rows'] as $row): ?>
                        <tr>
                            <td>
                                <p class="font-mono text-[11px] font-bold text-slate-900 dark:text-white">#<?= (int) $row['id'] ?></p>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[9.5px] text-slate-700 dark:bg-white/5 dark:text-slate-300">
                                    <?= e($row['event']) ?>
                                </span>
                            </td>
                            <td>
                                <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"><?= e($row['business_name']) ?></p>
                            </td>
                            <td class="max-w-[220px] truncate font-mono text-[10.5px] text-slate-600 dark:text-slate-400">
                                <?= e($row['url']) ?>
                            </td>
                            <td>
                                <?php
                                $badge = match ($row['status']) {
                                    'success' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'failed' => 'badge-danger',
                                    default => 'badge-neutral',
                                };
                                ?>
                                <span class="<?= $badge ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                <?= $row['response_code'] ? 'HTTP ' . (int) $row['response_code'] : '—' ?>
                            </td>
                            <td class="text-[11px] text-slate-500">
                                <?= (int) $row['attempts'] ?>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                <?= e(ist($row['created_at'], 'd M, H:i')) ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if ($row['status'] !== 'success'): ?>
                                    <form method="post" data-loading>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="action" value="retry">
                                        <button type="submit" class="btn-secondary btn-sm" title="Deliver Now">
                                            <i data-lucide="send" class="h-3.5 w-3.5"></i> Retry
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400">Delivered</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $deliveries, 'paginationBase' => url('/admin/webhooks.php')]); ?>
    <?php endif; ?>
</div>
