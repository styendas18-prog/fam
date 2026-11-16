<?php
$total = 0.0;
foreach ($result['rows'] as $row) {
    $total += (float) $row['amount'];
}
?>
<div class="mb-4 grid gap-4 sm:grid-cols-3">
    <div class="stat-card"><p class="stat-label">Invoices issued</p><p class="stat-value"><?= (int) $result['total'] ?></p><p class="mt-1 text-[10px] text-slate-500">One per verified order</p></div>
    <div class="stat-card"><p class="stat-label">Value on this page</p><p class="stat-value"><?= e(money($total)) ?></p><p class="mt-1 text-[10px] text-slate-500"><?= count($result['rows']) ?> invoice<?= count($result['rows']) === 1 ? '' : 's' ?></p></div>
    <div class="stat-card"><p class="stat-label">Document format</p><p class="stat-value !text-[20px]">PDF</p><p class="mt-1 text-[10px] text-slate-500">Generated server-side, download only for the order token holder</p></div>
</div>

<div class="card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Receipts</h2><p class="text-[10.5px] text-slate-500">Invoice numbers are sequential and stored in the database.</p></div>
        <a href="<?= e(url('/user/transactions.php')) ?>" class="btn-secondary btn-sm"><i data-lucide="arrow-left-right" class="h-3.5 w-3.5"></i> Transactions</a>
    </div>
    <?php if (!$result['rows']): ?>
    <div class="px-5 py-16 text-center">
        <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="file-text" class="h-5 w-5"></i></span>
        <p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No invoices yet</p>
        <p class="mx-auto mt-1 max-w-sm text-[11px] leading-5 text-slate-500">A receipt is issued automatically when the provider confirms a payment and the UTR is verified.</p>
        <a href="<?= e(url('/user/create-payment.php')) ?>" class="btn-primary btn-sm mt-4">Create a payment</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table-mf">
            <thead><tr><th>Invoice</th><th>Order</th><th>Customer</th><th>Amount</th><th>Issued</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): ?>
            <tr>
                <td class="font-mono text-[10.5px] font-semibold text-slate-800 dark:text-slate-200"><?= e($row['invoice_number']) ?></td>
                <td class="font-mono text-[10px] text-slate-500"><?= e($row['public_id']) ?></td>
                <td><p class="max-w-[170px] truncate text-[11px] font-medium text-slate-700 dark:text-slate-200"><?= e($row['customer_name'] ?: '—') ?></p><p class="max-w-[170px] truncate text-[9.5px] text-slate-400"><?= e($row['customer_email'] ?: '') ?></p></td>
                <td class="whitespace-nowrap font-semibold"><?= e(money((float) $row['amount'])) ?></td>
                <td class="whitespace-nowrap text-[10.5px] text-slate-500"><?= e(ist($row['issued_at'], 'd M Y, H:i')) ?></td>
                <td><a href="<?= e(url('/receipt.php?token=' . urlencode((string) $row['checkout_token']))) ?>" class="btn-secondary btn-sm"><i data-lucide="download" class="h-3.5 w-3.5"></i> PDF</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/user/invoices.php')]); ?>
    <?php endif; ?>
</div>
