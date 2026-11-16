<?php
$kyc = $merchant['kyc_status'] ?? 'not_submitted';
$kycMeta = status_meta($kyc);
$states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
    'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
    'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir',
    'Ladakh', 'Puducherry', 'Chandigarh', 'Andaman and Nicobar Islands', 'Dadra and Nagar Haveli and Daman and Diu', 'Lakshadweep',
];
$businessTypes = ['proprietorship', 'partnership', 'llp', 'private_limited', 'public_limited', 'trust', 'society', 'individual'];
?>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
    <div class="space-y-4">
        <div class="card">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.06]">
                <div><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Business details</h2><p class="text-[10.5px] text-slate-500">Shown on payment links, checkout and receipts.</p></div>
                <span class="<?= e($kycMeta['class']) ?> self-start"><span class="h-1.5 w-1.5 rounded-full <?= e($kycMeta['dot']) ?>"></span> KYC <?= e(str_replace('_', ' ', $kyc)) ?></span>
            </div>
            <form method="post" action="<?= e(url('/user/profile.php')) ?>" class="space-y-4 p-5" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="business">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label for="business_name" class="label">Business name</label><input id="business_name" name="business_name" required minlength="2" maxlength="160" class="input" value="<?= e($merchant['business_name']) ?>"></div>
                    <div><label for="website" class="label">Website <span class="font-normal text-slate-400">(optional)</span></label><input id="website" name="website" type="url" maxlength="255" class="input" placeholder="https://example.com" value="<?= e($merchant['website'] ?? '') ?>"></div>
                    <div><label for="support_phone" class="label">Support phone <span class="font-normal text-slate-400">(optional)</span></label><input id="support_phone" name="support_phone" maxlength="30" class="input" placeholder="+91 98765 43210" value="<?= e($merchant['support_phone'] ?? '') ?>"></div>
                    <div class="sm:col-span-2"><label for="support_email" class="label">Support email <span class="font-normal text-slate-400">(optional)</span></label><input id="support_email" name="support_email" type="email" maxlength="190" class="input" placeholder="help@example.com" value="<?= e($merchant['support_email'] ?? '') ?>"><p class="hint">Customers see this on the hosted checkout. Leave blank to use the platform support address.</p></div>
                </div>
                <div class="flex justify-end"><button type="submit" class="btn-primary btn-sm"><i data-lucide="save" class="h-3.5 w-3.5"></i> Save business details</button></div>
            </form>
        </div>

        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[13px] font-bold text-slate-900 dark:text-white">Registered identity & KYC</h2><p class="text-[10.5px] text-slate-500">KYC verification is only marked verified after the platform operator reviews real documents.</p></div>
            <form method="post" action="<?= e(url('/user/profile.php')) ?>" class="space-y-4 p-5" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="kyc">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="legal_name" class="label">Registered legal name</label><input id="legal_name" name="legal_name" required minlength="2" maxlength="190" class="input" value="<?= e($merchant['legal_name'] ?? $merchant['business_name']) ?>"></div>
                    <div><label for="business_type" class="label">Business type</label><select id="business_type" name="business_type" class="input"><option value="">Select type</option><?php foreach ($businessTypes as $type): ?><option value="<?= e($type) ?>" <?= ($merchant['business_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></div>
                    <div class="sm:col-span-2"><label for="address_line1" class="label">Address line 1</label><input id="address_line1" name="address_line1" maxlength="190" class="input" value="<?= e($merchant['address_line1'] ?? '') ?>"></div>
                    <div class="sm:col-span-2"><label for="address_line2" class="label">Address line 2 <span class="font-normal text-slate-400">(optional)</span></label><input id="address_line2" name="address_line2" maxlength="190" class="input" value="<?= e($merchant['address_line2'] ?? '') ?>"></div>
                    <div><label for="city" class="label">City</label><input id="city" name="city" maxlength="100" class="input" value="<?= e($merchant['city'] ?? '') ?>"></div>
                    <div><label for="state" class="label">State</label><select id="state" name="state" class="input"><option value="">Select state</option><?php foreach ($states as $state): ?><option value="<?= e($state) ?>" <?= ($merchant['state'] ?? '') === $state ? 'selected' : '' ?>><?= e($state) ?></option><?php endforeach; ?></select></div>
                    <div><label for="postal_code" class="label">PIN code</label><input id="postal_code" name="postal_code" maxlength="20" class="input" value="<?= e($merchant['postal_code'] ?? '') ?>"></div>
                    <div><label for="pan" class="label">PAN</label><input id="pan" name="pan" maxlength="10" class="input font-mono uppercase" placeholder="ABCDE1234F" value="<?= e($merchant['pan'] ?? '') ?>"></div>
                    <div><label for="gstin" class="label">GSTIN <span class="font-normal text-slate-400">(optional)</span></label><input id="gstin" name="gstin" maxlength="15" class="input font-mono uppercase" placeholder="22ABCDE1234F1Z5" value="<?= e($merchant['gstin'] ?? '') ?>"></div>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-400/20 dark:bg-amber-400/[0.06]"><p class="text-[10.5px] leading-5 text-amber-800 dark:text-amber-200/80">Submitting these details does not grant KYC approval. The platform operator reviews them manually; the status remains <?= e(str_replace('_', ' ', $kyc)) ?> until then.</p></div>
                <div class="flex justify-end"><button type="submit" class="btn-primary btn-sm"><i data-lucide="save" class="h-3.5 w-3.5"></i> Save business profile</button></div>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]"><h2 class="text-[12.5px] font-bold text-slate-900 dark:text-white">Account</h2></div>
            <form method="post" action="<?= e(url('/user/profile.php')) ?>" class="space-y-4 p-5" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="name">
                <div><label for="name" class="label">Full name</label><input id="name" name="name" required minlength="2" maxlength="120" class="input" value="<?= e($user['name']) ?>"></div>
                <div><label class="label">Email address</label><input class="input bg-slate-50 dark:bg-white/[0.02]" value="<?= e($user['email']) ?>" disabled><p class="hint">Contact support to change the sign-in email on this account.</p></div>
                <button type="submit" class="btn-secondary btn-sm w-full justify-center"><i data-lucide="user-round" class="h-3.5 w-3.5"></i> Update name</button>
            </form>
        </div>
        <div class="card p-5">
            <h2 class="text-[12px] font-bold text-slate-900 dark:text-white">Merchant identifiers</h2>
            <dl class="mt-3 space-y-2.5 text-[11px]">
                <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Merchant code</dt><dd class="font-mono font-semibold text-slate-700 dark:text-slate-200"><?= e($merchant['merchant_code']) ?></dd></div>
                <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Account status</dt><dd><?php $m = status_meta((string) $merchant['status']); ?><span class="<?= e($m['class']) ?>"><?= e($m['label']) ?></span></dd></div>
                <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Member since</dt><dd class="text-slate-700 dark:text-slate-200"><?= e(ist($merchant['created_at'], 'd M Y')) ?></dd></div>
                <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Country</dt><dd class="text-slate-700 dark:text-slate-200"><?= e($merchant['country']) ?></dd></div>
            </dl>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-800">
            <p class="flex items-center gap-2 text-[11px] font-semibold text-slate-700 dark:text-slate-200"><i data-lucide="shield-check" class="h-4 w-4 text-emerald-500"></i> Data accuracy</p>
            <p class="mt-1.5 text-[10px] leading-5 text-slate-500">Your legal name and PAN must match your bank account. Mismatched details can cause provider settlement failures that the platform cannot fix.</p>
        </div>
    </div>
</div>
