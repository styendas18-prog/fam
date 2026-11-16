<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\TotpService;

$pending = Auth::pendingAdmin();
$current = $pending ? null : Auth::admin();
// A signed-in administrator with TOTP disabled may enroll optionally.
$optionalEnrollment = $current !== null && (int) $current['totp_enabled'] !== 1;
$subject = $pending ?? ($optionalEnrollment ? $current : null);

if (!$subject) {
    Flash::error('Please sign in with your administrator password first.');
    Response::redirect(url('/admin/login.php'));
}

$setup = (int) $subject['totp_enabled'] !== 1;
$secret = '';
$provisioningUri = '';

if ($setup) {
    $secret = (string) ($_SESSION['admin_2fa_secret'] ?? '');
    if ($secret === '' || strlen($secret) < 16) {
        $secret = TotpService::generateSecret();
        $_SESSION['admin_2fa_secret'] = $secret;
    }
    $provisioningUri = TotpService::provisioningUri($secret, (string) $subject['email'], 'MAXXfam Admin');
} else {
    try {
        $stored = (string) $subject['totp_secret'];
        // Support both encrypted (current) and legacy plaintext secrets.
        try {
            $secret = Crypto::decrypt($stored);
        } catch (Throwable) {
            $secret = $stored;
        }
    } catch (Throwable) {
        $secret = '';
    }
    if ($secret === '') {
        Flash::error('Your two-factor secret is unavailable. Contact the platform operator for CLI recovery.');
        Response::redirect(url($optionalEnrollment ? '/admin/dashboard.php' : '/admin/login.php'));
    }
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        RateLimiter::enforce('admin-2fa:' . $subject['id'] . ':' . Request::ip(), 10, 600, 'Too many verification attempts. Try again in a few minutes.');
        $code = preg_replace('/\D/', '', Request::str('code')) ?? '';
        if (!TotpService::verify($secret, $code)) {
            Audit::security('admin_2fa_failed', 'warning', ['admin_id' => $subject['id']], null, (int) $subject['id']);
            Flash::error('That code is not valid. Check your authenticator clock and try again.');
            Response::redirect(url('/admin/two-factor.php' . ($setup ? '?setup=1' : '')));
        }
        if ($setup) {
            Database::execute(
                'UPDATE admins SET totp_secret = ?, totp_enabled = 1 WHERE id = ?',
                [Crypto::encrypt($secret), $subject['id']]
            );
            Audit::security('admin_2fa_enrolled', 'info', [], null, (int) $subject['id']);
        }
        if ($pending) {
            $fresh = Database::fetch('SELECT * FROM admins WHERE id = ? AND status = "active" LIMIT 1', [$subject['id']]);
            if (!$fresh) {
                throw new RuntimeException('Administrator unavailable.');
            }
            Auth::completeAdminLogin($fresh);
            Flash::success('Administrator session started.');
            Response::redirect(url('/admin/dashboard.php'));
        }
        unset($_SESSION['admin_2fa_secret']);
        Flash::success('Two-factor authentication is now enabled for this account.');
        Response::redirect(url('/admin/dashboard.php'));
    } catch (Throwable $e) {
        Flash::error('Verification could not be completed. Start the sign-in flow again.');
        Response::redirect(url($optionalEnrollment ? '/admin/dashboard.php' : '/admin/login.php'));
    }
}

View::render('auth.admin-two-factor', [
    'title' => $setup ? ($optionalEnrollment ? 'Enable 2FA' : 'Set up 2FA') : 'Two-factor verification',
    'setup' => $setup,
    'secret' => $secret,
    'provisioningUri' => $provisioningUri,
    'optional' => $optionalEnrollment,
    'authHeading' => $setup ? 'Protect your admin account' : 'Enter your authenticator code',
    'authCopy' => $setup ? 'Scan the QR code with an authenticator app, then enter the current six-digit code to finish setup.' : 'Open your authenticator app and enter the current six-digit code.',
], 'auth');
