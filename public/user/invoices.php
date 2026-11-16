<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\View;
use App\Http\UserPage;
use App\Models\InvoiceModel;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$result = InvoiceModel::listForMerchant($merchantId, max(1, Request::int('page', 1)), 20);

View::render('user.invoices', [
    'title' => 'Invoices',
    'active' => 'invoices',
    'pageTitle' => 'Invoices',
    'pageSubtitle' => 'Receipts issued only for provider-verified payments',
    'merchant' => $merchant,
    'result' => $result,
], 'dashboard');
