<?php
use App\Core\Security;
?>
<section class="relative overflow-hidden py-24 sm:py-32">
    <div class="absolute inset-0 bg-grid opacity-40"></div>
    <div class="container-mf relative">
        <div class="mx-auto max-w-lg rounded-3xl border border-white/10 bg-ink-850/80 p-10 text-center shadow-float backdrop-blur">
            <span class="eyebrow">Error 419</span>
            <h1 class="mt-5 text-3xl font-bold text-white">Your session expired</h1>
            <p class="mt-3 text-sm leading-7 text-slate-400">The security token for this form is invalid or has expired. Refresh the page and submit the form again. No changes were made.</p>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                <a href="javascript:history.back()" class="btn-secondary">Go back</a>
                <a href="<?= e(url('/')) ?>" class="btn-primary">Return home</a>
            </div>
        </div>
    </div>
</section>
