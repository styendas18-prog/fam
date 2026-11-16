<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\AnnouncementModel;

$admin = AdminPage::boot('announcements.manage');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $action = Request::str('action');

        if ($action === 'create') {
            $title = Request::str('title');
            $body = trim((string) Request::input('body', ''));
            $audience = Request::str('audience', 'all_users');
            if ($title === '' || mb_strlen($title) < 3) {
                throw new HttpException(422, 'Enter an announcement title (3+ chars).');
            }
            if ($body === '' || mb_strlen($body) < 5) {
                throw new HttpException(422, 'Enter announcement content (5+ chars).');
            }
            if (!in_array($audience, ['all_users', 'active_merchants'], true)) {
                $audience = 'all_users';
            }

            $id = AnnouncementModel::create((int) $admin['id'], $title, $body, $audience);
            Audit::admin((int) $admin['id'], 'admin_announcement_created', 'announcement', $id, [
                'title' => $title,
                'audience' => $audience,
            ]);
            Flash::success("Draft announcement '{$title}' created.");
        } elseif ($action === 'publish') {
            $id = Request::int('id');
            $recipients = AnnouncementModel::publish($id);
            Audit::admin((int) $admin['id'], 'admin_announcement_published', 'announcement', $id, [
                'recipients' => $recipients,
            ]);
            Flash::success("Announcement published and queued to {$recipients} active recipient(s).");
        } elseif ($action === 'delete') {
            $id = Request::int('id');
            AnnouncementModel::delete($id);
            Audit::admin((int) $admin['id'], 'admin_announcement_deleted', 'announcement', $id);
            Flash::success('Announcement deleted.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Announcement operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/announcements.php'));
}

$page = AdminPage::page();
$result = AnnouncementModel::listAll($page, 20);

View::render('admin.announcements', [
    'title' => 'Announcements',
    'active' => 'announcements',
    'pageTitle' => 'System announcements',
    'pageSubtitle' => 'Publish notices to merchant inboxes & email notifications',
    'result' => $result,
], 'admin');
