<?php
$title = 'Developer documentation';
$nav = 'docs';
$eyebrow = 'Documentation';
$heading = 'Build a verified UPI integration.';
$copy = 'A complete guide to authentication, orders, verification, webhooks and going live with MAXXfam.';
require BASE_PATH . '/app/Views/public/_page-hero.php';

$sections = [
    'quickstart' => 'Quickstart',
    'authentication' => 'Authentication',
    'create-payment' => 'Create a payment',
    'payment-status' => 'Payment status',
    'webhooks' => 'Webhooks',
    'signatures' => 'Verify signatures',
    'states' => 'Payment states',
    'errors' => 'Errors & rate limits',
    'security' => 'Security model',
    'golive' => 'Go-live checklist',
];
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-14">
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <nav class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-3" aria-label="Documentation sections">
                    <?php foreach ($sections as $anchor => $label): ?>
                    <a href="#<?= e($anchor) ?>" class="block rounded-lg px-3 py-2 text-[11.5px] font-medium text-slate-400 hover:bg-white/5 hover:text-white"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="mt-4 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-4">
                    <p class="text-[11px] font-semibold text-white">Base URL</p>
                    <code class="mt-1.5 block break-all font-mono text-[10px] text-brand-300"><?= e(url('/api/v1')) ?></code>
                    <p class="mt-3 text-[10px] leading-5 text-slate-500">All requests and responses are JSON. Amounts are decimal strings or numbers in INR.</p>
                </div>
            </aside>

            <div class="min-w-0 max-w-4xl space-y-10 text-[12.5px] leading-7 text-slate-400">
                <section id="quickstart">
                    <h2 class="text-xl font-bold text-white">Quickstart</h2>
                    <p class="mt-3">1. Create an account and open <strong class="text-slate-200">Integration</strong>. Save an authorized provider API key and run the connection test. Only a real authenticated request can move the provider to Connected.</p>
                    <p class="mt-2">2. Open <strong class="text-slate-200">API keys</strong>, create a key with the scopes you need, and copy it once. Keys look like <code class="rounded bg-white/5 px-1.5 py-0.5 font-mono text-[11px] text-brand-300">mf_live_…</code>.</p>
                    <p class="mt-2">3. Call the create-payment endpoint from your server. Never expose an API key in browser JavaScript.</p>
                    <div class="code-block mt-4">
                        <div class="flex items-center justify-between border-b border-white/[0.07] px-4 py-2.5"><span class="font-mono text-[10px] text-slate-500">cURL</span><span class="rounded bg-white/5 px-2 py-0.5 font-mono text-[9px] text-slate-400">POST /api/v1/payments/create</span></div>
                        <pre><code>curl -X POST <?= e(url('/api/v1/payments/create')) ?> \
  -H <span class="text-emerald-300">"Authorization: Bearer mf_live_YOUR_KEY"</span> \
  -H <span class="text-emerald-300">"Content-Type: application/json"</span> \
  -d <span class="text-amber-200">'{"amount":"499.00","customer_reference":"ORDER-1042","description":"Pro plan"}'</span></code></pre>
                    </div>
                    <p class="mt-3">4. Redirect the customer to <code class="text-brand-300">data.checkout_url</code>, or render <code class="text-brand-300">data.qr_url</code> in your own UI. Poll <code class="text-brand-300">GET /api/v1/payments/{order_id}</code> or wait for a signed webhook.</p>
                </section>

                <section id="authentication">
                    <h2 class="text-xl font-bold text-white">Authentication</h2>
                    <p class="mt-3">Send your secret key as a Bearer token. The key is tied to one merchant and a set of scopes. Requests without a valid, active key receive <code class="text-rose-300">401 unauthorized</code>.</p>
                    <div class="code-block mt-4"><pre><code>Authorization: Bearer mf_live_0123456789abcdef…
