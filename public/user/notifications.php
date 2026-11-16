<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\UserPage;
use App\Models\NotificationModel;

[$user, $merchant] = UserPage::boot();
$userId = (int) $user['id'];

if (Request::isPost()) {
    Csrf::validateOrFail();
    $action = Request::str('action');
    if ($action === 'read') {
        NotificationModel::markRead(Request::int('id'), $userId);
        Flash::success('Notification marked as read.');
    } elseif ($action === 'read_all') {
        NotificationModel::markAllRead($userId);
        Flash::success('All notifications marked as read.');
    }
    Response::redirect(url('/user/notifications.php'));
}

$filter = Request::str('filter', 'all');
$notifications = NotificationModel::listForUser($userId, 100);
if ($filter === 'unread') {
    $notifications = array_values(array_filter($notifications, static fn (array $n): bool => $n['read_at'] === null));
}
$unread = NotificationModel::unreadCount($userId);

View::render('user.notifications', [
    'title' => 'Notifications',
    'active' => 'notifications',
    'pageTitle' => 'Notifications',
    'pageSubtitle' => 'Real events from your payment account',
    'merchant' => $merchant,
    'notifications' => $notifications,
    'filter' => $filter,
    'unread' => $unread,
], 'dashboard');
