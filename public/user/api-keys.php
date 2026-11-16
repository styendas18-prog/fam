<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\UserPage;
use App\Models\ApiKeyModel;
use App\Services\Mailer;
use Throwable;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action', 'create');
        if ($action === 'create') {
            RateLimiter::enforce('api-key-create:' . $merchantId, 10, 3600, 'Too many API keys created. Try again later.');
            $name = Request::str('name');
            if (mb_strlen($name) < 3 || mb_strlen($name) > 80) {
                throw new HttpException(422, 'Key name must be between 3 and 80 characters.');
            }
            $scopes = array_map('strval', (array) Request::input('scopes', []));
            $scopes = array_values(array_filter($scopes, [ApiKeyModel::class, 'scopesFor']));
            if (!$scopes) {
                throw new HttpException(422, 'Select at least one scope for this key.');
            }
            $created = ApiKeyModel::create($merchantId, $name, $scopes);
            Audit::security('api_key_created', 'warning', ['key_id' => $created['id'], 'name' => $name, 'scopes' => $scopes], (int) $user['id'], null);
            Mailer::queueApiKeyCreated($user, $name, $created['last4']);
            $_SESSION['_new_api_key'] = $created;
            Flash::success('API key created. Copy it now — it is shown once.');
            Response::redirect(url('/user/api-keys.php'));
        }

        $id = Request::int('id');
        $keys = ApiKeyModel::listForMerchant($merchantId);
        $key = null;
        foreach ($keys as $candidate) {
            if ((int) $candidate['id'] === $id) {
                $key = $candidate;
                break;
            }
        }
        if (!$key) {
            throw new HttpException(404, 'API key not found.');
        }
        if ($action === 'revoke') {
            ApiKeyModel::revoke($id, $merchantId);
            Audit::security('api_key_revoked', 'warning', ['key_id' => $id, 'name' => $key['name']], (int) $user['id'], null);
            Flash::success('API key revoked. Requests using it will now fail.');
        } elseif ($action === 'rotate') {
            $rotated = ApiKeyModel::rotate($id, $merchantId);
            Audit::security('api_key_rotated', 'warning', ['old_key_id' => $id, 'new_key_id' => $rotated['id']], (int) $user['id'], null);
            Mailer::queueApiKeyCreated($user, $key['name'], $rotated['last4']);
            $_SESSION['_new_api_key'] = $rotated;
            Flash::success('Key rotated. The old key is revoked; copy the replacement now.');
        } else {
            throw new HttpException(422, 'Unknown action.');
        }
        Response::redirect(url('/user/api-keys.php'));
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('The API key operation could not be completed.');
    }
    Response::redirect(url('/user/api-keys.php'));
}

$newKey = $_SESSION['_new_api_key'] ?? null;
unset($_SESSION['_new_api_key']);
$keys = ApiKeyModel::listForMerchant($merchantId);
$active = count(array_filter($keys, static fn (array $k): bool => $k['status'] === 'active'));

View::render('user.api-keys', [
    'title' => 'API keys',
    'active' => 'api-keys',
    'pageTitle' => 'API keys',
    'pageSubtitle' => 'Scoped, hashed server-to-server credentials',
    'merchant' => $merchant,
    'keys' => $keys,
    'activeCount' => $active,
    'newKey' => is_array($newKey) ? $newKey : null,
], 'dashboard');
