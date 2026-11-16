<?php
$pretty = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $json;
};
?>
<div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card"><p class="stat-label">Requests (all time)</p><p class="stat-value"><?= (int) $stats['total'] ?></p><p class="mt-1 text-[10px] text-slate-500">Retained for operational review</p></div>
    <div class="stat-card"><p class="stat-label">Today</p><p class="stat-value"><?= (int) $stats['today'] ?></p><p class="mt-1 text-[10px] text-slate-500">Since 00:00 UTC</p></div>
    <div class="stat-card"><p class="stat-label">Errors (24h)</p><p class="stat-value <?= (int) $stats['errors_24h'] > 0 ? '!text-rose-600 dark:!text-rose-400' : '' ?>"><?= (int) $stats['errors_24h'] ?></p><p class="mt-1 text-[10px] text-slate-500">HTTP 4xx and 5xx</p></div>
    <div class="stat-card"><p class="stat-label">Average latency</p><p class="stat-value"><?= (int) $stats['avg_latency'] ?><span class="text-[14px] text-slate-400"> ms</span></p><p class="mt-1 text-[10px] text-slate-500">Server-side measurement</p></div>
</div>

<?php if ($detail): ?>
<div class="card mb-4 overflow-hidden">
    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
        <div><p class="font-mono text-[11px] font-bold text-slate-900 dark:text-white"><?= e($detail['request_id']) ?></p><p class="text-[10px] text-slate-500"><?= e($detail['method']) ?> <?= e($detail['path']) ?> · <?= e(ist($detail['created_at'])) ?> · <?= (int) $detail['latency_ms'] ?> ms</p></div>
        <div class="flex items-center gap-2">
            <?php $m = (int) $detail['status_code'] >= 400 ? status_meta('failed') : status_meta('success'); ?>
            <span class="<?= e($m['class']) ?>">HTTP <?= (int) $detail['status_code'] ?></span>
            <a href="<?= e(url('/user/api-logs.php')) ?>" class="btn-ghost btn-sm">Close</a>
        </div>
    </div>
    <div class="grid gap-4 p-5 lg:grid-cols-2">
        <div><p class="mb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Request body (redacted)</p><pre class="max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-[10.5px] leading-5 text-slate-300"><?= e($pretty($detail['request_body'])) ?></pre></div>
        <div><p class="mb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Response body (redacted)</p><pre class="max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-[10.5px] leading-5 text-slate-300"><?= e($pretty($detail['response_body'])) ?></pre></div>
    </div>
    <?php if ($detail['error']): ?><div class="border-t border-slate-100 px-5 py-3 text-[10.5px] text-rose-600 dark:border-white/[0.06] dark:text-rose-400"><?= e($detail['error']) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/[0.06]">
        <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Request history</h2><p class="text-[10.5px] text-slate-500"><?= (int) $result['total'] ?> matching requests.</p></div>
        <a href="<?= e(url('/user/documentation.php')) ?>" class="btn-secondary btn-sm self-start"><i data-lucide="book-open" class="h-3.5 w-3.5"></i> API guide</a>
    </div>
    <form method="get" action="<?= e(url('/user/api-logs.php')) ?>" class="grid gap-2 border-b border-slate-100 px-5 py-3 sm:grid-cols-2 lg:grid-cols-6 dark:border-white/[0.06]">
        <input name="q" value="<?= e($filters['q']) ?>" class="input h-9 text-[11px] lg:col-span-2" placeholder="Path, request ID or error">
        <select name="status" class="input h-9 text-[11px]"><option value="">All results</option><option value="success" <?= $filters['status'] === 'success' ? 'selected' : '' ?>>Successful</option><option value="error" <?= $filters['status'] === 'error' ? 'selected' : '' ?>>Errors</option></select>
        <input type="date" name="from" value="<?= e($filters['from']) ?>" class="input h-9 text-[11px]" aria-label="From date">
        <input type="date" name="to" value="<?= e($filters['to']) ?>" class="input h-9 text-[11px]" aria-label="To date">
        <div class="flex gap-2"><button class="btn-primary btn-sm flex-1">Filter</button><a href="<?= e(url('/user/api-logs.php')) ?>" class="btn-ghost btn-sm">Reset</a></div>
    </form>
    <?php if (!$result['rows']): ?>
    <div class="px-5 py-14 text-center"><span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="terminal" class="h-5 w-5"></i></span><p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No API requests yet</p><p class="mt-1 text-[11px] text-slate-500">Requests appear here once your integration calls <span class="font-mono">/api/v1</span>.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table-mf">
            <thead><tr><th>Request</th><th>Endpoint</th><th>Result</th><th>Latency</th><th>Key</th><th>Time</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): $meta = (int) $row['status_code'] >= 400 ? status_meta('failed') : status_meta('success'); ?>
            <tr>
                <td class="font-mono text-[9.5px] text-slate-500"><?= e($row['request_id']) ?></td>
                <td><span class="font-mono text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= e($row['method']) ?></span> <span class="font-mono text-[10px] text-slate-500"><?= e($row['path']) ?></span></td>
                <td><span class="<?= e($meta['class']) ?>"><?= (int) $row['status_code'] ?></span></td>
                <td class="whitespace-nowrap text-[10.5px] text-slate-500"><?= (int) $row['latency_ms'] ?> ms</td>
                <td class="max-w-[150px] truncate text-[10px] text-slate-500"><?= e($row['key_name'] ?: '—') ?></td>
                <td class="whitespace-nowrap text-[10px] text-slate-500"><?= e(ist($row['created_at'], 'd M, H:i:s')) ?></td>
                <td><a href="<?= e(url('/user/api-logs.php') . query_string(['id' => $row['id']])) ?>" class="btn-ghost btn-sm" aria-label="View request"><i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/user/api-logs.php')]); ?>
    <?php endif; ?>
</div>
