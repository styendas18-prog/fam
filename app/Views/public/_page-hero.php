<?php
$eyebrow = $eyebrow ?? 'MAXXfam';
$heading = $heading ?? '';
$copy = $copy ?? '';
?>
<section class="relative overflow-hidden border-b border-white/[0.06] bg-[#050a15] pb-14 pt-16 sm:pb-16 sm:pt-20">
    <div class="absolute inset-0 bg-grid opacity-50"></div>
    <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-brand-600/15 blur-3xl"></div>
    <div class="container-mf relative">
        <span class="eyebrow"><?= e($eyebrow) ?></span>
        <h1 class="mt-5 max-w-3xl text-[36px] font-bold leading-[1.08] tracking-[-0.03em] text-white sm:text-[46px]"><?= e($heading) ?></h1>
        <p class="mt-4 max-w-2xl text-[14.5px] leading-7 text-slate-400"><?= e($copy) ?></p>
    </div>
</section>
