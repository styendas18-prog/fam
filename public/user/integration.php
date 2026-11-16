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
use App\Http\UserPage;
use App\Services\EmailVerificationService;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action', 'email_save');

        if (!str_starts_with($action, 'email_')) {
            throw new HttpException(422, 'Unknown action.');
        }
        if (!Settings::bool('email_verification_enabled', true)) {
            throw new HttpException(409, 'Email verification is disabled by the platform administrator.');
        }

        if ($action === 'email_save') {
            RateLimiter::enforce('email-verify-save:' . $merchantId, 10, 3600, 'Too many email verification updates.');
            $account = EmailVerificationService::saveAccount($merchantId, [
                'gmail_address' => Request::str('gmail_address', ''),
                'app_password' => Request::str('app_password', ''),
                'upi_id' => Request::str('upi_id', ''),
            ]);
            Audit::security('email_verification_saved', 'warning', [
                'account_id' => (int) ($account['id'] ?? 0),
                'gmail' => $account['gmail_address'] ?? null,
                'upi_id' => $account['upi_id'] ?? null,
            ], (int) $user['id'], null);
            Flash::success('Gmail IMAP account saved. The App Password is encrypted; run a connection test to confirm it works.');
        } elseif ($action === 'email_test') {
            RateLimiter::enforce('email-verify-test:' . $merchantId, 6, 300, 'Too many IMAP tests. Wait a few minutes.');
            $account = EmailVerificationService::accountForMerchant($merchantId);
            if (!$account) {
                throw new HttpException(409, 'Save a Gmail IMAP account before testing.');
            }
            $result = EmailVerificationService::test($account);
            if ($result['connected']) {
                Flash::success($result['message']);
            } else {
                Flash::error('IMAP test failed: ' . $result['message'] . ' No connected status was recorded.');
            }
        } elseif ($action === 'email_scan') {
            RateLimiter::enforce('email-verify-scan:' . $merchantId, 12, 600, 'Too many mailbox scans. Wait a few minutes.');
            $account = EmailVerificationService::accountForMerchant($merchantId);
            if (!$account) {
                throw new HttpException(409, 'Save a Gmail IMAP account before scanning.');
            }
            $summary = EmailVerificationService::scanAccount($account);
            if (!empty($summary['error'])) {
                Flash::error('Mailbox scan failed: ' . $summary['error']);
            } else {
                Flash::success(sprintf(
                    'Mailbox scanned: %d message(s) processed, %d verified, %d unmatched, %d ignored, %d duplicate(s), %d failed.',
                    (int) $summary['checked'],
                    (int) $summary['verified'],
                    (int) $summary['unmatched'],
                    (int) $summary['ignored'],
                    (int) $summary['duplicate'],
                    (int) $summary['failed']
                ));
                Audit::security('email_verification_scanned', 'info', [
                    'account_id' => (int) $account['id'],
                    'checked' => (int) $summary['checked'],
                    'verified' => (int) $summary['verified'],
                ], (int) $user['id'], null);
            }
        } elseif ($action === 'email_toggle') {
            $account = EmailVerificationService::accountForMerchant($merchantId);
            if (!$account) {
                throw new HttpException(409, 'No Gmail IMAP account is configured.');
            }
            $enabled = Request::int('is_enabled', 1) === 1;
            EmailVerificationService::setEnabled((int) $account['id'], $enabled);
            Audit::security('email_verification_toggled', 'warning', ['account_id' => (int) $account['id'], 'enabled' => $enabled], (int) $user['id'], null);
            Flash::success($enabled ? 'Email verification enabled.' : 'Email verification disabled. No mailbox scans will run.');
        } elseif ($action === 'email_remove') {
            EmailVerificationService::deleteAccount($merchantId);
            Audit::security('email_verification_removed', 'warning', ['merchant_id' => $merchantId], (int) $user['id'], null);
            Flash::success('Gmail IMAP account removed.');
        } else {
            throw new HttpException(422, 'Unknown email verification action.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('Integration operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/user/integration.php'));
}

$emailAccount = EmailVerificationService::accountForMerchant($merchantId);
$emailRecent = $emailAccount
    ? Database::fetchAll(
        'SELECT * FROM email_verification_messages WHERE account_id = ? ORDER BY id DESC LIMIT 10',
        [(int) $emailAccount['id']]
    )
    : [];

View::render('user.integration', [
    'title' => 'Integration',
    'active' => 'integration',
    'pageTitle' => 'Integration',
    'pageSubtitle' => 'Automated payment verification via Gmail IMAP',
    'merchant' => $merchant,
    'emailAccount' => $emailAccount,
    'emailRecent' => $emailRecent,
    'emailVerificationEnabled' => Settings::bool('email_verification_enabled', true),
], 'dashboard');
