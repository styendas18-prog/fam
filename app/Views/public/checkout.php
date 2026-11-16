<?php
use App\Core\Security;
use App\Core\Settings;

$order = $order ?? [];
$merchant = $merchant ?? [];
$siteName = (string) Settings::get('site_name', 'MAXXfam');
$initialState = match ($order['status'] ?? 'pending') {
    'success' => 'success',
    'expired' => 'expired',
    'failed' => 'failed',
    'cancelled' => 'cancelled',
    default => 'waiting',
};
$expiresIso = !empty($order['expires_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($order['expires_at'] . ' UTC')) : '';
$payableAmount = (float) ($order['payable_amount'] ?? $order['amount'] ?? 0);
$qrPayload = $order['qr_url'] ?? $order['upi_intent'] ?? ('upi://pay?pa=' . urlencode($order['upi_id'] ?? 'merchant@upi') . '&pn=' . urlencode($order['business_name'] ?? 'Merchant') . '&am=' . number_format($payableAmount, 2, '.', '') . '&cu=INR&tn=' . urlencode($order['public_id']));
?>
<div id="checkout-root"
     data-token="<?= e($order['checkout_token']) ?>"
     data-csrf="<?= e(\App\Core\Csrf::token()) ?>"
     data-status-url="<?= e(url('/checkout.php')) ?>"
     data-state="<?= e($initialState) ?>"
     class="min-h-screen bg-[#f4f7fb] px-4 py-8 dark:bg-ink-950 sm:py-12">

    <div class="mx-auto max-w-lg">
        <!-- Header -->
        <header class="mb-6 flex items-center justify-between">
            <a href="<?= e(url('/')) ?>" class="flex items-center gap-2">
                <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="<?= e($siteName) ?>" class="h-8 w-auto [filter:invert(1)] dark:[filter:none]">
            </a>
            <div class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                <span>256-bit Encrypted Checkout</span>
            </div>
        </header>

        <!-- Main Checkout Card -->
        <main class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-ink-900">
            <!-- Merchant & Order Summary Banner -->
            <div class="border-b border-slate-100 bg-slate-50/70 p-5 dark:border-white/[0.06] dark:bg-white/[0.02]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Paying To</p>
                        <h1 class="mt-0.5 text-[17px] font-bold text-slate-900 dark:text-white"><?= e($order['business_name'] ?? 'Merchant') ?></h1>
                        <?php if (!empty($order['description'])): ?>
                            <p class="mt-1 text-[12px] text-slate-500 dark:text-slate-400"><?= e($order['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Payable Amount</p>
                        <p class="mt-0.5 font-mono text-[24px] font-extrabold text-brand-600 dark:text-brand-400">
                            <?= e(money($payableAmount)) ?>
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/60 pt-3 text-[11px] text-slate-500 dark:border-white/5 dark:text-slate-400">
                    <div>Order ID: <span class="font-mono font-semibold text-slate-700 dark:text-slate-300"><?= e($order['public_id']) ?></span></div>
                    <?php if (!empty($order['customer_reference'])): ?>
                        <div>Ref: <span class="font-medium text-slate-700 dark:text-slate-300"><?= e($order['customer_reference']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-6">
                <!-- State: WAITING (Payment pending / scan QR) -->
                <div id="state-waiting" class="<?= $initialState === 'waiting' ? '' : 'hidden' ?> space-y-6">
                    <div class="flex items-center justify-between rounded-xl bg-amber-500/10 px-4 py-2.5 text-[12px] font-medium text-amber-700 dark:text-amber-300">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="clock" class="h-4 w-4"></i>
                            Session expires in:
                        </span>
                        <span class="font-mono text-[14px] font-bold" data-countdown="<?= e($expiresIso) ?>">--:--</span>
                    </div>

                    <!-- QR Code Display Box -->
                    <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-6 dark:border-white/10 dark:bg-white/[0.02]">
                        <?php if (!empty($order['qr_url']) && filter_var($order['qr_url'], FILTER_VALIDATE_URL)): ?>
                            <div class="overflow-hidden rounded-xl bg-white p-3 shadow-md dark:bg-white">
                                <img src="<?= e($order['qr_url']) ?>" alt="UPI QR Code" class="h-48 w-48 object-contain">
                            </div>
                        <?php else: ?>
                            <div class="overflow-hidden rounded-xl bg-white p-3 shadow-md dark:bg-white">
                                <div data-qr="<?= e($qrPayload) ?>" data-qr-size="192" class="h-48 w-48"></div>
                            </div>
                        <?php endif; ?>

                        <p class="mt-4 text-center text-[13px] font-semibold text-slate-800 dark:text-slate-200">
                            Scan with any UPI app to pay
                        </p>
                        <p class="mt-1 text-center text-[11px] text-slate-500">
                            Google Pay · PhonePe · Paytm · BHIM · Cred · Banking UPI
                        </p>
                    </div>

                    <!-- UPI Intent Button for Mobile Users -->
                    <?php if (!empty($order['upi_intent'])): ?>
                    <div class="block sm:hidden">
                        <a href="<?= e($order['upi_intent']) ?>" class="btn-primary w-full justify-center py-3 text-[14px]">
                            <i data-lucide="smartphone" class="h-4 w-4"></i>
                            Pay via UPI App
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Live Polling Indicator -->
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-white/5 dark:bg-white/[0.02]">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-brand-500"></span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[12px] font-medium text-slate-700 dark:text-slate-300">
                                    Checking real-time payment status...
                                </p>
                                <p id="waiting-note" class="text-[10.5px] text-slate-500">
                                    Do not close or refresh this window until payment completes.
                                </p>
                            </div>
                        </div>
                        <p id="poll-note" class="mt-2 hidden text-[11px] text-amber-600 dark:text-amber-400"></p>
                    </div>

                    <!-- Cancel Button -->
                    <form method="post" action="<?= e(url('/checkout.php')) ?>" class="text-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($order['checkout_token']) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="text-[12px] font-medium text-slate-400 hover:text-rose-500">
                            Cancel payment
                        </button>
                    </form>
                </div>

                <!-- State: SUCCESS -->
                <div id="state-success" class="<?= $initialState === 'success' ? '' : 'hidden' ?> space-y-6 py-4 text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                        <i data-lucide="check-circle-2" class="h-9 w-9"></i>
                    </div>
                    <div>
                        <span class="rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            Verified Payment
                        </span>
                        <h2 class="mt-3 text-[22px] font-extrabold text-slate-900 dark:text-white">Payment Successful</h2>
                        <p class="mt-1 text-[13px] text-slate-500">
                            Provider confirmation received and verified server-side.
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-left dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex items-center justify-between text-[12px]">
                            <span class="text-slate-500">Amount Paid</span>
                            <span class="font-bold text-slate-900 dark:text-white"><?= e(money($payableAmount)) ?></span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-slate-200/60 pt-2 text-[12px] dark:border-white/5">
                            <span class="text-slate-500">Bank UTR / Ref</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-white"><?= e($order['utr'] ?? 'Verified') ?></span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a id="receipt-link"
                           href="<?= e(url('/receipt.php?token=' . urlencode((string) $order['checkout_token']))) ?>"
                           class="btn-primary w-full justify-center">
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Download Official Receipt (PDF)
                        </a>

                        <?php if (!empty($order['success_redirect_url'])): ?>
                            <a id="redirect-link" href="<?= e($order['success_redirect_url']) ?>" class="btn-secondary w-full justify-center">
                                Return to Merchant
                            </a>
                            <p id="redirect-countdown" class="text-[11px] text-slate-500"></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- State: EXPIRED -->
                <div id="state-expired" class="<?= $initialState === 'expired' ? '' : 'hidden' ?> space-y-5 py-4 text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                        <i data-lucide="alert-triangle" class="h-9 w-9"></i>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-bold text-slate-900 dark:text-white">Session Expired</h2>
                        <p class="mt-2 text-[12.5px] leading-relaxed text-slate-500">
                            This dynamic payment session has timed out. For security, never scan or pay an expired dynamic QR code.
                        </p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-left text-[11px] leading-5 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/5 dark:text-amber-300">
                        <p class="font-semibold">Did money leave your account?</p>
                        <p class="mt-1 text-amber-700/80 dark:text-amber-300/80">
                            If money was debited from your bank, the UPI network will automatically reverse it back to your source account within 24 to 48 business hours.
                        </p>
                    </div>
                </div>

                <!-- State: FAILED -->
                <div id="state-failed" class="<?= $initialState === 'failed' ? '' : 'hidden' ?> space-y-5 py-4 text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400">
                        <i data-lucide="x-circle" class="h-9 w-9"></i>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-bold text-slate-900 dark:text-white">Payment Failed</h2>
                        <p id="failure-reason" class="mt-2 text-[12.5px] text-rose-600 dark:text-rose-400">
                            <?= e($order['failure_message'] ?? 'The payment could not be processed by the bank.') ?>
                        </p>
                    </div>
                </div>

                <!-- State: CANCELLED -->
                <div id="state-cancelled" class="<?= $initialState === 'cancelled' ? '' : 'hidden' ?> space-y-5 py-4 text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">
                        <i data-lucide="ban" class="h-9 w-9"></i>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-bold text-slate-900 dark:text-white">Payment Cancelled</h2>
                        <p class="mt-2 text-[12.5px] text-slate-500">
                            This transaction was cancelled by user request.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Notes -->
            <div class="border-t border-slate-100 bg-slate-50/50 p-4 text-center text-[10.5px] text-slate-500 dark:border-white/[0.06] dark:bg-white/[0.01]">
                <p>Never share your UPI PIN or banking passwords with anyone.</p>
                <?php if (!empty($order['support_email'])): ?>
                    <p class="mt-1">Merchant support: <a href="mailto:<?= e($order['support_email']) ?>" class="font-medium text-brand-600 dark:text-brand-400"><?= e($order['support_email']) ?></a></p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
