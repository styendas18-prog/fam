<?php
$title = 'Set a new password';
$authHeading = 'Choose a new password';
$authCopy = 'This link can be used once. After saving, all existing sessions for the account are revoked.';
?>
<form method="post" action="<?= e(url('/reset-password.php')) ?>" class="space-y-4" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <div>
        <label for="password" class="label">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10" class="input" placeholder="At least 10 characters">
        <p class="hint">Minimum 10 characters with upper case, lower case and a number.</p>
    </div>
    <div>
        <label for="password_confirmation" class="label">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="input" placeholder="Repeat password">
    </div>
    <button type="submit" class="btn-primary w-full">Save new password <i data-lucide="check" class="h-4 w-4"></i></button>
</form>
