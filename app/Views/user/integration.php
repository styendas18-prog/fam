<?php
$stateLabels = [
    'not_configured' => 'Not Configured',
    'testing' => 'Testing',
    'connected' => 'Connected',
    'failed' => 'Connection Failed',
    'disabled' => 'Disabled',
];
$mailState = $emailAccount ? (string) $emailAccount['connection_status'] : 'not_configured';
if ($emailAccount && (int) $emailAccount['is_enabled'] !== 1) { $mailState = 'disabled'; }
$mailMeta = status_meta($mailState);
$mailConfigured = $emailAccount !== null;
?>
<div class="card max-w-3xl overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between dark:border-white/[0.06]">
        <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-300"><i data-lucide="mail-check" class="h-5 w-5"></i></span>
            <div>
                <h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Automated Payment Verification · Gmail IMAP</h2>
                <p class="mt-1.5 max-w-xl text-[10.5px] leading-5 text-slate-500">Reads FamPay transaction alert emails from a Gmail mailbox and reconciles them with pending orders.</p>
            </div>
        </div>
        <span class="<?= e($mailMeta['class']) ?> shrink-0"><span class="h-1.5 w-1.5 rounded-full <?= e($mailMeta['dot']) ?>"></span> <?= e($stateLabels[$mailState] ?? $mailState) ?></span>
    </div>
    <?php if (!$emailVerificationEnabled): ?>
    <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-[10.5px] leading-5 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/[0.06] dark:text-amber-200">Email verification is disabled by the platform administrator. Existing configuration is preserved but no scans will run.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/user/integration.php')) ?>" class="space-y-4 p-5" data-loading>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="email_save">
        <div>
            <label for="gmail_address" class="label">FamPay-linked Gmail address</label>
            <input id="gmail_address" name="gmail_address" type="email" autocomplete="email" required maxlength="190" class="input" value="<?= e((string) ($emailAccount['gmail_address'] ?? '')) ?>" placeholder="merchant@gmail.com">
        </div>
        <div>
            <label for="upi_id" class="label">FamPay UPI ID</label>
            <input id="upi_id" name="upi_id" type="text" autocomplete="off" required maxlength="190" class="input font-mono text-[11px]" value="<?= e((string) ($emailAccount['upi_id'] ?? '')) ?>" placeholder="yourname@fam or yourname@yesfam">
            <p class="hint !mt-1 !text-[9.5px]">Must end with <span class="font-mono">@fam</span> or <span class="font-mono">@yesfam</span>.</p>
        </div>
        <div>
            <label for="app_password" class="label">Google App Password (16 characters)</label>
            <input id="app_password" name="app_password" type="password" autocomplete="new-password" maxlength="19" class="input font-mono text-[11px]" placeholder="<?= $mailConfigured ? 'Saved •••• ' . e((string) $emailAccount['app_password_last4']) . ' — leave blank to keep' : '16-character app password' ?>">
            <p class="hint !mt-1 !text-[9.5px]">Create one at Google Account → Security → App passwords. Spaces are ignored. Stored encrypted with AES-256-GCM; only the last four characters are shown.</p>
        </div>
        <button class="btn-primary btn-sm w-full sm:w-auto" <?= $emailVerificationEnabled ? '' : 'disabled' ?>><?= $mailConfigured ? 'Update Gmail IMAP account' : 'Connect Gmail IMAP account' ?></button>
    </form>

    <div class="border-t border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <div class="grid gap-2 text-[10px] sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10"><p class="font-bold uppercase tracking-[0.1em] text-slate-400">App password</p><p class="mt-1 font-mono text-[11px] text-slate-700 dark:text-slate-300"><?= $mailConfigured ? '•••• ' . e((string) $emailAccount['app_password_last4']) : 'Not saved' ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10"><p class="font-bold uppercase tracking-[0.1em] text-slate-400">Last test</p><p class="mt-1 text-[10.5px] text-slate-700 dark:text-slate-300"><?= $emailAccount && $emailAccount['last_test_at'] ? e(ist($emailAccount['last_test_at'], 'd M, H:i')) : 'Never' ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10"><p class="font-bold uppercase tracking-[0.1em] text-slate-400">Last scan</p><p class="mt-1 text-[10.5px] text-slate-700 dark:text-slate-300"><?= $emailAccount && $emailAccount['last_checked_at'] ? e(ist($emailAccount['last_checked_at'], 'd M, H:i')) : 'Never' ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10"><p class="font-bold uppercase tracking-[0.1em] text-slate-400">Messages / verified</p><p class="mt-1 text-[10.5px] text-slate-700 dark:text-slate-300"><?= (int) ($emailAccount['total_messages'] ?? 0) ?> / <?= (int) ($emailAccount['total_verified'] ?? 0) ?></p></div>
        </div>
        <?php if ($emailAccount && !empty($emailAccount['last_test_message'])): ?>
        <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-[10px] leading-5 text-slate-600 dark:bg-white/[0.03] dark:text-slate-400"><span class="font-semibold">Last result:</span> <?= e((string) $emailAccount['last_test_message']) ?></div>
        <?php endif; ?>
        <?php if ($emailAccount && !empty($emailAccount['last_error'])): ?>
        <div class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[10px] leading-5 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"><span class="font-semibold">Last error:</span> <?= e((string) $emailAccount['last_error']) ?></div>
        <?php endif; ?>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:max-w-sm">
            <form method="post" action="<?= e(url('/user/integration.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="email_test"><button class="btn-secondary btn-sm w-full justify-center" <?= $mailConfigured ? '' : 'disabled' ?>><i data-lucide="activity" class="h-3.5 w-3.5"></i> Test IMAP</button></form>
            <form method="post" action="<?= e(url('/user/integration.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="email_scan"><button class="btn-secondary btn-sm w-full justify-center" <?= $mailConfigured && (int) $emailAccount['is_enabled'] === 1 ? '' : 'disabled' ?>><i data-lucide="scan-line" class="h-3.5 w-3.5"></i> Scan now</button></form>
        </div>
        <?php if ($mailConfigured): ?>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:max-w-sm">
            <form method="post" action="<?= e(url('/user/integration.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="email_toggle"><input type="hidden" name="is_enabled" value="<?= (int) $emailAccount['is_enabled'] === 1 ? '0' : '1' ?>"><button class="btn-ghost btn-sm w-full justify-center"><?= (int) $emailAccount['is_enabled'] === 1 ? 'Disable' : 'Enable' ?></button></form>
            <form method="post" action="<?= e(url('/user/integration.php')) ?>" data-confirm="Remove this Gmail IMAP account and its message history?" data-confirm-title="Remove Gmail IMAP account"><?= csrf_field() ?><input type="hidden" name="action" value="email_remove"><button class="btn-ghost btn-sm w-full justify-center text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Remove</button></form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($emailRecent): ?>
    <div class="border-t border-slate-100 px-5 py-4 dark:border-white/[0.06]">
        <h3 class="text-[11px] font-bold text-slate-900 dark:text-white">Recent parsed messages</h3>
        <div class="mt-2 overflow-x-auto">
            <table class="table-mf text-[10.5px]">
                <thead><tr><th>Received</th><th>From</th><th>Subject</th><th>Amount</th><th>UTR</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($emailRecent as $message): ?>
                    <tr>
                        <td class="whitespace-nowrap"><?= $message['created_at'] ? e(ist($message['created_at'], 'd M, H:i')) : '—' ?></td>
                        <td class="max-w-[160px] truncate font-mono text-[9.5px]"><?= e((string) ($message['from_address'] ?? '—')) ?></td>
                        <td class="max-w-[220px] truncate"><?= e((string) ($message['subject'] ?? '—')) ?></td>
                        <td class="whitespace-nowrap"><?= $message['parsed_amount'] !== null ? e(money((float) $message['parsed_amount'])) : '—' ?></td>
                        <td class="whitespace-nowrap font-mono text-[9.5px]"><?= e((string) ($message['parsed_utr'] ?? '—')) ?></td>
                        <td><span class="<?= in_array($message['status'], ['verified'], true) ? 'badge-success' : (in_array($message['status'], ['failed', 'duplicate'], true) ? 'badge-danger' : 'badge-neutral') ?>"><?= e((string) $message['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
