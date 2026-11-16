<?php
$settings = $settings ?? [];
$adminEmail = $adminEmail ?? '';
?>
<div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
    <div class="card">
        <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
            <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">SMTP Server Settings</h2>
            <p class="text-[11px] text-slate-500">Credentials are used to send verification receipts, password resets, and login alerts.</p>
        </div>

        <form method="post" class="space-y-5 p-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="label" for="smtp_host">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.mailgun.org or smtp.gmail.com" class="input font-mono">
                </div>
                <div>
                    <label class="label" for="smtp_port">Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>" class="input font-mono">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption" class="input">
                        <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (Port 587)</option>
                        <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL / TLS (Port 465)</option>
                        <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (Plaintext)</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="smtp_username">SMTP Username</label>
                    <input type="text" id="smtp_username" name="smtp_username" value="<?= e($settings['smtp_username'] ?? '') ?>" placeholder="username@example.com" class="input" autocomplete="new-password">
                </div>
            </div>

            <div>
                <label class="label" for="smtp_password">SMTP Password</label>
                <input type="password" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep existing password" class="input" autocomplete="new-password">
                <p class="mt-1 text-[10px] text-slate-400">Stored securely and never rendered in frontend forms.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="mail_from_address">From Email Address</label>
                    <input type="email" id="mail_from_address" name="mail_from_address" value="<?= e($settings['mail_from_address'] ?? '') ?>" placeholder="noreply@yourdomain.com" class="input">
                </div>
                <div>
                    <label class="label" for="mail_from_name">From Sender Name</label>
                    <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($settings['mail_from_name'] ?? 'MAXXfam') ?>" class="input">
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary">
                    <i data-lucide="check" class="h-4 w-4"></i> Save SMTP Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Test Connectivity Box -->
    <div class="space-y-6">
        <div class="card p-5">
            <h3 class="text-[13px] font-bold text-slate-900 dark:text-white">Live Connection Test</h3>
            <p class="text-[11px] text-slate-500 mb-4">Send a live test message to verify credentials and TLS negotiation.</p>

            <form method="post" data-loading class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test">

                <div>
                    <label class="label" for="test_email">Recipient Email</label>
                    <input type="email" id="test_email" name="test_email" value="<?= e($adminEmail) ?>" required class="input">
                </div>

                <button type="submit" class="btn-secondary w-full justify-center">
                    <i data-lucide="send" class="h-3.5 w-3.5"></i> Test SMTP Connection
                </button>
            </form>

            <?php if (!empty($settings['smtp_last_test_at'])): ?>
                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3 text-[11px] dark:border-white/5 dark:bg-white/[0.02]">
                    <p class="text-slate-400">Last Test: <?= e(ist($settings['smtp_last_test_at'], 'd M Y, H:i')) ?></p>
                    <p class="mt-1 font-semibold text-slate-700 dark:text-slate-300"><?= e($settings['smtp_last_test_result']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card p-5">
            <h3 class="text-[12px] font-bold text-slate-900 dark:text-white">Email Queue Worker</h3>
            <p class="mt-1 text-[11px] text-slate-500">Emails are queued to the database and sent asynchronously via the cron worker.</p>
            <div class="mt-3 font-mono text-[10.5px] rounded-lg bg-slate-100 p-2 text-slate-700 dark:bg-white/5 dark:text-slate-300">
                php cron/process_email.php
            </div>
        </div>
    </div>
</div>
