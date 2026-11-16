<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\View;
use App\Http\UserPage;

[$user, $merchant] = UserPage::boot();

View::render('user.documentation', [
    'title' => 'Documentation',
    'active' => 'docs',
    'pageTitle' => 'Documentation',
    'pageSubtitle' => 'Integration guide for the MAXXfam REST API',
    'merchant' => $merchant,
    'baseUrl' => url('/'),
], 'dashboard');
