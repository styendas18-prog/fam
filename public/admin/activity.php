<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\LogModel;

$admin = AdminPage::boot('activity.view');

$filters = [
    'q' => Request::str('q'),
];

$page = AdminPage::page();
$result = LogModel::adminActivity($filters, $page, 30);

View::render('admin.activity', [
    'title' => 'Admin activity',
    'active' => 'activity',
    'pageTitle' => 'Administrator activity audit',
    'pageSubtitle' => 'Immutable audit log of administrative modifications and privileged operations',
    'result' => $result,
    'filters' => $filters,
], 'admin');
