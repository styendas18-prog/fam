<?php
$categories = ['general' => 'General', 'integration' => 'Integration', 'payment' => 'Payment', 'account' => 'Account', 'security' => 'Security', 'billing' => 'Billing'];
$priorityLabels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
?>
<div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)] xl:items-start">
    <div class="space-y-4">
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Your tickets</h2><span class="badge-info"><?= count($tickets) ?></span></div>
            <?php if (!$tickets): ?>
            <div class="px-5 py-10 text-center"><span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500"><i data-lucide="life-buoy" class="h-5 w-5"></i></span><p class="mt-3 text-[12px] font-semibold text-slate-700 dark:text-slate-200">No tickets yet</p><p class="mt-1 text-[10.5px] leading-5 text-slate-500">Open a ticket and the team will reply in this thread.</p></div>
            <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                <?php foreach ($tickets as $ticket): $meta = status_meta($ticket['status']); $isActive = $activeTicket && (int) $activeTicket['id'] === (int) $ticket['id']; ?>
                <a href="<?= e(url('/user/support.php?ticket=' . (int) $ticket['id'])) ?>" class="block px-5 py-3.5 transition <?= $isActive ? 'bg-brand-50/70 dark:bg-brand-400/[0.06]' : 'hover:bg-slate-50/70 dark:hover:bg-white/[0.02]' ?>">
                    <div class="flex items-start justify-between gap-2"><p class="truncate text-[11.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($ticket['subject']) ?></p><span class="<?= e($meta['class']) ?> shrink-0"><?= e($meta['label']) ?></span></div>
                    <p class="mt-1 font-mono text-[9.5px] text-slate-400"><?= e($ticket['ticket_number']) ?> · <?= (int) $ticket['message_count'] ?> message<?= (int) $ticket['message_count'] === 1 ? '' : 's' ?></p>
                    <p class="mt-1 text-[9.5px] text-slate-400">Updated <?= e(time_ago($ticket['updated_at'])) ?></p>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[12.5px] font-bold text-slate-900 dark:text-white">New ticket</h2><p class="text-[10px] text-slate-500">Include order IDs and timestamps where relevant.</p></div>
            <form method="post" action="<?= e(url('/user/support.php')) ?>" class="space-y-3.5 p-5" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div><label for="subject" class="label">Subject</label><input id="subject" name="subject" required minlength="3" maxlength="190" class="input" placeholder="Short summary"></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label for="category" class="label">Category</label><select id="category" name="category" class="input"><?php foreach ($categories as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div><label for="priority" class="label">Priority</label><select id="priority" name="priority" class="input"><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $value === 'normal' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                </div>
                <div><label for="message" class="label">Message</label><textarea id="message" name="message" required minlength="10" maxlength="5000" rows="5" class="input resize-y" placeholder="Describe the issue, what you expected and what happened."></textarea></div>
                <button type="submit" class="btn-primary w-full"><i data-lucide="send" class="h-4 w-4"></i> Create ticket</button>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <?php if ($activeTicket): $meta = status_meta($activeTicket['status']); ?>
        <div class="card">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="truncate text-[13px] font-bold text-slate-900 dark:text-white"><?= e($activeTicket['subject']) ?></h2><span class="<?= e($meta['class']) ?>"><span class="h-1.5 w-1.5 rounded-full <?= e($meta['dot']) ?>"></span> <?= e($meta['label']) ?></span></div>
                    <p class="mt-1 font-mono text-[9.5px] text-slate-400"><?= e($activeTicket['ticket_number']) ?> · <?= e(ucfirst($activeTicket['category'])) ?> · <?= e(ucfirst($activeTicket['priority'])) ?> priority · opened <?= e(ist($activeTicket['created_at'], 'd M Y, H:i')) ?></p>
                </div>
                <a href="<?= e(url('/user/support.php')) ?>" class="btn-ghost btn-sm self-start">Close view</a>
            </div>
            <div class="space-y-4 p-5">
                <?php foreach ($messages as $message): $fromUser = $message['sender_type'] === 'user'; ?>
                <div class="flex gap-3 <?= $fromUser ? 'flex-row-reverse' : '' ?>">
                    <span class="avatar h-8 w-8 shrink-0 text-[10px] <?= $fromUser ? '!bg-slate-600' : '' ?>"><?= e($fromUser ? initials((string) $user['name']) : 'MF') ?></span>
                    <div class="max-w-[85%] rounded-2xl border px-4 py-3 <?= $fromUser ? 'border-brand-200 bg-brand-50 dark:border-brand-400/20 dark:bg-brand-400/[0.08]' : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]' ?>">
                        <p class="text-[10px] font-semibold <?= $fromUser ? 'text-brand-700 dark:text-brand-200' : 'text-slate-500' ?>"><?= e($fromUser ? $user['name'] : ($message['admin_name'] ?: 'Support team')) ?> · <?= e(ist($message['created_at'], 'd M, H:i')) ?></p>
                        <p class="mt-1.5 whitespace-pre-line text-[11.5px] leading-5 text-slate-700 dark:text-slate-300"><?= e($message['message']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($messages === []): ?><p class="py-6 text-center text-[11px] text-slate-500">No messages on this ticket.</p><?php endif; ?>
            </div>
            <?php if ($activeTicket['status'] !== 'closed'): ?>
            <form method="post" action="<?= e(url('/user/support.php')) ?>" class="border-t border-slate-100 p-5 dark:border-white/[0.06]" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="ticket_id" value="<?= (int) $activeTicket['id'] ?>">
                <label for="reply" class="label">Reply</label>
                <textarea id="reply" name="message" required minlength="2" maxlength="5000" rows="4" class="input resize-y" placeholder="Add details for the support team…"></textarea>
                <div class="mt-3 flex justify-end"><button type="submit" class="btn-primary btn-sm"><i data-lucide="send" class="h-3.5 w-3.5"></i> Send reply</button></div>
            </form>
            <?php else: ?>
            <div class="border-t border-slate-100 px-5 py-4 text-[10.5px] text-slate-500 dark:border-white/[0.06]">This ticket is closed. Create a new ticket if you need further help.</div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-400/10 dark:text-brand-300"><i data-lucide="messages-square" class="h-5 w-5"></i></span>
                <h2 class="mt-4 text-[14px] font-bold text-slate-800 dark:text-white">How can we help?</h2>
                <p class="mx-auto mt-1.5 max-w-md text-[11px] leading-5 text-slate-500">Open a ticket for integration help, payment investigation, account questions or security concerns. Include the payment order ID where relevant — never send your API key, UPI PIN or OTP.</p>
                <div class="mx-auto mt-6 grid max-w-lg gap-2 text-left sm:grid-cols-3">
                    <?php foreach ([['Payment issue', 'receipt-text'], ['Integration', 'plug-zap'], ['Account', 'user-round']] as [$label, $icon]): ?>
                    <div class="rounded-xl border border-slate-200 px-3 py-3 dark:border-white/10"><i data-lucide="<?= e($icon) ?>" class="h-4 w-4 text-brand-500"></i><p class="mt-2 text-[10.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($label) ?></p></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
