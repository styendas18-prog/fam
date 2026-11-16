<?php
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0, 'per_page' => 20];
$page = (int) ($pagination['page'] ?? 1);
$pages = (int) ($pagination['pages'] ?? 1);
$total = (int) ($pagination['total'] ?? 0);
$perPage = (int) ($pagination['per_page'] ?? 20);
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = min($total, $page * $perPage);
$base = $paginationBase ?? '';
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3.5 text-[11px] text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06] dark:text-slate-400">
    <p>Showing <span class="font-semibold text-slate-700 dark:text-slate-200"><?= $from ?>–<?= $to ?></span> of <span class="font-semibold text-slate-700 dark:text-slate-200"><?= $total ?></span></p>
    <?php if ($pages > 1): ?>
    <nav class="flex items-center gap-1" aria-label="Pagination">
        <a href="<?= e($base . query_string(['page' => max(1, $page - 1)])) ?>" class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 hover:bg-slate-50 <?= $page <= 1 ? 'pointer-events-none opacity-40' : '' ?> dark:border-white/10 dark:hover:bg-white/5" aria-label="Previous page"><i data-lucide="chevron-left" class="h-3.5 w-3.5"></i></a>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= e($base . query_string(['page' => $i])) ?>" class="grid h-7 min-w-7 place-items-center rounded-md border px-1.5 <?= $i === $page ? 'border-brand-500 bg-brand-600 font-semibold text-white' : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= e($base . query_string(['page' => min($pages, $page + 1)])) ?>" class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 hover:bg-slate-50 <?= $page >= $pages ? 'pointer-events-none opacity-40' : '' ?> dark:border-white/10 dark:hover:bg-white/5" aria-label="Next page"><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></a>
    </nav>
    <?php endif; ?>
</div>
