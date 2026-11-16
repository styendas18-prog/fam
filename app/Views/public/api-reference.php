<?php
$title = 'API reference';
$nav = 'docs';
$eyebrow = 'API v1';
$heading = 'REST API reference.';
$copy = 'Authenticated JSON endpoints for payments, links, webhook tests and account profile. Base URL: ' . url('/api/v1') . '.';
require BASE_PATH . '/app/Views/public/_page-hero.php';

$endpoints = [
    ['POST', '/payments/create', 'payments:write', 'Create a provider-backed payment order.'],
    ['GET', '/payments/{order_id}', 'payments:read', 'Fetch one order and its verification result.'],
    ['GET', '/payments', 'payments:read', 'List orders with filters and pagination.'],
    ['POST', '/payment-links/create', 'links:write', 'Create a shareable payment link.'],
    ['GET', '/payment-links', 'links:read', 'List payment links.'],
    ['POST', '/webhooks/test', 'webhooks:test', 'Send a signed test delivery.'],
    ['GET', '/account/profile', 'profile:read', 'Read merchant and account profile.'],
];
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[260px_minmax(0,1fr)] lg:gap-14">
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Endpoints</p>
                    <div class="mt-3 space-y-1">
                        <?php foreach ($endpoints as [$method, $path]): ?>
                        <a href="#<?= e(strtolower($method) . preg_replace('/[^a-z0-9]+/i', '-', $path)) ?>" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-[10.5px] text-slate-400 hover:bg-white/5 hover:text-white">
                            <span class="w-9 shrink-0 rounded px-1 py-0.5 text-center font-mono text-[8px] font-bold <?= $method === 'POST' ? 'bg-emerald-400/10 text-emerald-300' : 'bg-sky-400/10 text-sky-300' ?>"><?= e($method) ?></span>
                            <span class="truncate font-mono"><?= e($path) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-4 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-4 text-[10.5px] leading-5 text-slate-500">
                    <p class="font-semibold text-white">Conventions</p>
                    <p class="mt-2">JSON in, JSON out. Timestamps ISO 8601. Amounts are INR decimal values. Errors include a stable <code class="text-brand-300">code</code>.</p>
                </div>
            </aside>

            <div class="min-w-0 max-w-4xl space-y-8">
                <div class="rounded-2xl border border-brand-400/20 bg-brand-500/[0.06] p-5">
                    <div class="flex flex-wrap items-center gap-2 text-[10.5px]">
                        <span class="rounded-md bg-emerald-500/15 px-2 py-1 font-mono font-bold text-emerald-300">Bearer</span>
                        <span class="text-slate-400">Authentication required on every endpoint.</span>
                    </div>
                    <code class="mt-3 block break-all rounded-lg border border-white/10 bg-black/25 px-3 py-2 font-mono text-[10.5px] text-slate-300">Authorization: Bearer mf_live_…</code>
                </div>

                <article id="post-payments-create" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-emerald-500/15 px-2 py-1 font-mono text-[9px] font-bold text-emerald-300">POST</span><code class="font-mono text-[12px] text-white">/api/v1/payments/create</code><span class="badge-info ml-auto">payments:write</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Creates a local order and a genuine provider payment session. Returns 201 on success. The order is Pending — never successful at creation.</p>
                    <h3 class="mt-4 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Body</h3>
                    <div class="mt-2 overflow-x-auto rounded-lg border border-white/[0.07]">
                        <table class="w-full text-left text-[10.5px]"><thead class="bg-white/[0.03] text-[9px] uppercase tracking-[0.1em] text-slate-500"><tr><th class="px-3 py-2">Field</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Required</th><th class="px-3 py-2">Description</th></tr></thead><tbody class="divide-y divide-white/[0.06]">
                        <?php foreach ([
                            ['amount', 'number', 'yes', 'Payment amount in INR.'],
                            ['customer_reference', 'string', 'no', 'Idempotency reference (max 120).'],
                            ['customer_name', 'string', 'no', 'Customer display name.'],
                            ['customer_email', 'email', 'no', 'Customer email.'],
                            ['customer_phone', 'string', 'no', 'Customer phone.'],
                            ['description', 'string', 'no', 'Order description (max 500).'],
                            ['success_redirect_url', 'https URL', 'no', 'Customer return URL after verification.'],
                        ] as [$f, $t, $r, $d]): ?>
                        <tr><td class="px-3 py-2 font-mono text-brand-300"><?= e($f) ?></td><td class="px-3 py-2 text-slate-400"><?= e($t) ?></td><td class="px-3 py-2 text-slate-500"><?= e($r) ?></td><td class="px-3 py-2 text-slate-500"><?= e($d) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>
                    <div class="code-block mt-4"><pre><code><span class="text-slate-500">// 201 Created</span>
{
  <span class="text-sky-300">"success"</span>: true,
  <span class="text-sky-300">"data"</span>: {
    <span class="text-sky-300">"order_id"</span>: <span class="text-emerald-300">"MF…"</span>,
    <span class="text-sky-300">"status"</span>: <span class="text-amber-200">"pending"</span>,
    <span class="text-sky-300">"amount"</span>: <span class="text-amber-200">"499.00"</span>,
    <span class="text-sky-300">"payable_amount"</span>: <span class="text-amber-200">"499.00"</span>,
    <span class="text-sky-300">"currency"</span>: <span class="text-emerald-300">"INR"</span>,
    <span class="text-sky-300">"qr_url"</span>: <span class="text-emerald-300">"https://…"</span>,
    <span class="text-sky-300">"checkout_url"</span>: <span class="text-emerald-300">"https://…"</span>,
    <span class="text-sky-300">"upi_intent"</span>: <span class="text-emerald-300">"upi://pay?…"</span>,
    <span class="text-sky-300">"expires_at"</span>: <span class="text-emerald-300">"2026-10-31T18:00:00+05:30"</span>
  }
}</code></pre></div>
                    <p class="mt-2 text-[10.5px] leading-5 text-slate-500">Errors: <code>integration_not_configured</code>, <code>provider_disabled</code>, <code>amount_out_of_range</code>, <code>merchant_inactive</code>.</p>
                </article>

                <article id="get-payments-order-id" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-sky-500/15 px-2 py-1 font-mono text-[9px] font-bold text-sky-300">GET</span><code class="font-mono text-[12px] text-white">/api/v1/payments/{order_id}</code><span class="badge-info ml-auto">payments:read</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Returns one order scoped to your merchant. On success it includes the transaction UTR, verified timestamp and receipt URL. If the order is pending and the provider is configured, the status endpoint performs a live verification check before answering.</p>
                    <div class="code-block mt-4"><pre><code>{
  <span class="text-sky-300">"success"</span>: true,
  <span class="text-sky-300">"data"</span>: {
    <span class="text-sky-300">"order_id"</span>: <span class="text-emerald-300">"MF…"</span>,
    <span class="text-sky-300">"status"</span>: <span class="text-emerald-300">"success"</span>,
    <span class="text-sky-300">"amount"</span>: <span class="text-amber-200">"499.00"</span>,
    <span class="text-sky-300">"transaction"</span>: {
      <span class="text-sky-300">"utr"</span>: <span class="text-emerald-300">"123456789012"</span>,
      <span class="text-sky-300">"verified_at"</span>: <span class="text-emerald-300">"2026-10-31T12:34:56+05:30"</span>,
      <span class="text-sky-300">"verification_source"</span>: <span class="text-emerald-300">"webhook"</span>
    },
    <span class="text-sky-300">"receipt_url"</span>: <span class="text-emerald-300">"https://…"</span>
  }
}</code></pre></div>
                </article>

                <article id="get-payments" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-sky-500/15 px-2 py-1 font-mono text-[9px] font-bold text-sky-300">GET</span><code class="font-mono text-[12px] text-white">/api/v1/payments</code><span class="badge-info ml-auto">payments:read</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Lists your orders, newest first. Query parameters: <code class="text-slate-300">status</code> (pending/success/failed/expired/cancelled), <code class="text-slate-300">q</code>, <code class="text-slate-300">from</code>, <code class="text-slate-300">to</code>, <code class="text-slate-300">page</code>, <code class="text-slate-300">per_page</code> (max 100).</p>
                </article>

                <article id="post-payment-links-create" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-emerald-500/15 px-2 py-1 font-mono text-[9px] font-bold text-emerald-300">POST</span><code class="font-mono text-[12px] text-white">/api/v1/payment-links/create</code><span class="badge-info ml-auto">links:write</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Creates a shareable link. Required: <code class="text-slate-300">title</code>. Optional: <code class="text-slate-300">amount</code> (omit for customer-entered amount), <code class="text-slate-300">description</code>, <code class="text-slate-300">min_amount</code>, <code class="text-slate-300">max_amount</code>, <code class="text-slate-300">expires_at</code>, <code class="text-slate-300">success_redirect_url</code>. The public URL is <code class="text-slate-300">/pay/{slug}</code>.</p>
                </article>

                <article id="get-payment-links" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-sky-500/15 px-2 py-1 font-mono text-[9px] font-bold text-sky-300">GET</span><code class="font-mono text-[12px] text-white">/api/v1/payment-links</code><span class="badge-info ml-auto">links:read</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Lists links with order count and verified volume. Filters: <code class="text-slate-300">status</code>, <code class="text-slate-300">q</code>, <code class="text-slate-300">page</code>, <code class="text-slate-300">per_page</code>.</p>
                </article>

                <article id="post-webhooks-test" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-emerald-500/15 px-2 py-1 font-mono text-[9px] font-bold text-emerald-300">POST</span><code class="font-mono text-[12px] text-white">/api/v1/webhooks/test</code><span class="badge-info ml-auto">webhooks:test</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Sends a signed <code class="text-slate-300">webhook.test</code> event to one of your active endpoints. Body: <code class="text-slate-300">{"webhook_id": 12}</code>. The response reports the HTTP status and latency of the real outbound request. It does not claim delivery if the endpoint failed.</p>
                </article>

                <article id="get-account-profile" class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-sky-500/15 px-2 py-1 font-mono text-[9px] font-bold text-sky-300">GET</span><code class="font-mono text-[12px] text-white">/api/v1/account/profile</code><span class="badge-info ml-auto">profile:read</span></div>
                    <p class="mt-3 text-[11.5px] leading-6 text-slate-400">Returns account name, email, merchant code, business name, status, KYC state and non-secret provider connection summaries. Credentials are never returned.</p>
                </article>

                <div class="rounded-2xl border border-white/[0.08] bg-[#050a15] p-5 sm:p-6">
                    <h2 class="text-[13px] font-bold text-white">Error format</h2>
                    <div class="code-block mt-3"><pre><code>{
  <span class="text-sky-300">"success"</span>: false,
  <span class="text-sky-300">"error"</span>: {
    <span class="text-sky-300">"code"</span>: <span class="text-rose-300">"validation_failed"</span>,
    <span class="text-sky-300">"message"</span>: <span class="text-slate-300">"The amount field is required."</span>,
    <span class="text-sky-300">"details"</span>: { <span class="text-sky-300">"amount"</span>: <span class="text-slate-300">"This field is required."</span> }
  }
}</code></pre></div>
                    <p class="mt-3 text-[10.5px] leading-5 text-slate-500">HTTP status: 200/201 success, 400 malformed JSON, 401 auth, 403 scope/forbidden, 404 not found, 409 conflict, 422 validation/provider rejection, 429 rate limit, 502 provider/network failure, 500 internal.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="<?= e(url('/docs')) ?>" class="btn-secondary btn-sm">Integration guide</a>
                    <a href="<?= e(url('/status')) ?>" class="btn-secondary btn-sm">System status</a>
                    <a href="<?= e(url('/register.php')) ?>" class="btn-primary btn-sm">Get API keys</a>
                </div>
            </div>
        </div>
    </div>
</section>
