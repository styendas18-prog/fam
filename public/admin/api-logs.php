<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\ApiLogModel;

$admin = AdminPage::boot('logs.view');

$filters = [
    'q' => Request::str('q'),
    'status' => Request::str('status'),
    'from' => Request::str('from'),
    'to' => Request::str('to'),
];

$page = AdminPage::page();
$result = ApiLogModel::listAll($filters, $page, 30);

View::render('admin.api-logs', [
    'title' => 'API logs',
    'active' => 'api-logs',
    'pageTitle' => 'Developer API request logs',
    'pageSubtitle' => 'Audit stream of inbound REST API calls, status codes and response latency',
    'result' => $result,
    'filters' => $filters,
], 'admin');
