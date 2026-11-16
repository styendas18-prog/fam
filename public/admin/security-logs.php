<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\LogModel;

$admin = AdminPage::boot('logs.view');

$filters = [
    'q' => Request::str('q'),
    'severity' => Request::str('severity'),
    'from' => Request::str('from'),
];

$page = AdminPage::page();
$result = LogModel::securityLogs($filters, $page, 30);

View::render('admin.security-logs', [
    'title' => 'Security logs',
    'active' => 'security-logs',
    'pageTitle' => 'Security audit logs',
    'pageSubtitle' => 'Tamper-evident logs of authentication, authorization, rate limiting & anomalies',
    'result' => $result,
    'filters' => $filters,
], 'admin');
