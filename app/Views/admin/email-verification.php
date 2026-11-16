<?php
$accounts = $accounts ?? [];
$policy = $policy ?? [];
$stats = $stats ?? [];
$recent = $recent ?? [];
$messageCount = $messageCount ?? 0;
$verifiedCount = $verifiedCount ?? 0;
$canManage = $canManage ?? false;

$stateLabel = static fn (string $state): string => match ($state) {
    'connected' => 'Connected',
    'testing' => 'Testing',
    'failed' => 'Connection Failed',
    'disabled' => 'Disabled',
    default => 'Not Configured',
};
$stateBadge = static fn (string $state): string => match ($state) {
    'connected' => 'badge-success',
    'testing' => 'badge-warning',
    'failed' => 'badge-danger',
    'disabled' => 'badge-neutral',
    default => 'badge-neutral',
};
$messageBadge = static fn (string $state): string => match ($state) {
    'verified' => 'badge-success',
    'failed', 'duplicate' => 'badge-danger',
    'unmatched', 'ambiguous', 'unparsed' => 'badge-warning',
    'ignored' => 'badge-neutral',
    default => 'badge-info',
};
?>
<div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-500/20 dark:bg-sky-500/10">
    <div class="flex items-start gap-3">
        <i data-lucide="shield-alert" class="mt-0.5 h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400"></i>
        <div class="text-[12px] leading-relaxed text-sky-900 dark:text-sky-200">
            <span class="font-semibold">Email verification is a lower-assurance source.</span>
            A payment becomes verified only when a trusted FamPay sender, the configured UPI ID, a 12-digit UTR, and the exact payable amount all match one pending order. Duplicate UTRs and references remain blocked. Google App Passwords are encrypted with AES-256-GCM and never displayed in full.
        </div>
    </div>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-4">
        <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Accounts</p>
        <p class="mt-1 text-[24px] font-bold text-slate-900 dark:text-white"><?= count($accounts) ?></p>
        <p class="text-[10px] text-slate-500">Merchant Gmail IMAP connections</p>
    </div>
    <div class="card p-4">
        <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Messages parsed</p>
        <p class="mt-1 text-[24px] font-bold text-slate-900 dark:text-white"><?= (int) $messageCount ?></p>
        <p class="text-[10px] text-slate-500">All-time parsed alerts</p>
    </div>
    <div class="card p-4">
        <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Verified</p>
        <p class="mt-1 text-[24px] font-bold text-emerald-600 dark:text-emerald-400"><?= (int) $verifiedCount ?></p>
        <p class="text-[10px] text-slate-500">Reconciled with a pending order</p>
    </div>
    <div class="card p-4">
        <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400">Last 24 hours</p>
        <p class="mt-1 text-[24px] font-bold text-slate-900 dark:text-white"><?= (int) array_sum($stats) ?></p>
        <p class="text-[10px] text-slate-500"><?= (int) ($stats['verified'] ?? 0) ?> verified · <?= (int) ($stats['duplicate'] ?? 0) ?> duplicate · <?= (int) ($stats['failed'] ?? 0) ?> failed</p>
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] xl:items-start">
    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Merchant Gmail IMAP accounts</h2>
                <p class="text-[11px] text-slate-500">Credentials are owned and entered by each merchant. Administrators can enable, test, scan, or remove an account.</p>
            </div>
            <?php if (!$accounts): ?>
            <div class="px-5 py-10 text-center text-[12px] text-slate-500">No merchant has configured Gmail IMAP verification yet.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table-mf">
                    <thead>
                        <tr>
                            <th>Merchant / Gmail</th>
                            <th>FamPay UPI</th>
                            <th>Status</th>
                            <th>Last test / scan</th>
                            <th>Messages</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $state = (string) $account['connection_status'];
                        if ((int) $account['is_enabled'] !== 1) { $state = 'disabled'; }
                        $id = (int) $account['id'];
                        ?>
                        <tr>
                            <td>
                                <p class="text-[11.5px] font-semibold text-slate-800 dark:text-slate-200"><?= e((string) ($account['business_name'] ?? '—')) ?></p>
                                <p class="font-mono text-[10px] text-slate-500"><?= e((string) $account['gmail_address']) ?></p>
                                <p class="text-[9.5px] text-slate-400"><?= e((string) ($account['merchant_code'] ?? '')) ?><?= !empty($account['owner_email']) ? ' · ' . e(mask_email((string) $account['owner_email'])) : '' ?></p>
                            </td>
                            <td class="whitespace-nowrap font-mono text-[10.5px]"><?= e((string) $account['upi_id']) ?></td>
                            <td>
                                <span class="<?= $stateBadge($state) ?>"><?= e($stateLabel($state)) ?></span>
                                <?php if (!empty($account['last_error'])): ?><p class="mt-1 max-w-[180px] text-[9.5px] leading-4 text-rose-600 dark:text-rose-400"><?= e(mb_substr((string) $account['last_error'], 0, 120)) ?></p><?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-[10px] text-slate-500">
                                <p>Test: <?= $account['last_test_at'] ? e(ist((string) $account['last_test_at'], 'd M, H:i')) : 'Never' ?></p>
                                <p>Scan: <?= $account['last_checked_at'] ? e(ist((string) $account['last_checked_at'], 'd M, H:i')) : 'Never' ?></p>
                            </td>
                            <td class="whitespace-nowrap text-[10.5px]">
                                <?= (int) $account['total_messages'] ?> / <span class="text-emerald-600 dark:text-emerald-400"><?= (int) $account['total_verified'] ?></span>
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <?php if ($canManage): ?>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test"><input type="hidden" name="account_id" value="<?= $id ?>"><button class="btn-ghost btn-sm" title="Test IMAP login">Test</button></form>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="scan"><input type="hidden" name="account_id" value="<?= $id ?>"><button class="btn-ghost btn-sm" title="Scan unseen mail now">Scan</button></form>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="account_id" value="<?= $id ?>"><input type="hidden" name="is_enabled" value="<?= (int) $account['is_enabled'] === 1 ? '0' : '1' ?>"><button class="btn-ghost btn-sm"><?= (int) $account['is_enabled'] === 1 ? 'Disable' : 'Enable' ?></button></form>
                                    <form method="post" data-confirm="Remove this merchant's Gmail IMAP account and parsed message history?" data-confirm-title="Remove email verification account"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="account_id" value="<?= $id ?>"><button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Remove</button></form>
                                    <?php else: ?>
                                    <span class="text-[9.5px] text-slate-400">View only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Recent parsed messages</h2>
                <p class="text-[11px] text-slate-500">Every scanned email is recorded with the parsed amount, UTR, matched order and outcome.</p>
            </div>
            <?php if (!$recent): ?>
            <div class="px-5 py-10 text-center text-[12px] text-slate-500">No emails have been parsed yet.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table-mf">
                    <thead><tr><th>Received</th><th>Merchant / From</th><th>Subject</th><th>Amount</th><th>UTR</th><th>Status</th><th>Note</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $message): ?>
                        <tr>
                            <td class="whitespace-nowrap text-[10px]"><?= $message['created_at'] ? e(ist((string) $message['created_at'], 'd M, H:i')) : '—' ?></td>
                            <td class="max-w-[180px]">
                                <p class="truncate text-[10.5px] font-semibold text-slate-700 dark:text-slate-300"><?= e((string) ($message['business_name'] ?? '—')) ?></p>
                                <p class="truncate font-mono text-[9.5px] text-slate-400"><?= e((string) ($message['from_address'] ?? '—')) ?></p>
                            </td>
                            <td class="max-w-[220px] truncate text-[10.5px]"><?= e((string) ($message['subject'] ?? '—')) ?></td>
                            <td class="whitespace-nowrap text-[10.5px]"><?= $message['parsed_amount'] !== null ? e(money((float) $message['parsed_amount'])) : '—' ?></td>
                            <td class="whitespace-nowrap font-mono text-[9.5px]"><?= e((string) ($message['parsed_utr'] ?? '—')) ?></td>
                            <td><span class="<?= $messageBadge((string) $message['status']) ?>"><?= e((string) $message['status']) ?></span></td>
                            <td class="max-w-[240px] text-[9.5px] leading-4 text-slate-500"><?= e((string) ($message['note'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                <div class="flex items-center justify-between">
                    <h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Platform policy</h2>
                    <span class="<?= !empty($policy['enabled']) ? 'badge-success' : 'badge-neutral' ?>"><?= !empty($policy['enabled']) ? 'Enabled' : 'Disabled' ?></span>
                </div>
            </div>
            <form method="post" class="space-y-3 p-5">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="policy">
                <label class="flex items-center gap-2 text-[11px] text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="enabled" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= !empty($policy['enabled']) ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                    Enable email verification platform-wide
                </label>
                <label class="flex items-start gap-2 text-[11px] text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="require_sender_match" value="1" class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= !empty($policy['require_sender_match']) ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                    <span>Require a trusted sender domain or address</span>
                </label>
                <label class="flex items-start gap-2 text-[11px] text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="require_upi_match" value="1" class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand-600" <?= !empty($policy['require_upi_match']) ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                    <span>Require the configured FamPay UPI ID in the message body</span>
                </label>
                <div>
                    <label for="default_senders" class="label">Default trusted senders</label>
                    <input id="default_senders" name="default_senders" type="text" maxlength="500" class="input font-mono text-[10.5px]" value="<?= e((string) ($policy['default_senders'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <p class="hint !mt-1 !text-[9px]">Comma-separated domains or full addresses, e.g. famapp.in,fampay.in.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="lookback_hours" class="label">Order lookback (hours)</label>
                        <input id="lookback_hours" name="lookback_hours" type="number" min="1" max="168" class="input font-mono text-[11px]" value="<?= (int) ($policy['lookback_hours'] ?? 24) ?>" <?= $canManage ? '' : 'disabled' ?>>
                    </div>
                    <div>
                        <label for="max_per_run" class="label">Max emails per scan</label>
                        <input id="max_per_run" name="max_per_run" type="number" min="1" max="100" class="input font-mono text-[11px]" value="<?= (int) ($policy['max_per_run'] ?? 20) ?>" <?= $canManage ? '' : 'disabled' ?>>
                    </div>
                </div>
                <?php if ($canManage): ?>
                <button class="btn-primary btn-sm w-full">Save platform policy</button>
                <?php else: ?>
                <p class="text-[9.5px] text-slate-400">Your role can view this page but not change policy.</p>
                <?php endif; ?>
            </form>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-400/20 dark:bg-amber-400/[0.06]">
            <p class="flex items-center gap-2 text-[11px] font-semibold text-amber-800 dark:text-amber-200"><i data-lucide="alert-triangle" class="h-4 w-4"></i> Operational limits</p>
            <ul class="mt-2 list-disc space-y-1.5 pl-4 text-[10px] leading-5 text-amber-700/80 dark:text-amber-200/70">
                <li>Gmail requires a 16-character App Password; normal account passwords cannot be used.</li>
                <li>The server must trust a CA bundle for outbound TLS. Set <span class="font-mono">IMAP_CA_BUNDLE</span> if certificate verification fails.</li>
                <li>Only unseen messages are scanned. A message that cannot be parsed is recorded and marked seen.</li>
                <li>Email verification cannot replace provider API confirmation, and refunds remain manual.</li>
            </ul>
        </div>
    </div>
</div>
