<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\AnalyticsModel;
use App\Models\LogModel;
use App\Models\MerchantModel;
use App\Models\PaymentOrderModel;
use App\Models\ProviderModel;

$admin = AdminPage::boot('dashboard');
$stats = MerchantModel::adminStats();
$series = AnalyticsModel::adminSeries(14);
$security = LogModel::securitySummary();
$providers = ProviderModel::all();
$recentOrders = PaymentOrderModel::listAll([], 1, 8);
$recentActivity = LogModel::adminActivity([], 1, 8);
$email = LogModel::emailStats();
$verifiedToday = Database::fetch(
    'SELECT COUNT(*) AS count, COALESCE(SUM(amount),0) AS volume FROM transactions WHERE verified_at >= UTC_DATE()'
) ?: ['count' => 0, 'volume' => 0];
$failedWebhooks = (int) (Database::scalar("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'failed'") ?: 0);
$pendingTickets = (int) (Database::scalar("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending')") ?: 0);
$cron = [
    ['Expire orders', 'cron/expire_orders.php', (string) (Database::scalar("SELECT MAX(created_at) FROM admin_activity_logs WHERE action = 'cron_expire_orders'") ?: '')],
    ['Verify payments', 'cron/verify_payments.php', (string) (Database::scalar("SELECT MAX(created_at) FROM admin_activity_logs WHERE action = 'cron_verify_payments'") ?: '')],
    ['Retry webhooks', 'cron/retry_webhooks.php', (string) (Database::scalar("SELECT MAX(created_at) FROM admin_activity_logs WHERE action = 'cron_retry_webhooks'") ?: '')],
    ['Send email', 'cron/process_email.php', (string) (Database::scalar("SELECT MAX(created_at) FROM admin_activity_logs WHERE action = 'cron_process_email'") ?: '')],
];

View::render('admin.dashboard', [
    'title' => 'Dashboard',
    'active' => 'dashboard',
    'pageTitle' => 'Platform dashboard',
    'pageSubtitle' => 'Live operational data — no demo statistics',
    'useCharts' => true,
    'stats' => $stats,
    'series' => $series,
    'security' => $security,
    'providers' => $providers,
    'recentOrders' => $recentOrders['rows'],
    'recentActivity' => $recentActivity['rows'],
    'email' => $email,
    'verifiedToday' => $verifiedToday,
    'failedWebhooks' => $failedWebhooks,
    'pendingTickets' => $pendingTickets,
    'cron' => $cron,
], 'admin');
