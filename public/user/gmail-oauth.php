<?php
declare(strict_types=1);

/**
 * Gmail OAuth 2.0 callback for the merchant panel.
 *
 *   /user/gmail-oauth.php?action=start   → redirect to Google consent
 *   /user/gmail-oauth.php?code=...&state=...  → store the refresh token
 *
 * The App Password flow keeps working; OAuth is optional and useful where a
 * Workspace administrator has disabled App Passwords.
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Settings;
use App\Http\UserPage;
use App\Services\EmailVerificationService;
use App\Services\GmailOAuth;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$account = EmailVerificationService::accountForMerchant($merchantId);
$back = url('/user/integration.php');

$finish = static function (string $message, bool $ok) use ($back): never {
    if ($ok) {
        Flash::success($message);
    } else {
        Flash::error($message);
    }
    Response::redirect($back);
};

if (!Settings::bool('email_verification_enabled', true)) {
    $finish('Email verification is disabled by the platform administrator.', false);
}
if (!GmailOAuth::configured()) {
    $finish('Gmail OAuth is not configured on this server. Ask the administrator to set GMAIL_OAUTH_CLIENT_ID and GMAIL_OAUTH_CLIENT_SECRET, or use an App Password.', false);
}

if ((string) ($_GET['action'] ?? '') === 'start') {
    if (!$account) {
        $finish('Save your Gmail address and FamPay UPI ID first, then connect Google.', false);
    }
    $state = bin2hex(random_bytes(24));
    $_SESSION['gmail_oauth_state'] = [
        'state' => $state,
        'merchant_id' => $merchantId,
        'account_id' => (int) $account['id'],
        'created' => time(),
    ];
    Response::redirect(GmailOAuth::authUrl($state, (string) $account['gmail_address']));
}

if (isset($_GET['error'])) {
    $error = (string) $_GET['error'];
    $finish($error === 'access_denied'
        ? 'Google access was cancelled. Nothing changed.'
        : 'Google returned an error: ' . $error, false);
}

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
$pending = $_SESSION['gmail_oauth_state'] ?? null;
unset($_SESSION['gmail_oauth_state']);

try {
    if (!is_array($pending) || $state === '' || !hash_equals((string) ($pending['state'] ?? ''), $state)) {
        throw new HttpException(400, 'This Google sign-in link is invalid or was already used. Start again from the integration page.');
    }
    if (time() - (int) ($pending['created'] ?? 0) > 600) {
        throw new HttpException(400, 'The Google sign-in link expired. Start again from the integration page.');
    }
    if ((int) ($pending['merchant_id'] ?? 0) !== $merchantId) {
        throw new HttpException(403, 'This Google sign-in belongs to another merchant account.');
    }
    if ($code === '') {
        throw new HttpException(400, 'Google did not return an authorization code.');
    }

    $tokens = GmailOAuth::exchange($code);
    if (($tokens['refresh_token'] ?? '') === '') {
        throw new HttpException(409, 'Google did not return a refresh token. Remove the app under Google Account > Security > Third-party access, then connect again.');
    }

    $accountId = (int) ($pending['account_id'] ?? 0);
    $tokenEmail = (string) ($tokens['email'] ?? '');
    EmailVerificationService::setOauthTokens($accountId, $tokens['refresh_token'], '');

    Audit::security('gmail_oauth_connected', 'warning', [
        'account_id' => $accountId,
        'merchant_id' => $merchantId,
        'google_account' => $tokenEmail !== '' ? $tokenEmail : null,
    ], (int) $user['id'], null);

    $notice = 'Gmail OAuth 2.0 connected. Run "Test IMAP" to confirm the mailbox.';
    if ($tokenEmail !== '' && $account && strcasecmp($tokenEmail, (string) $account['gmail_address']) !== 0) {
        $notice .= ' Note: Google signed in as ' . $tokenEmail . ', which differs from the configured address ' . $account['gmail_address'] . '. Update the address if that is the wrong mailbox.';
    }
    $finish($notice, true);
} catch (HttpException $e) {
    Audit::security('gmail_oauth_failed', 'warning', [
        'merchant_id' => $merchantId,
        'message' => mb_substr($e->getMessage(), 0, 200),
    ], (int) $user['id'], null);
    $finish($e->getMessage(), false);
} catch (Throwable $e) {
    Audit::security('gmail_oauth_failed', 'warning', [
        'merchant_id' => $merchantId,
        'message' => mb_substr($e->getMessage(), 0, 200),
    ], (int) $user['id'], null);
    $finish('Gmail OAuth failed: ' . $e->getMessage(), false);
}
