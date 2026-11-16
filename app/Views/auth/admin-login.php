<?php
$title = 'Administrator sign-in';
$authHeading = 'Administrator access';
$authCopy = 'Sign in with your administrator email and password. Two-factor authentication can be enabled optionally after sign-in.';
?>
<form method="post" action="<?= e(url('/admin/login.php')) ?>" class="space-y-4" data-loading>
    <?= csrf_field() ?>
    <div>
        <label for="email" class="label">Administrator email</label>
        <input id="email" name="email" type="email" autocomplete="username" required value="<?= old('email') ?>" class="input" placeholder="admin@example.com">
    </div>
    <div>
        <label for="password" class="label">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required class="input" placeholder="Administrator password">
    </div>
    <button type="submit" class="btn-primary w-full">Sign in <i data-lucide="log-in" class="h-4 w-4"></i></button>
</form>
<div class="mt-6 rounded-xl border border-brand-500/20 bg-brand-500/[0.06] p-4 text-[10.5px] leading-5 text-slate-500 dark:text-slate-400">
    <p class="flex items-start gap-2"><i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-500"></i> TOTP two-factor authentication is optional. Enable it from the admin menu after signing in; recovery is CLI-only.</p>
</div>
<p class="mt-5 text-center text-[11.5px] text-slate-500"><a href="<?= e(url('/login.php')) ?>" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Merchant sign-in</a></p>
