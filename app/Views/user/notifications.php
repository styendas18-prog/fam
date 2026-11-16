<?php
$icons = [
    'payment_success' => ['check-circle-2', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300'],
    'payment_failed' => ['alert-circle', 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300'],
    'payment_expired' => ['clock-3', 'bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300'],
    'announcement' => ['megaphone', 'bg-brand-50 text-brand-600 dark:bg-brand-400/10 dark:text-brand-300'],
];
?>
<div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-ink-800">
        <a href="<?= e(url('/user/notifications.php')) ?>" class="rounded-lg px-3 py-1.5 text-[11px] font-semibold <?= $filter === 'all' ? 'bg-brand-600 text-white' : 'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5' ?>">All</a>
        <a href="<?= e(url('/user/notifications.php?filter=unread')) ?>" class="rounded-lg px-3 py-1.5 text-[11px] font-semibold <?= $filter === 'unread' ? 'bg-brand-600 text-white' : 'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5' ?>">Unread <?= $unread > 0 ? '(' . (int) $unread . ')' : '' ?></a>
    </div>
    <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(url('/user/notifications.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn-secondary btn-sm"><i data-lucide="check-check" class="h-3.5 w-3.5"></i> Mark all read</button></form>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (!$notifications): ?>
    <div class="px-5 py-16 text-center"><span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="bell-off" class="h-5 w-5"></i></span><p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">Nothing here</p><p class="mt-1 text-[11px] text-slate-500"><?= $filter === 'unread' ? 'You have read every notification.' : 'Payment and account events will appear here.' ?></p></div>
    <?php else: ?>
    <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
        <?php foreach ($notifications as $note): [$icon, $tone] = $icons[$note['type']] ?? ['bell', 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400']; ?>
        <div class="flex items-start gap-3 px-5 py-4 <?= $note['read_at'] ? '' : 'bg-brand-50/40 dark:bg-brand-400/[0.03]' ?>">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl <?= e($tone) ?>"><i data-lucide="<?= e($icon) ?>" class="h-4 w-4"></i></span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[12px] font-semibold text-slate-800 dark:text-slate-100"><?= e($note['title']) ?></p>
                    <?php if (!$note['read_at']): ?><span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span><?php endif; ?>
                    <span class="text-[9.5px] text-slate-400"><?= e(time_ago($note['created_at'])) ?></span>
                </div>
                <p class="mt-1 text-[10.5px] leading-5 text-slate-500 dark:text-slate-400"><?= e($note['message']) ?></p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <?php if ($note['link']): ?><a href="<?= e(url($note['link'])) ?>" class="text-[10px] font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">Open</a><?php endif; ?>
                    <?php if (!$note['read_at']): ?><form method="post" action="<?= e(url('/user/notifications.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= (int) $note['id'] ?>"><button class="text-[10px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Mark read</button></form><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
