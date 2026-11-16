<?php
$title = 'Privacy policy';
$nav = '';
$eyebrow = 'Legal';
$heading = 'Privacy policy';
$copy = 'How MAXXfam collects, uses and protects merchant and payment data. Version 1.0 — effective ' . date('d F Y') . '.';
require BASE_PATH . '/app/Views/public/_page-hero.php';
$supportEmail = (string) \App\Core\Settings::get('site_support_email', 'support@example.com');
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[.28fr_.72fr] lg:gap-16">
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <nav class="space-y-1 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-3 text-[11.5px]" aria-label="On this page">
                    <?php foreach ([
                        'data-we-collect' => 'Data we collect',
                        'why' => 'Why we use it',
                        'payments' => 'Payment data',
                        'retention' => 'Retention',
                        'security' => 'Security',
                        'rights' => 'Your rights',
                        'contact' => 'Contact',
                    ] as $anchor => $label): ?>
                    <a href="#<?= e($anchor) ?>" class="block rounded-lg px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <div class="max-w-3xl space-y-8 text-[12.5px] leading-7 text-slate-400">
                <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/[0.05] p-5">
                    <p class="font-semibold text-emerald-200">In plain language</p>
                    <p class="mt-1.5 text-[12px]">We run payment software. We need account details to operate it, payment data to verify transactions, and security logs to protect the service. We do not sell personal data, and we do not claim ownership of your merchant funds.</p>
                </div>

                <section id="data-we-collect">
                    <h2 class="text-xl font-bold text-white">1. Data we collect</h2>
                    <p class="mt-3">Account data: name, email address, hashed password, merchant business name, support contact details, and optional KYC/business profile fields you choose to provide.</p>
                    <p class="mt-2">Payment data: order references, amounts, customer name/email/phone when supplied by the merchant, provider order identifiers, UPI UTRs, sender names returned by the provider, status history and verification source.</p>
                    <p class="mt-2">Technical data: IP address, user agent, session records, security events, API request metadata (path, status, latency, request ID) and webhook delivery results.</p>
                </section>

                <section id="why">
                    <h2 class="text-xl font-bold text-white">2. Why we use it</h2>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5">
                        <li>To create orders and verify payments with the payment provider you authorize.</li>
                        <li>To authenticate users, administer accounts and prevent fraud, replay and abuse.</li>
                        <li>To provide transaction history, invoices, analytics, notifications and support.</li>
                        <li>To meet legal, accounting and security obligations that apply to the operator.</li>
                    </ul>
                </section>

                <section id="payments">
                    <h2 class="text-xl font-bold text-white">3. Payment data and third parties</h2>
                    <p class="mt-3">When you enable a payment provider, order and payment details are transmitted to that provider so it can create a payment request and report status. That provider processes data under its own terms and privacy policy. MAXXfam does not control the provider’s systems.</p>
                    <p class="mt-2">We do not store full card numbers, UPI PINs, bank passwords or wallet credentials. Provider API credentials are encrypted at rest using AES-256-GCM. Merchant API keys are stored as one-way SHA-256 hashes.</p>
                </section>

                <section id="retention">
                    <h2 class="text-xl font-bold text-white">4. Retention</h2>
                    <p class="mt-3">Account and transaction records are retained while the account is active and for the period needed for accounting, dispute resolution and legal compliance. Security and API logs are retained on a bounded schedule configured by the operator. Login attempt history is pruned automatically.</p>
                </section>

                <section id="security">
                    <h2 class="text-xl font-bold text-white">5. Security</h2>
                    <p class="mt-3">Controls include hashed passwords (Argon2id/bcrypt), CSRF protection, strict session cookies, session rotation, login rate limiting and lockout, role-based admin access, mandatory TOTP for administrators, encrypted provider credentials, API key hashing, unique UTR constraints, atomic transactions and SSRF-safe webhook delivery.</p>
                    <p class="mt-2">No system is perfectly secure. If you believe you have found a vulnerability, contact us privately and do not test against other merchants’ data.</p>
                </section>

                <section id="rights">
                    <h2 class="text-xl font-bold text-white">6. Your choices and rights</h2>
                    <p class="mt-3">You can update profile and business information in the dashboard, revoke API keys, rotate webhook secrets, disable webhooks, and request account deletion by contacting support. Some records may need to be retained where law or dispute handling requires it.</p>
                    <p class="mt-2">Depending on your jurisdiction, you may have rights to access, correct, export or delete personal data. We will respond within a reasonable period after verifying your identity.</p>
                </section>

                <section id="contact">
                    <h2 class="text-xl font-bold text-white">7. Contact</h2>
                    <p class="mt-3">Privacy questions: <a class="text-brand-300 hover:text-brand-200" href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>. Include your merchant code but never a full API key, provider credential or UPI PIN.</p>
                    <p class="mt-4 text-[11px] text-slate-500">This policy is a template for the operator of this installation. The operator must review it against applicable law and its actual data practices before going live.</p>
                </section>
            </div>
        </div>
    </div>
</section>
