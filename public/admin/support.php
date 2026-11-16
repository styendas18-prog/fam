<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\SupportModel;

$admin = AdminPage::boot('support.manage');

$ticketId = Request::int('id');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $action = Request::str('action');
        $id = Request::int('id');
        $ticket = SupportModel::find($id);
        if (!$ticket) {
            throw new HttpException(404, 'Ticket not found.');
        }

        if ($action === 'reply') {
            $message = trim((string) Request::input('message', ''));
            if ($message === '') {
                throw new HttpException(422, 'Enter a reply message.');
            }
            SupportModel::reply($id, 'admin', (int) $admin['id'], $message);
            Audit::admin((int) $admin['id'], 'admin_ticket_replied', 'support_ticket', $id, [
                'ticket_number' => $ticket['ticket_number'],
            ]);
            Flash::success('Reply sent to ticket ' . $ticket['ticket_number']);
        } elseif ($action === 'status') {
            $newStatus = Request::str('status');
            if (in_array($newStatus, ['open', 'pending', 'resolved', 'closed'], true)) {
                Database::execute('UPDATE support_tickets SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$newStatus, $id]);
                Audit::admin((int) $admin['id'], 'admin_ticket_status_changed', 'support_ticket', $id, [
                    'old_status' => $ticket['status'],
                    'new_status' => $newStatus,
                ]);
                Flash::success('Ticket status changed to ' . ucfirst($newStatus));
            }
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Support action failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/support.php' . ($ticketId > 0 ? '?id=' . $ticketId : '')));
}

$filters = [
    'q' => Request::str('q'),
    'status' => Request::str('status'),
];

$page = AdminPage::page();
$result = SupportModel::listAll($filters, $page, 20);

$activeTicket = null;
$messages = [];
if ($ticketId > 0) {
    $activeTicket = SupportModel::find($ticketId);
    if ($activeTicket) {
        $messages = SupportModel::messages($ticketId);
    }
}

View::render('admin.support', [
    'title' => 'Support tickets',
    'active' => 'support',
    'pageTitle' => 'Support tickets',
    'pageSubtitle' => 'Merchant inquiries, technical support requests & incident tickets',
    'result' => $result,
    'activeTicket' => $activeTicket,
    'messages' => $messages,
    'filters' => $filters,
], 'admin');
