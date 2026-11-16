<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$filters = $filters ?? [];
?>
<div class="card mb-6">
    <form method="get" class="flex flex-wrap gap-3 p-4 sm:items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="label" for="q">Search Merchant or Key Name</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search..." class="input">
        </div>
        <div class="w-40">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="input">
                <option value="">All</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="revoked" <?= ($filters['status'] ?? '') === 'revoked' ? 'selected' : '' ?>>Revoked</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">
            <i data-lucide="filter" class="h-4 w-4"></i> Filter
        </button>
        <?php if (!empty($filters['q']) || !empty($filters['status'])): ?>
            <a href="<?= e(url('/admin/api-management.php')) ?>" class="btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">API Keys (<?= (int) $result['total'] ?>)</h2>
        <p class="text-[11px] text-slate-500">Only hashed keys and prefixes are stored; raw secret keys are never visible in admin.</p>
    </div>

    <?php if (empty($result['rows'])): ?>
        <div class="px-5 py-16 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                <i data-lucide="key" class="h-6 w-6"></i>
            </span>
            <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No API keys found</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table-mf">
                <thead>
                    <tr>
                        <th>Key / Name</th>
                        <th>Merchant</th>
                        <th>Prefix / Last 4</th>
                        <th>Scopes</th>
                        <th>Status</th>
                        <th>Last Used</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <p class="text-[12px] font-bold text-slate-900 dark:text-white"><?= e($row['name']) ?></p>
                                <p class="text-[10px] text-slate-400">ID: <?= (int) $row['id'] ?></p>
                            </td>
                            <td>
                                <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"><?= e($row['business_name']) ?></p>
                                <p class="text-[9.5px] text-slate-400"><?= e($row['merchant_code']) ?></p>
                            </td>
                            <td>
                                <code class="font-mono text-[11px] text-slate-700 dark:text-slate-300"><?= e($row['key_prefix']) ?>••••<?= e($row['last4']) ?></code>
                            </td>
                            <td>
                                <?php
                                $scopes = json_decode((string) $row['scopes'], true) ?: [];
                                ?>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700 dark:bg-white/5 dark:text-slate-300">
                                    <?= count($scopes) ?> scope<?= count($scopes) === 1 ? '' : 's' ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?= $row['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                <?= !empty($row['last_used_at']) ? e(ist($row['last_used_at'], 'd M, H:i')) : 'Never' ?>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                <?= e(ist($row['created_at'], 'd M Y')) ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if ($row['status'] === 'active'): ?>
                                    <form method="post" data-confirm="Are you sure you want to revoke this API key? Merchant integrations using it will immediately fail.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <button type="submit" class="btn-danger btn-sm">
                                            Revoke
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400">Revoked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/api-management.php')]); ?>
    <?php endif; ?>
</div>
