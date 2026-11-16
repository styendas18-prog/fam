<?php
$base = rtrim((string) $baseUrl, '/');
$createExample = <<<JSON
curl -X POST {$base}/api/v1/payments/create \\
  -H "Authorization: Bearer mf_live_your_key" \\
  -H "Content-Type: application/json" \\
  -H "Idempotency-Key: order-2026-0001" \\
  -d '{
    "amount": 499.00,
    "currency": "INR",
    "customer_name": "Asha Verma",
    "customer_email": "asha@example.com",
    "customer_reference": "INV-2026-0001",
    "description": "Order #1042",
    "success_redirect_url": "https://example.com/payment/complete"
  }'
JSON;
$responseExample = <<<JSON
{
  "success": true,
  "data": {
    "order_id": "MF1A2B3C4D5E6F7890ABCDEF12",
    "status": "pending",
    "amount": "499.00",
    "payable_amount": "499.00",
    "currency": "INR",
    "provider": "famgateway",
    "checkout_url": "{$base}/checkout.php?token=...",
    "qr_url": "https://famgateway.in/...",
    "expires_at": "2026-10-31T18:32:00+05:30",
    "created_at": "2026-10-31T18:27:00+05:30"
  }
}
JSON;
$verifyExample = <<<JSON
curl "{$base}/api/v1/payments/MF1A2B3C4D5E6F7890ABCDEF12" \\
  -H "Authorization: Bearer mf_live_your_key"
