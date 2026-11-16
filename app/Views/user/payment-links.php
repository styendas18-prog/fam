<?php
$publicBase = url('/pay/');
?>
<div class="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)] xl:items-start">
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Create a link</h2><p class="mt-0.5 text-[10.5px] text-slate-500">Fixed amount or customer-entered within limits.</p></div>
        <form method="post" action="<?= e(url('/user/payment-links.php')) ?>" class="space-y-4 p-5" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div><label for="title" class="label">Title</label><input id="title" name="title" required maxlength="160" value="<?= old('title') ?>" class="input" placeholder="Invoice #2041"></div>
            <div><label for="description" class="label">Description <span class="font-normal text-slate-400">(optional)</span></label><textarea id="description" name="description" rows="2" maxlength="1000" class="input" placeholder="What is this payment for?"><?= old('description') ?></textarea></div>
            <div>
                <label for="amount" class="label">Fixed amount (INR) <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="amount" name="amount" type="number" step="0.01" min="1" max="100000" value="<?= old('amount') ?>" class="input" placeholder="Leave blank for open amount">
                <p class="hint">Leave blank to let the customer enter an amount within the limits below.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="min_amount" class="label">Min</label><input id="min_amount" name="min_amount" type="number" step="0.01" min="1" value="<?= old('min_amount', '1') ?>" class="input"></div>
                <div><label for="max_amount" class="label">Max</label><input id="max_amount" name="max_amount" type="number" step="0.01" max="100000" value="<?= old('max_amount', '100000') ?>" class="input"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="expiry_days" class="label">Expires in</label><input id="expiry_days" name="expiry_days" type="number" min="1" max="365" value="<?= old('expiry_days', '30') ?>" class="input"></div>
                <div><label for="customer_reference" class="label">Reference</label><input id="customer_reference" name="customer_reference" maxlength="120" value="<?= old('customer_reference') ?>" class="input" placeholder="INV-2041"></div>
            </div>
            <div><label for="success_redirect_url" class="label">Success redirect <span class="font-normal text-slate-400">(optional)</span></label><input id="success_redirect_url" name="success_redirect_url" type="url" maxlength="500" value="<?= old('success_redirect_url') ?>" class="input font-mono text-[10.5px]" placeholder="https://your-site.com/thanks"></div>
            <button type="submit" class="btn-primary w-full">Create payment link <i data-lucide="link-2" class="h-4 w-4"></i></button>
        </form>
    </div>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
            <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Your links</h2><p class="text-[10.5px] text-slate-500"><?= (int) $result['total'] ?> link<?= $result['total'] === 1 ? '' : 's' ?>.</p></div>
            <form method="get" class="flex gap-2"><input name="q" value="<?= e($filters['q']) ?>" class="input h-9 w-40 text-[11px]" placeholder="Search"><select name="status" class="input h-9 w-28 text-[11px]"><option value="">All</option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select><button class="btn-secondary btn-sm">Filter</button></form>
        </div>
        <?php if (!$result['rows']): ?>
        <div class="px-5 py-16 text-center"><span class="grid mx-auto h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="link-2" class="h-5 w-5"></i></span><p class="mt-4 text-[13px] font-semibold text-slate-700 dark:text-slate-200">No payment links</p><p class="mt-1 text-[11px] text-slate-500">Create one to start collecting payments without an integration.</p></div>
        <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
            <?php foreach ($result['rows'] as $link): $url = $publicBase . $link['slug']; ?>
            <div class="p-5 <?= (int) $highlight === (int) $link['id'] ? 'bg-brand-50/50 dark:bg-brand-400/[0.04]' : '' ?>">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-[13px] font-bold text-slate-900 dark:text-white"><?= e($link['title']) ?></h3>
                            <span class="<?= $link['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><span class="h-1.5 w-1.5 rounded-full <?= $link['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span> <?= e(ucfirst($link['status'])) ?></span>
                        </div>
                        <p class="mt-1 text-[10.5px] text-slate-500"><?= $link['amount'] !== null ? e(money((float) $link['amount'])) . ' fixed' : e(money((float) $link['min_amount'])) . ' – ' . e(money((float) $link['max_amount'])) . ' open' ?> · <?= (int) $link['order_count'] ?> orders · <?= e(money((float) $link['success_volume'])) ?> verified</p>
                        <?php if ($link['expires_at']): ?><p class="mt-0.5 text-[9.5px] <?= strtotime($link['expires_at'] . ' UTC') < time() ? 'text-rose-500' : 'text-slate-400' ?>">Expires <?= e(ist($link['expires_at'], 'd M Y')) ?></p><?php endif; ?>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-1.5">
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="btn-secondary btn-sm"><i data-lucide="external-link" class="h-3 w-3"></i> Open</a>
                        <button type="button" class="btn-secondary btn-sm" data-copy="<?= e($url) ?>"><i data-lucide="copy" class="h-3 w-3"></i> Copy</button>
                        <form method="post" action="<?= e(url('/user/payment-links.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $link['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn-ghost btn-sm"><?= $link['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form>
                        <form method="post" action="<?= e(url('/user/payment-links.php')) ?>" data-confirm="Delete this link? The public URL will stop working. Existing orders are retained." data-confirm-title="Delete payment link"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $link['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button></form>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-white/10 dark:bg-white/[0.03]">
                    <code class="min-w-0 flex-1 truncate font-mono text-[10px] text-slate-600 dark:text-slate-300"><?= e($url) ?></code>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php \App\Core\View::partial('partials.pagination', ['pagination' => $result, 'paginationBase' => url('/user/payment-links.php')]); ?>
        <?php endif; ?>
    </div>
</div>
