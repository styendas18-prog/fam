<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$filters = $filters ?? [];
?>
<div class="card mb-6">
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-4 sm:items-end">
        <div>
            <label class="label" for="q">Search Event / User / Details</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="E.g. login_failed" class="input">
        </div>
        <div>
            <label class="label" for="severity">Severity</label>
            <select id="severity" name="severity" class="input">
                <option value="">All</option>
                <option value="info" <?= ($filters['severity'] ?? '') === 'info' ? 'selected' : '' ?>>Info</option>
                <option value="warning" <?= ($filters['severity'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning</option>
                <option value="critical" <?= ($filters['severity'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
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
            <?php if (!empty($filters['q']) || !empty($filters['severity']) || !empty($filters['from'])): ?>
                <a href="<?= e(url('/admin/security-logs.php')) ?>" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Security Events (<?= (int) $result['total'] ?> entries)</h2>
    </div>

    <?php if (empty($result['rows'])): ?>
        <div class="px-5 py-16 text-center text-slate-500">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                <i data-lucide="shield-check" class="h-6 w-6"></i>
            </span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No security incidents logged</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Severity</th>
                        <th>User / Admin</th>
                        <th>IP Address</th>
                        <th>Details</th>
                        <th>Timestamp (IST)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <span class="font-mono text-[11px] font-bold text-slate-900 dark:text-white"><?= e($row['event']) ?></span>
                            </td>
                            <td>
                                <?php
                                $badge = match ($row['severity']) {
                                    'critical' => 'badge-danger',
                                    'warning' => 'badge-warning',
                                    default => 'badge-neutral',
                                };
                                ?>
                                <span class="<?= $badge ?>"><?= ucfirst($row['severity']) ?></span>
                            </td>
                            <td>
                                <p class="text-[11px] text-slate-700 dark:text-slate-300"><?= e($row['user_email'] ?? $row['admin_email'] ?? 'System / Anonymous') ?></p>
                            </td>
                            <td class="font-mono text-[10.5px] text-slate-500"><?= e($row['ip']) ?></td>
                            <td class="max-w-[260px] truncate font-mono text-[10px] text-slate-500">
                                <?= e($row['details']) ?>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                <?= e(ist($row['created_at'], 'd M, H:i:s')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/security-logs.php')]); ?>
    <?php endif; ?>
</div>
