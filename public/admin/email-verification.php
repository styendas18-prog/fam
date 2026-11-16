<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;
use App\Http\AdminPage;
use App\Services\EmailVerificationService;

$admin = AdminPage::boot('emailverify.view');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        if (!\App\Core\Auth::adminCan($admin, 'emailverify.manage')) {
            throw new HttpException(403, 'You do not have permission to manage email verification.');
        }
        $action = Request::str('action', '');

        if ($action === 'policy') {
            RateLimiter::enforce('admin-email-verify-policy:' . (int) $admin['id'], 20, 3600, 'Too many policy updates.');
            Settings::set('email_verification_enabled', Request::int('enabled', 0) === 1 ? '1' : '0', false, (int) $admin['id']);
            Settings::set('email_verification_default_senders', EmailVerificationService::normalizeSenders(Request::str('default_senders', EmailVerificationService::DEFAULT_SENDERS)), false, (int) $admin['id']);
            $lookback = max(1, min(168, Request::int('lookback_hours', 24)));
            Settings::set('email_verification_lookback_hours', (string) $lookback, false, (int) $admin['id']);
            $max = max(1, min(100, Request::int('max_per_run', 20)));
            Settings::set('email_verification_max_per_run', (string) $max, false, (int) $admin['id']);
            Settings::set('email_verification_require_sender_match', Request::bool('require_sender_match') ? '1' : '0', false, (int) $admin['id']);
            Settings::set('email_verification_require_upi_match', Request::bool('require_upi_match') ? '1' : '0', false, (int) $admin['id']);
            Audit::admin((int) $admin['id'], 'email_verification_policy_updated', 'system', null, [
                'enabled' => Request::int('enabled', 0),
                'lookback_hours' => $lookback,
                'max_per_run' => $max,
            ]);
            Flash::success('Email verification policy updated.');
        } elseif ($action === 'toggle') {
            $id = Request::int('account_id');
            $account = EmailVerificationService::findById($id);
            if (!$account) {
                throw new HttpException(404, 'Email verification account not found.');
            }
            $enabled = Request::int('is_enabled', 1) === 1;
            EmailVerificationService::setEnabled($id, $enabled);
            Audit::admin((int) $admin['id'], 'email_verification_account_toggled', 'email_verification_account', $id, ['enabled' => $enabled]);
            Flash::success('Account ' . ($enabled ? 'enabled' : 'disabled') . '.');
        } elseif ($action === 'test') {
            $id = Request::int('account_id');
            RateLimiter::enforce('admin-email-verify-test:' . (int) $admin['id'], 20, 600, 'Too many IMAP tests. Wait a few minutes.');
            $account = EmailVerificationService::findById($id);
            if (!$account) {
                throw new HttpException(404, 'Email verification account not found.');
            }
            $result = EmailVerificationService::test($account);
            Audit::admin((int) $admin['id'], 'email_verification_account_tested', 'email_verification_account', $id, [
                'connected' => $result['connected'],
                'message' => mb_substr((string) $result['message'], 0, 200),
            ]);
            if ($result['connected']) {
                Flash::success($result['message']);
            } else {
                Flash::error('IMAP test failed: ' . $result['message']);
            }
        } elseif ($action === 'scan') {
            $id = Request::int('account_id');
            RateLimiter::enforce('admin-email-verify-scan:' . (int) $admin['id'], 20, 600, 'Too many mailbox scans. Wait a few minutes.');
            $account = EmailVerificationService::findById($id);
            if (!$account) {
                throw new HttpException(404, 'Email verification account not found.');
            }
            $summary = EmailVerificationService::scanAccount($account);
            Audit::admin((int) $admin['id'], 'email_verification_account_scanned', 'email_verification_account', $id, [
                'checked' => (int) ($summary['checked'] ?? 0),
                'verified' => (int) ($summary['verified'] ?? 0),
                'error' => $summary['error'] ?? null,
            ]);
            if (!empty($summary['error'])) {
                Flash::error('Mailbox scan failed: ' . $summary['error']);
            } else {
                Flash::success(sprintf(
                    'Scanned %d message(s): %d verified, %d unmatched, %d ignored, %d duplicate(s), %d failed.',
                    (int) $summary['checked'],
                    (int) $summary['verified'],
                    (int) $summary['unmatched'],
                    (int) $summary['ignored'],
                    (int) $summary['duplicate'],
                    (int) $summary['failed']
                ));
            }
        } elseif ($action === 'delete') {
            $id = Request::int('account_id');
            $account = EmailVerificationService::findById($id);
            if (!$account) {
                throw new HttpException(404, 'Email verification account not found.');
            }
            EmailVerificationService::deleteAccount((int) $account['merchant_id']);
            Audit::admin((int) $admin['id'], 'email_verification_account_deleted', 'email_verification_account', $id, [
                'merchant_id' => (int) $account['merchant_id'],
                'gmail' => $account['gmail_address'],
            ]);
            Flash::success('Email verification account removed.');
        } else {
            throw new HttpException(422, 'Unknown action.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('Email verification operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/email-verification.php'));
}

$accounts = EmailVerificationService::allAccounts(false);
$stats = EmailVerificationService::stats();
$recent = EmailVerificationService::recentMessages(100);
$messageCount = (int) Database::scalar('SELECT COUNT(*) FROM email_verification_messages');
$verifiedCount = (int) Database::scalar("SELECT COUNT(*) FROM email_verification_messages WHERE status = 'verified'");

View::render('admin.email-verification', [
    'title' => 'Email verification',
    'active' => 'email-verification',
    'pageTitle' => 'Gmail IMAP email verification',
    'pageSubtitle' => 'Platform policy, merchant IMAP accounts, parsed FamPay alerts and real reconciliation state',
    'accounts' => $accounts,
    'stats' => $stats,
    'recent' => $recent,
    'messageCount' => $messageCount,
    'verifiedCount' => $verifiedCount,
    'policy' => [
        'enabled' => Settings::bool('email_verification_enabled', true),
        'default_senders' => (string) Settings::get('email_verification_default_senders', EmailVerificationService::DEFAULT_SENDERS),
        'lookback_hours' => Settings::int('email_verification_lookback_hours', 24),
        'max_per_run' => Settings::int('email_verification_max_per_run', 20),
        'require_sender_match' => Settings::bool('email_verification_require_sender_match', true),
        'require_upi_match' => Settings::bool('email_verification_require_upi_match', true),
    ],
    'canManage' => \App\Core\Auth::adminCan($admin, 'emailverify.manage'),
], 'admin');
