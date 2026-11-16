<?php
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$activeTicket = $activeTicket ?? null;
$messages = $messages ?? [];
$filters = $filters ?? [];
?>
<div class="grid gap-6 lg:grid-cols-[1fr_420px] lg:items-start">
    <div class="space-y-6">
        <div class="card">
            <form method="get" class="flex flex-wrap gap-3 p-4 sm:items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="label" for="q">Search Subject / Email / Number</label>
                    <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search..." class="input">
                </div>
                <div class="w-40">
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="input">
                        <option value="">All States</option>
                        <option value="open" <?= ($filters['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending (Answered)</option>
                        <option value="resolved" <?= ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">
                    <i data-lucide="filter" class="h-4 w-4"></i> Filter
                </button>
                <?php if (!empty($filters['q']) || !empty($filters['status'])): ?>
                    <a href="<?= e(url('/admin/support.php')) ?>" class="btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">All Tickets (<?= (int) $result['total'] ?>)</h2>
            </div>

            <?php if (empty($result['rows'])): ?>
                <div class="px-5 py-16 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
                        <i data-lucide="life-buoy" class="h-6 w-6"></i>
                    </span>
                    <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-300">No tickets found</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table-mf">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Subject / Category</th>
                                <th>Sender</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['rows'] as $row): ?>
                                <tr class="<?= ($activeTicket['id'] ?? 0) === (int) $row['id'] ? 'bg-brand-50/50 dark:bg-brand-500/10' : '' ?>">
                                    <td class="font-mono text-[11px] font-bold text-slate-900 dark:text-white">
                                        <?= e($row['ticket_number']) ?>
                                    </td>
                                    <td>
                                        <p class="max-w-[200px] truncate text-[11.5px] font-semibold text-slate-900 dark:text-white"><?= e($row['subject']) ?></p>
                                        <span class="text-[10px] text-slate-400 uppercase"><?= e($row['category']) ?></span>
                                    </td>
                                    <td>
                                        <p class="text-[11px] font-medium text-slate-700 dark:text-slate-300"><?= e($row['user_name'] ?? $row['guest_name'] ?? 'Guest') ?></p>
                                        <p class="text-[9.5px] text-slate-400"><?= e($row['user_email'] ?? $row['guest_email'] ?? '') ?></p>
                                    </td>
                                    <td>
                                        <?php
                                        $badge = match ($row['status']) {
                                            'open' => 'badge-danger',
                                            'pending' => 'badge-warning',
                                            'resolved' => 'badge-success',
                                            'closed' => 'badge-neutral',
                                            default => 'badge-neutral',
                                        };
                                        ?>
                                        <span class="<?= $badge ?>"><?= ucfirst($row['status']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-[11px] font-semibold <?= $row['priority'] === 'urgent' ? 'text-rose-500' : 'text-slate-500' ?>">
                                            <?= ucfirst($row['priority']) ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-[10.5px] text-slate-500">
                                        <?= e(ist($row['updated_at'], 'd M, H:i')) ?>
                                    </td>
                                    <td>
                                        <a href="<?= e(url('/admin/support.php?id=' . (int) $row['id'])) ?>" class="btn-secondary btn-sm">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/admin/support.php')]); ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Ticket Sidebar / Message Thread -->
    <div>
        <?php if ($activeTicket): ?>
            <div class="card overflow-hidden sticky top-6">
                <div class="border-b border-slate-100 bg-slate-50/50 p-5 dark:border-white/[0.06] dark:bg-white/[0.02]">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[11px] font-bold text-slate-500"><?= e($activeTicket['ticket_number']) ?></span>
                        <div class="flex items-center gap-2">
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $activeTicket['id'] ?>">
                                <input type="hidden" name="action" value="status">
                                <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold dark:border-white/10 dark:bg-ink-900">
                                    <option value="open" <?= $activeTicket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="pending" <?= $activeTicket['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="resolved" <?= $activeTicket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="closed" <?= $activeTicket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <h3 class="mt-2 text-[15px] font-bold text-slate-900 dark:text-white"><?= e($activeTicket['subject']) ?></h3>
                    <p class="mt-1 text-[11px] text-slate-500">From: <?= e($activeTicket['user_name'] ?? $activeTicket['guest_name'] ?? 'User') ?> (<?= e($activeTicket['user_email'] ?? $activeTicket['guest_email'] ?? '') ?>)</p>
                </div>

                <!-- Messages Thread -->
                <div class="max-h-[380px] overflow-y-auto space-y-4 p-5">
                    <?php foreach ($messages as $msg): ?>
                        <?php $isAdmin = ($msg['sender_type'] ?? '') === 'admin'; ?>
                        <div class="rounded-xl p-3 text-[12px] leading-relaxed <?= $isAdmin ? 'bg-brand-500/10 border border-brand-500/20 text-slate-800 dark:text-slate-200' : 'bg-slate-100 border border-slate-200/60 text-slate-800 dark:bg-white/5 dark:border-white/5 dark:text-slate-200' ?>">
                            <div class="flex items-center justify-between text-[10px] text-slate-400 mb-1">
                                <span class="font-bold <?= $isAdmin ? 'text-brand-600 dark:text-brand-400' : 'text-slate-600 dark:text-slate-300' ?>">
                                    <?= $isAdmin ? ('MAXXfam Support (' . e($msg['admin_name'] ?? 'Admin') . ')') : e($msg['user_name'] ?? 'Customer') ?>
                                </span>
                                <span><?= e(ist($msg['created_at'], 'd M, H:i')) ?></span>
                            </div>
                            <p class="whitespace-pre-wrap"><?= e($msg['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Form -->
                <form method="post" class="border-t border-slate-100 p-4 dark:border-white/[0.06]">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $activeTicket['id'] ?>">
                    <input type="hidden" name="action" value="reply">
                    <textarea name="message" rows="3" required placeholder="Type an official admin reply..." class="input text-[12px]"></textarea>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="btn-primary btn-sm">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-white/10">
                <i data-lucide="message-square" class="mx-auto h-8 w-8 text-slate-400"></i>
                <p class="mt-3 text-[13px] font-semibold text-slate-700 dark:text-slate-300">Select a Ticket</p>
                <p class="mt-1 text-[11px] text-slate-500">Click Open on any ticket from the list to view its message thread and respond.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
