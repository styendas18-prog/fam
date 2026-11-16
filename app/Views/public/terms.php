<?php
$title = 'Terms of service';
$nav = '';
$eyebrow = 'Legal';
$heading = 'Terms of service';
$copy = 'The rules for using this MAXXfam installation. Version 1.0 — effective ' . date('d F Y') . '.';
require BASE_PATH . '/app/Views/public/_page-hero.php';
$supportEmail = (string) \App\Core\Settings::get('site_support_email', 'support@example.com');
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-10 lg:grid-cols-[.28fr_.72fr] lg:gap-16">
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <nav class="space-y-1 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-3 text-[11.5px]" aria-label="On this page">
                    <?php foreach ([
                        'service' => 'The service',
                        'accounts' => 'Accounts',
                        'providers' => 'Payment providers',
                        'obligations' => 'Merchant obligations',
                        'prohibited' => 'Prohibited use',
                        'fees' => 'Fees',
                        'liability' => 'Liability',
                        'termination' => 'Termination',
                        'law' => 'Governing terms',
                    ] as $anchor => $label): ?>
                    <a href="#<?= e($anchor) ?>" class="block rounded-lg px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <div class="max-w-3xl space-y-8 text-[12.5px] leading-7 text-slate-400">
                <div class="rounded-xl border border-amber-400/20 bg-amber-400/[0.05] p-5">
                    <p class="font-semibold text-amber-200">Important</p>
                    <p class="mt-1.5 text-[12px]">MAXXfam is independent payment software. It is not a bank, wallet, payment aggregator or authorized payment system operator, and it is not affiliated with FamPay, NPCI or any bank. You must use only payment provider accounts you are legally authorized to operate.</p>
                </div>

                <section id="service">
                    <h2 class="text-xl font-bold text-white">1. The service</h2>
                    <p class="mt-3">The software provides order management, QR/hosted checkout presentation, server-side verification, webhook delivery, API access, reporting and related dashboard features. The operator may modify, suspend or discontinue features with reasonable notice.</p>
                </section>

                <section id="accounts">
                    <h2 class="text-xl font-bold text-white">2. Accounts and security</h2>
                    <p class="mt-3">You must provide accurate information, keep credentials confidential, and notify support promptly of unauthorized access. You are responsible for activity under your account and API keys. Administrators should enable TOTP two-factor authentication.</p>
                </section>

                <section id="providers">
                    <h2 class="text-xl font-bold text-white">3. Payment providers and funds</h2>
                    <p class="mt-3">Payment processing is performed by third-party providers under your separate agreement with them. MAXXfam does not hold, route, guarantee or settle merchant funds. Provider availability, fees, limits, settlement timing and chargeback rules are controlled by the provider.</p>
                    <p class="mt-2">A payment is shown as successful only when the provider’s authoritative response confirms it. The operator is not responsible for provider outages, delayed settlement, UPI network failures or incorrect merchant configuration.</p>
                </section>

                <section id="obligations">
                    <h2 class="text-xl font-bold text-white">4. Merchant obligations</h2>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5">
                        <li>Use only authorized provider credentials and lawful business practices.</li>
                        <li>Provide accurate order descriptions, amounts and customer communications.</li>
                        <li>Honour refunds and customer rights as required by law and your provider terms.</li>
                        <li>Keep webhook secrets, API keys and admin credentials secure.</li>
                        <li>Do not alter verification logic to mark unverified orders successful.</li>
                    </ul>
                </section>

                <section id="prohibited">
                    <h2 class="text-xl font-bold text-white">5. Prohibited use</h2>
                    <p class="mt-3">You may not use the service for unlawful goods or services, fraud, money laundering, deceptive payment collection, unauthorized access, malware, denial-of-service activity, or any use that violates a provider’s rules. We may suspend accounts and preserve evidence where required by law.</p>
                </section>

                <section id="fees">
                    <h2 class="text-xl font-bold text-white">6. Fees and taxes</h2>
                    <p class="mt-3">Platform fees, if any, are shown on the pricing page or a separate order form. Provider fees, taxes and statutory charges are separate. You are responsible for your own tax compliance and records.</p>
                </section>

                <section id="liability">
                    <h2 class="text-xl font-bold text-white">7. Disclaimers and liability</h2>
                    <p class="mt-3">The software is provided on an “as is” and “as available” basis to the maximum extent permitted by law. The operator does not warrant uninterrupted or error-free operation. To the extent permitted, the operator’s aggregate liability is limited to the platform fees you paid in the three months before the claim. Nothing excludes liability that cannot legally be excluded.</p>
                </section>

                <section id="termination">
                    <h2 class="text-xl font-bold text-white">8. Suspension and termination</h2>
                    <p class="mt-3">You may stop using the service at any time and request account closure. We may suspend or terminate access for material breach, security risk, unlawful activity, non-payment or provider requirement. Verified transaction records may be retained as required by law.</p>
                </section>

                <section id="law">
                    <h2 class="text-xl font-bold text-white">9. Governing terms</h2>
                    <p class="mt-3">These terms are a template for this installation. The operator must insert its legal entity, jurisdiction and dispute process before production use. Questions: <a class="text-brand-300 hover:text-brand-200" href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</p>
                </section>
            </div>
        </div>
    </div>
</section>
