<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\UserPage;
use App\Models\SupportModel;

[$user, $merchant] = UserPage::boot();
$userId = (int) $user['id'];
$merchantId = (int) $merchant['id'];
$ticketRef = max(0, Request::int('ticket', 0));

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action');

        if ($action === 'create') {
            RateLimiter::enforce('support-create:' . $userId, 5, 3600, 'Too many tickets. Please continue an existing conversation.');
            $subject = Request::str('subject');
            $category = Request::str('category', 'general');
            $priority = Request::str('priority', 'normal');
            $message = trim((string) Request::input('message', ''));
            if (mb_strlen($subject) < 3 || mb_strlen($subject) > 190) {
                throw new HttpException(422, 'Enter a subject between 3 and 190 characters.');
            }
            if (!in_array($category, ['general', 'integration', 'payment', 'account', 'security', 'billing'], true)) {
                $category = 'general';
            }
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
                $priority = 'normal';
            }
            if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
                throw new HttpException(422, 'Enter a message between 10 and 5000 characters.');
            }
            $ticket = SupportModel::create($userId, $merchantId, $subject, $category, $priority, $message);
            Flash::success('Ticket ' . $ticket['number'] . ' created. Our team will reply here and by email.');
            Response::redirect(url('/user/support.php?ticket=' . $ticket['id']));
        }

        if ($action === 'reply') {
            RateLimiter::enforce('support-reply:' . $userId, 20, 600, 'Too many replies. Please wait before sending again.');
            $ticket = SupportModel::findForUser(Request::int('ticket_id'), $userId);
            if (!$ticket) {
                throw new HttpException(404, 'Ticket not found.');
            }
            if (in_array($ticket['status'], ['closed'], true)) {
                throw new HttpException(422, 'This ticket is closed. Create a new ticket to reopen the conversation.');
            }
            $message = trim((string) Request::input('message', ''));
            if (mb_strlen($message) < 2 || mb_strlen($message) > 5000) {
                throw new HttpException(422, 'Enter a reply between 2 and 5000 characters.');
            }
            SupportModel::reply((int) $ticket['id'], 'user', $userId, $message);
            Flash::success('Reply sent.');
            Response::redirect(url('/user/support.php?ticket=' . $ticket['id']));
        }

        throw new HttpException(422, 'Unknown support action.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('The support request could not be completed.');
    }
    Response::redirect(url('/user/support.php' . ($ticketRef > 0 ? '?ticket=' . $ticketRef : '')));
}

$tickets = SupportModel::forUser($userId);
$activeTicket = null;
$messages = [];
if ($ticketRef > 0) {
    $activeTicket = SupportModel::findForUser($ticketRef, $userId);
    if ($activeTicket) {
        $messages = SupportModel::messages($ticketRef);
    }
}

View::render('user.support', [
    'title' => 'Support',
    'active' => 'support',
    'pageTitle' => 'Support',
    'pageSubtitle' => 'Real conversations with the platform team',
    'user' => $user,
    'merchant' => $merchant,
    'tickets' => $tickets,
    'activeTicket' => $activeTicket,
    'messages' => $messages,
], 'dashboard');