JSON;
$webhookExample = <<<PHP
\$payload = file_get_contents('php://input');
\$signature = \$_SERVER['HTTP_X_MAXXFAM_SIGNATURE'] ?? '';
\$timestamp = \$_SERVER['HTTP_X_MAXXFAM_TIMESTAMP'] ?? '';
\$expected = hash_hmac('sha256', \$payload, \$webhookSecret);
if (!hash_equals(\$expected, \$signature)) {
    http_response_code(401);
    exit('invalid signature');
}
\$event = json_decode(\$payload, true);
// Always call the MAXXfam API to confirm the final state.
// A webhook alone is never treated as proof of payment.
http_response_code(200);
PHP;
?>
<div class="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)] xl:items-start">
    <aside class="card sticky top-[88px] hidden xl:block">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[12px] font-bold text-slate-900 dark:text-white">On this page</h2></div>
        <nav class="space-y-1 p-3 text-[11px]">
            <?php foreach ([
                ['quickstart', 'Quick start'], ['auth', 'Authentication'], ['create', 'Create payment'], ['read', 'Get payment'],
                ['list', 'List payments'], ['links', 'Payment links'], ['webhooks', 'Webhooks'], ['verify', 'Verification rules'],
                ['errors', 'Errors & limits'], ['idempotency', 'Idempotency'],
            ] as [$id, $label]): ?>
            <a href="#<?= e($id) ?>" class="block rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="border-t border-slate-100 p-4 dark:border-white/[0.06]"><a href="<?= e(url('/api-reference')) ?>" target="_blank" rel="noopener" class="btn-secondary btn-sm w-full justify-center">Public API reference <i data-lucide="arrow-up-right" class="h-3 w-3"></i></a></div>
    </aside>

    <div class="space-y-4">
        <section id="quickstart" class="card card-pad">
            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-300">Quick start</p>
            <h2 class="mt-2 text-[19px] font-bold tracking-tight text-slate-900 dark:text-white">Create your first real payment</h2>
            <ol class="mt-4 space-y-3 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">
                <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 font-mono text-[9px] font-bold text-white">1</span><span>Save an authorized provider API key under <a href="<?= e(url('/user/integration.php')) ?>" class="font-semibold text-brand-600 dark:text-brand-300">Integration</a> and run a connection test.</span></li>
                <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 font-mono text-[9px] font-bold text-white">2</span><span>Create a scoped API key under <a href="<?= e(url('/user/api-keys.php')) ?>" class="font-semibold text-brand-600 dark:text-brand-300">API keys</a>. Copy it once — only a hash is stored.</span></li>
                <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 font-mono text-[9px] font-bold text-white">3</span><span>Call <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[10px] dark:bg-white/10">POST /api/v1/payments/create</code> from your server, then send the customer to the returned checkout URL.</span></li>
                <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 font-mono text-[9px] font-bold text-white">4</span><span>Poll <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[10px] dark:bg-white/10">GET /api/v1/payments/{order_id}</code> or consume signed webhooks. Only <code class="font-mono text-[10px]">success</code> after provider verification means paid.</span></li>
            </ol>
        </section>

        <section id="auth" class="card card-pad">
            <h2 class="text-[15px] font-bold text-slate-900 dark:text-white">Authentication</h2>
            <p class="mt-2 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">All API requests use a Bearer token. Keys are scoped; a key without the required scope receives <code class="rounded bg-slate-100 px-1 font-mono text-[10px] dark:bg-white/10">403 insufficient_scope</code>.</p>
            <div class="code-block mt-3"><pre>Authorization: Bearer mf_live_…</pre></div>
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-[10.5px] leading-5 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/[0.06] dark:text-rose-200">Never put a key in browser JavaScript, a mobile app, or source control. Requests are server-to-server only.</div>
        </section>

        <section id="create" class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><span class="badge-success font-mono">POST</span><code class="font-mono text-[11.5px] text-slate-700 dark:text-slate-200">/api/v1/payments/create</code><span class="badge-info">payments:write</span></div>
            <p class="mt-3 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Creates a genuine provider order. This endpoint never returns a successful payment by itself — the initial state is always <code class="font-mono text-[10px]">pending</code> (or an error).</p>
            <div class="mt-4 overflow-x-auto"><table class="table-mf"><thead><tr><th>Field</th><th>Required</th><th>Notes</th></tr></thead><tbody>
                <tr><td class="font-mono text-[10.5px]">amount</td><td>Yes</td><td>Decimal INR amount within the platform limits.</td></tr>
                <tr><td class="font-mono text-[10.5px]">currency</td><td>No</td><td>Only <code>INR</code> is accepted.</td></tr>
                <tr><td class="font-mono text-[10.5px]">customer_name / email / phone</td><td>No</td><td>Passed to the provider and shown on the receipt.</td></tr>
                <tr><td class="font-mono text-[10.5px]">customer_reference</td><td>No</td><td>Your own order/invoice reference, echoed in webhooks.</td></tr>
                <tr><td class="font-mono text-[10.5px]">description</td><td>No</td><td>Up to 500 characters.</td></tr>
                <tr><td class="font-mono text-[10.5px]">success_redirect_url</td><td>No</td><td>HTTPS URL the checkout returns to after success.</td></tr>
            </tbody></table></div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Example request</p>
            <div class="code-block mt-2"><pre><?= e($createExample) ?></pre></div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Example response</p>
            <div class="code-block mt-2"><pre><?= e($responseExample) ?></pre></div>
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-[10.5px] leading-5 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/[0.06] dark:text-amber-200">The returned QR and checkout URL come from the live provider response. If credentials are missing or invalid, the request fails honestly with <code class="font-mono">integration_not_configured</code> or a provider error.</div>
        </section>

        <section id="read" class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><span class="badge-info font-mono">GET</span><code class="font-mono text-[11.5px] text-slate-700 dark:text-slate-200">/api/v1/payments/{order_id}</code><span class="badge-info">payments:read</span></div>
            <p class="mt-3 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Returns the order and, when verified, the transaction UTR and receipt URL. Status is read from the local record, which is only updated by authoritative provider verification.</p>
            <div class="code-block mt-3"><pre><?= e($verifyExample) ?></pre></div>
        </section>

        <section id="list" class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><span class="badge-info font-mono">GET</span><code class="font-mono text-[11.5px] text-slate-700 dark:text-slate-200">/api/v1/payments</code><span class="badge-info">payments:read</span></div>
            <p class="mt-3 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Paginated list. Query parameters: <code class="font-mono text-[10px]">page</code>, <code class="font-mono text-[10px]">per_page</code> (max 100), <code class="font-mono text-[10px]">status</code>, <code class="font-mono text-[10px]">q</code>, <code class="font-mono text-[10px]">from</code>, <code class="font-mono text-[10px]">to</code> (YYYY-MM-DD).</p>
        </section>

        <section id="links" class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><span class="badge-success font-mono">POST</span><code class="font-mono text-[11.5px] text-slate-700 dark:text-slate-200">/api/v1/payment-links/create</code><span class="badge-info">links:write</span></div>
            <p class="mt-3 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Body: <code class="font-mono text-[10px]">title</code>, optional <code class="font-mono text-[10px]">description</code>, <code class="font-mono text-[10px]">amount</code> (omit for customer-entered), <code class="font-mono text-[10px]">min_amount</code>, <code class="font-mono text-[10px]">max_amount</code>, <code class="font-mono text-[10px]">success_redirect_url</code>, <code class="font-mono text-[10px]">expires_at</code>. Returns a hosted <code class="font-mono text-[10px]">/pay/{slug}</code> URL.</p>
            <div class="flex flex-wrap items-center gap-2 pt-4"><span class="badge-info font-mono">GET</span><code class="font-mono text-[11.5px] text-slate-700 dark:text-slate-200">/api/v1/payment-links</code><span class="badge-info">links:read</span></div>
        </section>

        <section id="webhooks" class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><span class="badge-success font-mono">POST</span><code class="font-mono text-[11.5px] text-slate-700 dark:text-slate-200">/api/v1/webhooks/test</code><span class="badge-info">webhooks:test</span></div>
            <p class="mt-3 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Sends one real signed test event to every active endpoint for your merchant. Signature is HMAC-SHA256 over the exact raw body, using the endpoint secret.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <?php foreach (['X-MAXXfam-Event' => 'Event name, e.g. payment.success', 'X-MAXXfam-Delivery' => 'Delivery ID for deduplication', 'X-MAXXfam-Timestamp' => 'Unix timestamp of the attempt', 'X-MAXXfam-Signature' => 'Hex HMAC-SHA256 of the raw body'] as $header => $note): ?>
                <div class="rounded-lg border border-slate-200 px-3 py-2.5 dark:border-white/10"><p class="font-mono text-[9.5px] font-semibold text-slate-700 dark:text-slate-200"><?= e($header) ?></p><p class="mt-0.5 text-[9.5px] text-slate-500"><?= e($note) ?></p></div>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Verifier example (PHP)</p>
            <div class="code-block mt-2"><pre><?= e($webhookExample) ?></pre></div>
            <p class="mt-3 text-[10.5px] leading-5 text-slate-500">Events: <span class="font-mono text-[9.5px]">payment.created, payment.pending, payment.success, payment.failed, payment.expired, webhook.test</span>. Failed deliveries retry with exponential backoff; inspect them under <a href="<?= e(url('/user/webhooks.php')) ?>" class="font-semibold text-brand-600 dark:text-brand-300">Webhooks</a>.</p>
        </section>

        <section id="verify" class="card card-pad">
            <h2 class="text-[15px] font-bold text-slate-900 dark:text-white">Verification rules</h2>
            <ul class="mt-3 space-y-2 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">
                <li class="flex gap-2.5"><i data-lucide="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500"></i> A payment becomes successful only after the provider's authoritative status endpoint confirms <code class="font-mono text-[10px]">success</code>.</li>
                <li class="flex gap-2.5"><i data-lucide="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500"></i> The provider order ID, payable amount and 12-digit UTR are validated server-side.</li>
                <li class="flex gap-2.5"><i data-lucide="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500"></i> A UTR can verify only one order; duplicate UTRs are blocked and logged as critical.</li>
                <li class="flex gap-2.5"><i data-lucide="x" class="mt-1 h-3.5 w-3.5 shrink-0 text-rose-500"></i> A webhook body is never trusted as proof. The server re-verifies with the provider before changing state.</li>
                <li class="flex gap-2.5"><i data-lucide="x" class="mt-1 h-3.5 w-3.5 shrink-0 text-rose-500"></i> The platform does not fabricate success for local testing, and there is no sandbox success toggle.</li>
            </ul>
        </section>

        <section id="errors" class="card card-pad">
            <h2 class="text-[15px] font-bold text-slate-900 dark:text-white">Errors & rate limits</h2>
            <p class="mt-2 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Errors use a consistent envelope. HTTP 429 includes <code class="font-mono text-[10px]">Retry-After</code>; the default limit is 60 requests per minute per API key.</p>
            <div class="code-block mt-3"><pre>{
  "success": false,
  "error": {
    "code": "integration_not_configured",
    "message": "Connect your provider API key before creating payments."
  }
}</pre></div>
            <div class="mt-4 overflow-x-auto"><table class="table-mf"><thead><tr><th>Code</th><th>HTTP</th><th>Meaning</th></tr></thead><tbody>
                <tr><td class="font-mono text-[10.5px]">unauthorized</td><td>401</td><td>Missing, revoked or invalid API key.</td></tr>
                <tr><td class="font-mono text-[10.5px]">insufficient_scope</td><td>403</td><td>Key lacks the required scope.</td></tr>
                <tr><td class="font-mono text-[10.5px]">validation_failed</td><td>422</td><td>Request body failed validation.</td></tr>
                <tr><td class="font-mono text-[10.5px]">integration_not_configured</td><td>409</td><td>No authorized provider credentials.</td></tr>
                <tr><td class="font-mono text-[10.5px]">rate_limited</td><td>429</td><td>Too many requests; honour Retry-After.</td></tr>
                <tr><td class="font-mono text-[10.5px]">provider_error</td><td>502</td><td>The provider rejected or failed the upstream call.</td></tr>
            </tbody></table></div>
        </section>

        <section id="idempotency" class="card card-pad">
            <h2 class="text-[15px] font-bold text-slate-900 dark:text-white">Idempotency</h2>
            <p class="mt-2 text-[11.5px] leading-6 text-slate-600 dark:text-slate-300">Send <code class="font-mono text-[10px]">Idempotency-Key</code> (8–64 characters) with <code class="font-mono text-[10px]">POST</code> requests. Replaying the same key and body returns the original response for 24 hours. Reusing a key with a different body returns <code class="font-mono text-[10px]">409 idempotency_conflict</code>.</p>
        </section>
    </div>
</div>
