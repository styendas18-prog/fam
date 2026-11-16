<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Validator;
use App\Core\View;
use App\Http\UserPage;
use App\Models\UserModel;

[$user, $merchant] = UserPage::boot();
$userId = (int) $user['id'];

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action');

        if ($action === 'password') {
            $current = (string) Request::input('current_password', '');
            $new = (string) Request::input('new_password', '');
            $confirm = (string) Request::input('new_password_confirmation', '');
            $fresh = UserModel::find($userId);
            if (!$fresh || !password_verify($current, (string) $fresh['password_hash'])) {
                Audit::security('password_change_failed', 'warning', ['reason' => 'current_password'], $userId, null);
                throw new HttpException(422, 'Your current password is not correct.');
            }
            $weak = Validator::passwordStrong($new);
            if ($weak !== null) {
                throw new HttpException(422, $weak);
            }
            if ($new !== $confirm) {
                throw new HttpException(422, 'The new password confirmation does not match.');
            }
            if (password_verify($new, (string) $fresh['password_hash'])) {
                throw new HttpException(422, 'Choose a password different from your current one.');
            }
            UserModel::updatePassword($userId, Auth::hashPassword($new));
            UserModel::revokeOtherSessions($userId, Security::hashToken(session_id()));
            Audit::security('password_changed', 'warning', ['sessions_revoked' => true], $userId, null);
            Flash::success('Password updated. Other sessions have been signed out.');
            Response::redirect(url('/user/security.php'));
        }

        if ($action === 'revoke_sessions') {
            $count = UserModel::revokeOtherSessions($userId, Security::hashToken(session_id()));
            Audit::security('sessions_revoked', 'warning', ['count' => $count], $userId, null);
            Flash::success($count > 0 ? $count . ' other session(s) signed out.' : 'No other active sessions were found.');
            Response::redirect(url('/user/security.php'));
        }

        throw new HttpException(422, 'Unknown security action.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('The security operation could not be completed.');
    }
    Response::redirect(url('/user/security.php'));
}

$currentHash = Security::hashToken(session_id());
$sessions = UserModel::sessions($userId, 20);
$events = UserModel::securityEvents($userId, 15);

View::render('user.security', [
    'title' => 'Security',
    'active' => 'security',
    'pageTitle' => 'Security',
    'pageSubtitle' => 'Password, active sessions and account security events',
    'user' => $user,
    'merchant' => $merchant,
    'sessions' => $sessions,
    'events' => $events,
    'currentHash' => $currentHash,
], 'dashboard');
