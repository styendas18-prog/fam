<?php
$title = 'Forgot password';
$authHeading = 'Reset your password';
$authCopy = 'Enter the email on your account. If it exists, we will send a time-limited reset link.';
?>
<form method="post" action="<?= e(url('/forgot-password.php')) ?>" class="space-y-4" data-loading>
    <?= csrf_field() ?>
    <div>
        <label for="email" class="label">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= old('email') ?>" class="input" placeholder="you@business.com">
    </div>
    <button type="submit" class="btn-primary w-full">Send reset link <i data-lucide="send" class="h-4 w-4"></i></button>
</form>
<div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-[10.5px] leading-5 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
    For security, the response is the same whether or not the email is registered. Reset links expire after 30 minutes and can be used once.
</div>
<p class="mt-5 text-center text-[11.5px] text-slate-500"><a href="<?= e(url('/login.php')) ?>" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Back to sign in</a></p>
