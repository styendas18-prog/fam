<?php
$title = 'Sign in';
$authHeading = 'Sign in to MAXXfam';
$authCopy = 'Use the email and password for your merchant account.';
?>
<form method="post" action="<?= e(url('/login.php')) ?>" class="space-y-4" data-loading>
    <?= csrf_field() ?>
    <div>
        <label for="email" class="label">Email address</label>
        <div class="relative">
            <i data-lucide="mail" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= old('email') ?>" class="input pl-10" placeholder="you@business.com">
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between">
            <label for="password" class="label">Password</label>
            <a href="<?= e(url('/forgot-password.php')) ?>" class="mb-1.5 text-[11px] font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">Forgot password?</a>
        </div>
        <div class="relative">
            <i data-lucide="lock" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="input pl-10 pr-11" placeholder="Your password">
            <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" data-toggle-password="password" aria-label="Show password"><i data-lucide="eye" class="h-4 w-4"></i></button>
        </div>
    </div>
    <label class="flex items-start gap-2.5 text-[11.5px] text-slate-500">
        <input type="checkbox" name="remember" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        <span>Keep me signed in on this device</span>
    </label>
    <button type="submit" class="btn-primary w-full" data-loading-text="Signing in…">Sign in <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
</form>

<div class="mt-6 flex items-center gap-3"><span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span><span class="text-[10px] uppercase tracking-[0.12em] text-slate-400">New here?</span><span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span></div>
<a href="<?= e(url('/register.php')) ?>" class="btn-secondary mt-4 w-full">Create a merchant account</a>

<div class="mt-7 rounded-xl border border-slate-200 bg-slate-50 p-4 text-[10.5px] leading-5 text-slate-500 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-400">
    <p class="flex items-start gap-2"><i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500"></i> Sessions rotate after sign-in. Repeated failures trigger temporary lockout. Administrators use a separate sign-in and may enable two-factor authentication.</p>
</div>
<a href="<?= e(url('/admin/login.php')) ?>" class="mt-4 block text-center text-[10.5px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Administrator sign-in</a>

