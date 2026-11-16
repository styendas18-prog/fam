<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$filters = $filters ?? [];
?>
<div class="card mb-6">
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-4 sm:items-end">
        <div>
            <label class="label" for="q">Search Path / Request ID</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="E.g. /payments/create" class="input">
        </div>
        <div>
            <label class="label" for="status">HTTP Status</label>
            <select id="status" name="status" class="input">
                <option value="">All</option>
                <option value="success" <?= ($filters['status'] ?? '') === 'success' ? 'selected' : '' ?>>Success (2xx / 3xx)</option>
                <option value="error" <?= ($filters['status'] ?? '') === 'error' ? 'selected' : '' ?>>Errors (4xx / 5xx)</option>
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
            <?php if (!empty($filters['q']) || !empty($filters['status']) || !empty($filters['from'])): ?>
                <a href="<?= e(url('/admin/api-logs.php')) ?>" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">API Log Stream (<?= (int) $result['total'] ?> entries)</h2>
        <p class="text-[11px] text-slate-500">Sensitive credentials and tokens are automatically redacted before logging.</p>
    </div>

    <?php if (empty($result['rows'])): ?>
        <div class="px-5 py-16 text-center text-slate-500">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                <i data-lucide="terminal" class="h-6 w-6"></i>
            </span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No API logs found</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Method / Path</th>
                        <th>Status</th>
                        <th>Merchant / Key</th>
                        <th>IP / Latency</th>
                        <th>Error Note</th>
                        <th>Timestamp (IST)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-1.5 font-mono text-[11px]">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-bold uppercase text-slate-700 dark:bg-white/10 dark:text-slate-300"><?= e($row['method']) ?></span>
                                    <span class="font-semibold text-slate-900 dark:text-white"><?= e($row['path']) ?></span>
                                </div>
                                <p class="font-mono text-[9.5px] text-slate-400"><?= e($row['request_id']) ?></p>
                            </td>
                            <td>
                                <?php
                                $sc = (int) $row['status_code'];
                                $badge = $sc >= 200 && $sc < 300 ? 'badge-success' : ($sc >= 400 && $sc < 500 ? 'badge-warning' : 'badge-danger');
                                ?>
                                <span class="<?= $badge ?>">HTTP <?= $sc ?></span>
                            </td>
                            <td>
                                <p class="text-[11px] font-medium text-slate-800 dark:text-slate-200"><?= e($row['business_name'] ?: 'Public / Unauth') ?></p>
                                <p class="font-mono text-[9.5px] text-slate-400"><?= e($row['key_name'] ?: '') ?></p>
                            </td>
                            <td class="whitespace-nowrap font-mono text-[10.5px]">
                                <p class="text-slate-600 dark:text-slate-400"><?= e($row['ip']) ?></p>
                                <p class="text-[9.5px] text-slate-400"><?= (int) $row['latency_ms'] ?> ms</p>
                            </td>
                            <td class="max-w-[200px] truncate text-[11px] text-rose-600 dark:text-rose-400">
                                <?= e($row['error'] ?: '—') ?>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                <?= e(ist($row['created_at'], 'd M, H:i:s')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/api-logs.php')]); ?>
    <?php endif; ?>
</div>
