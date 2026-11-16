<?php
$eventLabels = [
    'user_login_success' => ['Signed in', 'log-in', 'emerald'],
    'user_login_failed' => ['Failed sign-in', 'shield-alert', 'rose'],
    'user_login_blocked' => ['Blocked sign-in', 'shield-x', 'rose'],
    'user_logout' => ['Signed out', 'log-out', 'slate'],
    'password_changed' => ['Password changed', 'key-round', 'amber'],
    'password_change_failed' => ['Password change rejected', 'shield-alert', 'rose'],
    'sessions_revoked' => ['Sessions revoked', 'monitor-off', 'amber'],
    'api_key_created' => ['API key created', 'key-round', 'sky'],
    'api_key_revoked' => ['API key revoked', 'key-round', 'amber'],
    'webhook_created' => ['Webhook created', 'webhook', 'sky'],
    'payment_verified' => ['Payment verified', 'shield-check', 'emerald'],
    'payment_order_created' => ['Order created', 'circle-plus', 'sky'],
    'payment_order_failed' => ['Order failed', 'alert-circle', 'rose'],
];
?>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px] xl:items-start">
    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Change password</h2><p class="text-[10.5px] text-slate-500">At least 10 characters with upper case, lower case and a number.</p></div>
            <form method="post" action="<?= e(url('/user/security.php')) ?>" class="space-y-4 p-5" data-loading autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div><label for="current_password" class="label">Current password</label><input id="current_password" name="current_password" type="password" required class="input" autocomplete="current-password"></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="new_password" class="label">New password</label><input id="new_password" name="new_password" type="password" required minlength="10" class="input" autocomplete="new-password"></div>
                    <div><label for="new_password_confirmation" class="label">Confirm new password</label><input id="new_password_confirmation" name="new_password_confirmation" type="password" required minlength="10" class="input" autocomplete="new-password"></div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-[10px] leading-5 text-slate-500 dark:border-white/10 dark:bg-white/[0.03]">Changing your password signs out every other browser session. Your current session stays active.</div>
                <div class="flex justify-end"><button type="submit" class="btn-primary btn-sm"><i data-lucide="lock" class="h-3.5 w-3.5"></i> Update password</button></div>
            </form>
        </div>

        <div class="card">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
                <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Active sessions</h2><p class="text-[10.5px] text-slate-500">Server-recorded sign-ins for your account.</p></div>
                <form method="post" action="<?= e(url('/user/security.php')) ?>" data-confirm="Sign out every other session? Other devices will need to sign in again." data-confirm-title="Sign out other sessions"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_sessions"><button class="btn-secondary btn-sm"><i data-lucide="monitor-off" class="h-3.5 w-3.5"></i> Sign out others</button></form>
            </div>
            <?php if (!$sessions): ?>
            <p class="px-5 py-10 text-center text-[11px] text-slate-500">No session records yet. Sessions are recorded on each successful sign-in.</p>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($sessions as $session): $current = hash_equals((string) $session['session_hash'], $currentHash); $revoked = !empty($session['revoked_at']); ?>
                <div class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= $current ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400' ?>"><i data-lucide="<?= $current ? 'monitor-check' : 'monitor' ?>" class="h-4 w-4"></i></span>
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-[11.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e(mb_substr((string) ($session['user_agent'] ?: 'Unknown client'), 0, 90)) ?><?php if ($current): ?><span class="badge-success">This device</span><?php elseif ($revoked): ?><span class="badge-muted">Revoked</span><?php endif; ?></p>
                            <p class="mt-0.5 text-[9.5px] text-slate-400"><?= e($session['ip'] ?: 'Unknown IP') ?> · Started <?= e(ist($session['created_at'], 'd M Y, H:i')) ?> · Last activity <?= e(time_ago($session['last_activity_at'])) ?></p>
                        </div>
                    </div>
                    <?php if (!$current && !$revoked): ?><span class="badge-info self-start">Active</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[12.5px] font-bold text-slate-900 dark:text-white">Recent security events</h2></div>
            <?php if (!$events): ?>
            <p class="px-5 py-8 text-center text-[11px] text-slate-500">No security events recorded.</p>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($events as $event): $meta = $eventLabels[$event['event']] ?? [str_replace('_', ' ', ucfirst($event['event'])), 'activity', 'slate']; ?>
                <div class="flex items-start gap-3 px-5 py-3">
                    <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md <?= $meta[2] === 'rose' ? 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300' : ($meta[2] === 'amber' ? 'bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300' : ($meta[2] === 'emerald' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400')) ?>"><i data-lucide="<?= e($meta[1]) ?>" class="h-3 w-3"></i></span>
                    <div class="min-w-0"><p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200"><?= e($meta[0]) ?></p><p class="mt-0.5 text-[9.5px] text-slate-400"><?= e(ist($event['created_at'], 'd M Y, H:i')) ?> · <?= e($event['ip'] ?: 'unknown IP') ?></p></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-800">
            <h2 class="flex items-center gap-2 text-[12px] font-bold text-slate-900 dark:text-white"><i data-lucide="shield-check" class="h-4 w-4 text-brand-500"></i> Security model</h2>
            <ul class="mt-3 space-y-2 text-[10px] leading-5 text-slate-500">
                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span> API keys are stored only as SHA-256 hashes.</li>
                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span> Provider credentials are encrypted with AES-256-GCM.</li>
                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span> Failed sign-ins are rate-limited and locked out.</li>
                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span> Payments become successful only after provider verification.</li>
            </ul>
        </div>
    </div>
</div>
