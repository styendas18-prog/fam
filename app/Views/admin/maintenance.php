<?php
$settings = $settings ?? [];
$isMaintenance = ($settings['maintenance_mode'] ?? '0') === '1';
?>
<div class="card max-w-2xl">
    <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-[14px] font-bold text-slate-900 dark:text-white">Maintenance Control</h2>
                <p class="text-[11px] text-slate-500">Administrators retain full access to /admin while public traffic is held.</p>
            </div>
            <span class="<?= $isMaintenance ? 'badge-danger' : 'badge-success' ?>">
                <?= $isMaintenance ? 'MAINTENANCE ACTIVE' : 'LIVE SYSTEM' ?>
            </span>
        </div>
    </div>

    <form method="post" class="space-y-6 p-6">
        <?= csrf_field() ?>

        <div class="rounded-xl border p-4 <?= $isMaintenance ? 'border-rose-300 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10' : 'border-slate-200 bg-slate-50 dark:border-white/5 dark:bg-white/[0.02]' ?>">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="maintenance_mode" value="1" <?= $isMaintenance ? 'checked' : '' ?>
                       class="mt-1 h-5 w-5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                <div>
                    <span class="text-[13px] font-bold <?= $isMaintenance ? 'text-rose-900 dark:text-rose-200' : 'text-slate-900 dark:text-white' ?>">
                        Enable System Maintenance Mode
                    </span>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500">
                        When enabled, all public pages and new checkout sessions return HTTP 503 Service Unavailable with the notice below. Status and Contact pages remain operational so customers can check platform announcements.
                    </p>
                </div>
            </label>
        </div>

        <div>
            <label class="label" for="maintenance_message">Public Maintenance Message</label>
            <textarea id="maintenance_message" name="maintenance_message" rows="3" required class="input"><?= e($settings['maintenance_message'] ?? '') ?></textarea>
            <p class="mt-1 text-[10.5px] text-slate-500">Displayed to visitors when accessing the platform during maintenance.</p>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primary">
                <i data-lucide="shield" class="h-4 w-4"></i> Apply Maintenance Settings
            </button>
        </div>
    </form>
</div>
