<?php
$title = $setup ? 'Set up two-factor authentication' : 'Two-factor verification';
$authHeading = $setup ? 'Protect your admin account' : 'Enter your authenticator code';
$authCopy = $setup ? 'Scan the QR code with an authenticator app, then enter the current six-digit code to finish setup.' : 'Open your authenticator app and enter the current six-digit code.';
?>
<?php if ($setup): ?>
<div class="mb-5 flex justify-center rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10">
    <div data-qr="<?= e($provisioningUri) ?>" data-qr-size="180" class="h-[180px] w-[180px]"></div>
</div>
<div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">Manual setup secret</p>
    <div class="mt-2 flex items-center gap-2">
        <code class="min-w-0 flex-1 break-all rounded-lg bg-white px-3 py-2 font-mono text-[11px] text-slate-800 dark:bg-black/30 dark:text-slate-200"><?= e($secret) ?></code>
        <button type="button" class="btn-secondary btn-sm shrink-0" data-copy="<?= e($secret) ?>">Copy</button>
    </div>
</div>
<?php endif; ?>
<form method="post" action="<?= e(url('/admin/two-factor.php' . ($setup ? '?setup=1' : ''))) ?>" class="space-y-4" data-loading>
    <?= csrf_field() ?>
    <div>
        <label for="code" class="label">Six-digit code</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="input text-center font-mono text-[22px] tracking-[0.45em]" placeholder="000000">
    </div>
    <button type="submit" class="btn-primary w-full"><?= $setup ? 'Enable 2FA and sign in' : 'Verify and sign in' ?> <i data-lucide="log-in" class="h-4 w-4"></i></button>
</form>
<div class="mt-5 flex items-center justify-between text-[11px]">
    <a href="<?= e(url('/admin/login.php')) ?>" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Start over</a>
    <span class="text-slate-400">Codes rotate every 30 seconds</span>
</div>
