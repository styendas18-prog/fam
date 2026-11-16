<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
?>
<div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
            <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">All Announcements (<?= (int) $result['total'] ?>)</h2>
            <p class="text-[11px] text-slate-500">Publishing an announcement dispatches in-dashboard alerts and email queue entries.</p>
        </div>

        <?php if (empty($result['rows'])): ?>
            <div class="px-5 py-16 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                    <i data-lucide="megaphone" class="h-6 w-6"></i>
                </span>
                <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No announcements yet</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($result['rows'] as $row): ?>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-[14px] font-bold text-slate-900 dark:text-white"><?= e($row['title']) ?></h3>
                                    <?php if ((int) $row['is_published'] === 1): ?>
                                        <span class="badge-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge-warning">Draft</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 text-[12px] leading-relaxed text-slate-600 dark:text-slate-300 whitespace-pre-wrap"><?= e($row['body']) ?></p>
                                <div class="mt-3 flex items-center gap-4 text-[10.5px] text-slate-400">
                                    <span>Audience: <b><?= e($row['audience']) ?></b></span>
                                    <span>Author: <?= e($row['admin_name'] ?? 'Admin') ?></span>
                                    <span>Created: <?= e(ist($row['created_at'], 'd M Y, H:i')) ?></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <?php if ((int) $row['is_published'] === 0): ?>
                                    <form method="post" data-confirm="Publish this announcement? All targeted users will immediately receive an in-app alert and queued email.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="action" value="publish">
                                        <button type="submit" class="btn-primary btn-sm">
                                            <i data-lucide="send" class="h-3.5 w-3.5"></i> Publish
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" data-confirm="Delete this announcement?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-danger btn-sm" title="Delete">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/announcements.php')]); ?>
        <?php endif; ?>
    </div>

    <!-- Create Form -->
    <div class="card p-5">
        <h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Create Announcement</h3>
        <p class="text-[11px] text-slate-500 mb-4">Compose a new platform broadcast.</p>

        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div>
                <label class="label" for="title">Title</label>
                <input type="text" id="title" name="title" required placeholder="E.g. Scheduled UPI Gateway Maintenance" class="input">
            </div>

            <div>
                <label class="label" for="audience">Audience</label>
                <select id="audience" name="audience" class="input">
                    <option value="all_users">All Registered Users</option>
                    <option value="active_merchants">Active Merchants Only</option>
                </select>
            </div>

            <div>
                <label class="label" for="body">Message Body</label>
                <textarea id="body" name="body" rows="4" required placeholder="Describe the announcement details..." class="input"></textarea>
            </div>

            <button type="submit" class="btn-primary w-full justify-center">
                <i data-lucide="plus" class="h-4 w-4"></i> Create Draft
            </button>
        </form>
    </div>
</div>