Content-Type: application/json
Accept: application/json</code></pre></div>
                    <p class="mt-3">Key rules: keys are shown once, stored as SHA-256 hashes, can be revoked or rotated, and are rate limited. Use separate keys per environment and revoke a key immediately if it leaks.</p>
                </section>

                <section id="create-payment">
                    <h2 class="text-xl font-bold text-white">Create a payment</h2>
                    <div class="mt-3 rounded-xl border border-white/[0.08] bg-white/[0.025] p-4">
                        <p class="font-mono text-[11px] text-emerald-300">POST /api/v1/payments/create</p>
                        <p class="mt-1 font-mono text-[10px] text-slate-500">scope: payments:write</p>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-white/[0.08]">
                        <table class="w-full text-left text-[11px]">
                            <thead class="bg-white/[0.03] text-[9.5px] uppercase tracking-[0.1em] text-slate-500"><tr><th class="px-3 py-2.5">Field</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Required</th><th class="px-3 py-2.5">Notes</th></tr></thead>
                            <tbody class="divide-y divide-white/[0.06]">
                                <?php foreach ([
                                    ['amount', 'number', 'Yes', 'INR amount. Must be between the platform minimum and maximum.'],
                                    ['customer_reference', 'string', 'No', 'Your idempotency reference. Reuse returns the existing order.'],
                                    ['customer_name', 'string', 'No', 'Shown on checkout and receipts.'],
                                    ['customer_email', 'string', 'No', 'Used for notifications where enabled.'],
                                    ['customer_phone', 'string', 'No', 'Optional customer phone.'],
                                    ['description', 'string', 'No', 'Short order description.'],
                                    ['success_redirect_url', 'https URL', 'No', 'Where the customer returns after a verified payment.'],
                                ] as [$field, $type, $req, $note]): ?>
                                <tr><td class="px-3 py-2.5 font-mono text-brand-300"><?= e($field) ?></td><td class="px-3 py-2.5"><?= e($type) ?></td><td class="px-3 py-2.5"><?= e($req) ?></td><td class="px-3 py-2.5 text-slate-500"><?= e($note) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3">The response includes the local order ID, provider order ID, amount, status, QR URL, hosted checkout URL and expiry. Creation never returns success for payment status.</p>
                    <div class="code-block mt-4"><pre><code>{
  <span class="text-sky-300">"success"</span>: true,
  <span class="text-sky-300">"data"</span>: {
    <span class="text-sky-300">"order_id"</span>: <span class="text-emerald-300">"MF8C41D9A62E7B5F0A1B2C3D"</span>,
    <span class="text-sky-300">"provider_order_id"</span>: <span class="text-emerald-300">"FG-…"</span>,
    <span class="text-sky-300">"status"</span>: <span class="text-amber-200">"pending"</span>,
    <span class="text-sky-300">"amount"</span>: <span class="text-amber-200">"499.00"</span>,
    <span class="text-sky-300">"payable_amount"</span>: <span class="text-amber-200">"499.00"</span>,
    <span class="text-sky-300">"qr_url"</span>: <span class="text-emerald-300">"https://…"</span>,
    <span class="text-sky-300">"checkout_url"</span>: <span class="text-emerald-300">"…/checkout.php?token=…"</span>,
    <span class="text-sky-300">"expires_at"</span>: <span class="text-emerald-300">"2026-10-31T12:30:00+05:30"</span>
  }
}</code></pre></div>
                </section>

                <section id="payment-status">
                    <h2 class="text-xl font-bold text-white">Payment status</h2>
                    <p class="mt-3"><code class="text-brand-300">GET /api/v1/payments/{order_id}</code> returns your order and, when verified, the transaction UTR and receipt. Scope: <code class="text-slate-300">payments:read</code>.</p>
                    <p class="mt-2">Do not infer success from a redirect. Only a response with <code class="text-emerald-300">status: "success"</code> and a transaction UTR is verified.</p>
                    <p class="mt-2"><code class="text-brand-300">GET /api/v1/payments?status=success&amp;page=1&amp;per_page=20</code> lists your orders with filters for status, date range and search.</p>
                </section>

                <section id="webhooks">
                    <h2 class="text-xl font-bold text-white">Webhooks</h2>
                    <p class="mt-3">Register an HTTPS endpoint under <strong class="text-slate-200">Webhooks</strong> and subscribe to events. MAXXfam sends:</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <?php foreach (['payment.created', 'payment.pending', 'payment.success', 'payment.failed', 'payment.expired', 'webhook.test'] as $event): ?>
                        <code class="rounded-lg border border-white/[0.08] bg-white/[0.025] px-3 py-2 font-mono text-[10.5px] text-brand-300"><?= e($event) ?></code>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-3">Headers include <code class="text-slate-300">X-MAXXfam-Event</code>, <code class="text-slate-300">X-MAXXfam-Delivery</code> and <code class="text-slate-300">X-MAXXfam-Signature</code> (or the compatible provider signature header on provider callbacks). Deliveries retry with exponential backoff. Your endpoint must return HTTP 2xx quickly; do the heavy work asynchronously.</p>
                    <p class="mt-2">Treat webhooks as a notification to fetch status, not as proof of payment. Always re-check the payment before fulfilling goods or services.</p>
                </section>

                <section id="signatures">
                    <h2 class="text-xl font-bold text-white">Verify signatures</h2>
                    <p class="mt-3">The signature is an HMAC-SHA256 hex digest of the exact raw request body, keyed with the webhook secret shown when you created the endpoint (or the provider API key for provider callbacks). Compare in constant time.</p>
                    <div class="code-block mt-4"><pre><code><span class="text-slate-500">// PHP</span>
