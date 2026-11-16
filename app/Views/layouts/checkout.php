<?php
use App\Core\Security;
use App\Core\Settings;

$nonce = Security::nonce();
$siteName = (string) Settings::get('site_name', 'MAXXfam');
$pageTitle = $title ?? 'Secure checkout';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#070d1c">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(asset('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script defer src="<?= e(asset('assets/vendor/lucide.min.js')) ?>"></script>
    <script defer src="<?= e(asset('assets/vendor/qrcode.min.js')) ?>"></script>
    <script defer src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <script defer src="<?= e(asset('assets/js/checkout.js')) ?>"></script>
</head>
<body class="min-h-screen bg-[#f4f7fb] text-slate-800 dark:bg-ink-950 dark:text-slate-200">
<?= $content ?>
<script nonce="<?= e($nonce) ?>"></script>
</body>
</html>
