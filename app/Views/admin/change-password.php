<?php $forced = $forced ?? true; ?>
<div class="card max-w-xl">
    <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Administrator password</h2>
                <p class="mt-0.5 text-[11px] text-slate-500"><?= e($adminEmail ?? '') ?></p>
            </div>
            <span class="<?= $forced ? 'badge-warning' : 'badge-neutral' ?>"><?= $forced ? 'Rotation required' : 'Update' ?></span>
        </div>
    </div>
    <?php if ($forced): ?>
    <div class="border-b border-amber-200 bg-amber-50 px-6 py-3 text-[11px] leading-5 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/[0.06] dark:text-amber-200">
        This account was provisioned with a temporary password. You must choose a permanent password before the control panel unlocks.
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/change-password.php')) ?>" class="space-y-4 p-6" data-loading>
        <?= csrf_field() ?>
        <div>
            <label for="current_password" class="label">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="input">
        </div>
        <div>
            <label for="new_password" class="label">New password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="12" class="input">
            <p class="hint !mt-1 !text-[9.5px]">At least 12 characters with upper case, lower case, and a number. Do not reuse the temporary password.</p>
        </div>
        <div>
            <label for="new_password_confirmation" class="label">Confirm new password</label>
            <input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" required minlength="12" class="input">
        </div>
        <button class="btn-primary w-full justify-center">Save permanent password</button>
    </form>
</div>