$raw = file_get_contents('php://input');
$expected = hash_hmac('sha256', $raw, $webhookSecret);
$received = $_SERVER['HTTP_X_MAXXFAM_SIGNATURE'] ?? '';
if (!hash_equals($expected, $received)) {
    http_response_code(401);
    exit;
}
$event = json_decode($raw, true);
<span class="text-slate-500">// Then fetch /api/v1/payments/{order_id} before fulfilling.</span></code></pre></div>
                    <p class="mt-2">Never verify a signature against a parsed-and-re-encoded JSON string. Use the raw body bytes.</p>
                </section>

                <section id="states">
                    <h2 class="text-xl font-bold text-white">Payment states</h2>
                    <div class="mt-3 space-y-2">
                        <?php foreach ([
                            ['pending', 'Order exists and the provider session is active. Not paid.'],
                            ['success', 'Provider-confirmed amount and a unique 12-digit UTR. Terminal.'],
                            ['failed', 'Provider creation/status failure or explicit provider failure.'],
                            ['expired', 'The payment window elapsed without verification.'],
                            ['cancelled', 'Cancelled before payment. Cancelling does not refund anything.'],
                        ] as [$state, $desc]): ?>
                        <div class="flex flex-col gap-1 rounded-lg border border-white/[0.07] bg-white/[0.02] px-3.5 py-2.5 sm:flex-row sm:items-center sm:gap-4">
                            <code class="w-24 shrink-0 font-mono text-[10.5px] text-brand-300"><?= e($state) ?></code>
                            <p class="text-[11px] text-slate-500"><?= e($desc) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section id="errors">
                    <h2 class="text-xl font-bold text-white">Errors and rate limits</h2>
                    <p class="mt-3">Errors use a stable envelope: <code class="text-slate-300">{"success":false,"error":{"code":"…","message":"…"}}</code>. Common codes: <code class="text-rose-300">unauthorized</code>, <code class="text-rose-300">forbidden_scope</code>, <code class="text-rose-300">validation_failed</code>, <code class="text-rose-300">integration_not_configured</code>, <code class="text-rose-300">provider_disabled</code>, <code class="text-rose-300">amount_out_of_range</code>, <code class="text-rose-300">rate_limited</code>.</p>
                    <p class="mt-2">API requests are rate limited per key and per IP. A <code class="text-slate-300">429</code> response includes <code class="text-slate-300">Retry-After</code>. Back off instead of retrying in a tight loop. Creation is idempotent on <code class="text-slate-300">customer_reference</code>.</p>
                </section>

                <section id="security">
                    <h2 class="text-xl font-bold text-white">Security model</h2>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5">
                        <li>Provider credentials encrypted at rest with AES-256-GCM; never returned by any API.</li>
                        <li>Merchant API keys stored as SHA-256 hashes with scopes, status, expiry and last-used time.</li>
                        <li>CSRF protection on all state-changing dashboard forms; JSON APIs require authentication.</li>
                        <li>Login and API rate limiting, account lockout, session rotation, secure cookies.</li>
                        <li>SSRF validation on webhook destinations; private and reserved IPs are rejected.</li>
                        <li>Admin actions can be protected by optional TOTP 2FA and are written to an immutable activity log.</li>
                        <li>No endpoint can force a payment to success. Verification is a server-only state machine.</li>
                    </ul>
                </section>

                <section id="golive">
                    <h2 class="text-xl font-bold text-white">Go-live checklist</h2>
                    <div class="mt-3 space-y-2.5">
                        <?php foreach ([
                            'Set APP_ENV=production, APP_DEBUG=false and a unique APP_KEY.',
                            'Serve the site over HTTPS only, with public/ as the document root.',
                            'Create the first admin via CLI, sign in and enrol TOTP immediately.',
                            'Save an authorized provider key and confirm a true Connected result.',
                            'Create one low-value live order and verify the full UTR path end to end.',
                            'Configure SMTP and send a real test email.',
                            'Register your HTTPS webhook, verify a signature, and check retry behavior.',
                            'Schedule expire_orders, verify_payments, retry_webhooks and process_email cron jobs.',
                            'Review privacy, terms and support contact details for your entity.',
                        ] as $item): ?>
                        <label class="flex items-start gap-3 rounded-lg border border-white/[0.07] bg-white/[0.02] px-3.5 py-2.5 text-[11px] text-slate-400"><span class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded border border-white/20 text-[9px] text-slate-500">✓</span> <?= e($item) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="<?= e(url('/api-reference')) ?>" class="btn-primary btn-sm">API reference</a>
                        <a href="<?= e(url('/register.php')) ?>" class="btn-secondary btn-sm">Create account</a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
