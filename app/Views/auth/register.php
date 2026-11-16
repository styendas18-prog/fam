<?php
$title = 'Create account';
$authHeading = 'Create your merchant account';
$authCopy = 'Start with honest payments. You can connect a provider after creating your account.';
?>
<form method="post" action="<?= e(url('/register.php')) ?>" class="space-y-4" data-loading>
    <?= csrf_field() ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" name="name" type="text" autocomplete="name" required minlength="2" maxlength="120" value="<?= old('name') ?>" class="input" placeholder="Aarav Mehta">
        </div>
        <div>
            <label for="business_name" class="label">Business name</label>
            <input id="business_name" name="business_name" type="text" autocomplete="organization" required minlength="2" maxlength="160" value="<?= old('business_name') ?>" class="input" placeholder="Mehta Studio">
        </div>
    </div>
    <div>
        <label for="email" class="label">Work email</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= old('email') ?>" class="input" placeholder="you@business.com">
    </div>
    <div>
        <label for="password" class="label">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10" class="input" placeholder="At least 10 characters">
        <p class="hint">Minimum 10 characters with upper case, lower case and a number.</p>
    </div>
    <div>
        <label for="password_confirmation" class="label">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="input" placeholder="Repeat password">
    </div>
    <label class="flex items-start gap-2.5 text-[11px] leading-5 text-slate-500">
        <input type="checkbox" name="terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        <span>I agree to the <a href="<?= e(url('/terms')) ?>" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">terms</a> and <a href="<?= e(url('/privacy')) ?>" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">privacy policy</a>, and I will use only authorized provider accounts.</span>
    </label>
    <button type="submit" class="btn-primary w-full">Create account <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
</form>
<p class="mt-5 text-center text-[11.5px] text-slate-500">Already have an account? <a href="<?= e(url('/login.php')) ?>" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Sign in</a></p>
