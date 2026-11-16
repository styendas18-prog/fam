<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$filters = $filters ?? [];
?>
<div class="card mb-6">
    <form method="get" class="flex gap-3 p-4 sm:items-end">
        <div class="flex-1 max-w-md">
            <label class="label" for="q">Search Action / Target / Admin</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search activity..." class="input">
        </div>
        <button type="submit" class="btn-primary">
            <i data-lucide="filter" class="h-4 w-4"></i> Filter
        </button>
        <?php if (!empty($filters['q'])): ?>
            <a href="<?= e(url('/admin/activity.php')) ?>" class="btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Admin Audit Log (<?= (int) $result['total'] ?> entries)</h2>
    </div>

    <?php if (empty($result['rows'])): ?>
        <div class="px-5 py-16 text-center text-slate-500">
            <p>No activity logs recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Admin Operator</th>
                        <th>IP Address</th>
                        <th>Details</th>
                        <th>Timestamp (IST)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <span class="font-mono text-[11px] font-bold text-slate-900 dark:text-white"><?= e($row['action']) ?></span>
                            </td>
                            <td>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-700 dark:bg-white/5 dark:text-slate-300">
                                    <?= e($row['target_type'] ?: 'system') ?><?= $row['target_id'] ? '#' . (int) $row['target_id'] : '' ?>
                                </span>
                            </td>
                            <td>
                                <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"><?= e($row['admin_name'] ?: 'System / CLI') ?></p>
                                <p class="text-[9.5px] text-slate-400"><?= e($row['admin_email'] ?: '') ?></p>
                            </td>
                            <td class="font-mono text-[10.5px] text-slate-500"><?= e($row['ip']) ?></td>
                            <td class="max-w-[260px] truncate font-mono text-[10px] text-slate-500">
                                <?= e($row['details']) ?>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                <?= e(ist($row['created_at'], 'd M Y, H:i:s')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/activity.php')]); ?>
    <?php endif; ?>
</div>
