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
use App\Http\UserPage;
use App\Models\MerchantModel;
use App\Models\UserModel;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$userId = (int) $user['id'];

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action', 'business');

        if ($action === 'name') {
            $name = Request::str('name');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
                throw new HttpException(422, 'Enter your name between 2 and 120 characters.');
            }
            UserModel::updateName($userId, $name);
            Audit::security('profile_name_updated', 'info', [], $userId, null);
            Flash::success('Your name has been updated.');
            Response::redirect(url('/user/profile.php'));
        }

        if ($action === 'business') {
            $businessName = Request::str('business_name');
            if (mb_strlen($businessName) < 2 || mb_strlen($businessName) > 160) {
                throw new HttpException(422, 'Business name must be between 2 and 160 characters.');
            }
            $website = Request::str('website');
            if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
                throw new HttpException(422, 'Enter a valid website URL including https://.');
            }
            $supportEmail = strtolower(Request::str('support_email'));
            if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422, 'Enter a valid support email address.');
            }
            $phone = Request::str('support_phone');
            if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $phone)) {
                throw new HttpException(422, 'Enter a valid support phone number.');
            }
            MerchantModel::updateBusiness($merchantId, [
                'business_name' => $businessName,
                'website' => $website,
                'support_email' => $supportEmail,
                'support_phone' => $phone,
            ]);
            Audit::security('profile_business_updated', 'info', ['merchant_id' => $merchantId], $userId, null);
            Flash::success('Business details saved.');
            Response::redirect(url('/user/profile.php'));
        }

        if ($action === 'kyc') {
            $legalName = Request::str('legal_name');
            if (mb_strlen($legalName) < 2 || mb_strlen($legalName) > 190) {
                throw new HttpException(422, 'Enter the registered legal name.');
            }
            $pan = strtoupper(Request::str('pan'));
            if ($pan !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
                throw new HttpException(422, 'PAN must be in the format ABCDE1234F.');
            }
            $gstin = strtoupper(Request::str('gstin'));
            if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin)) {
                throw new HttpException(422, 'Enter a valid 15-character GSTIN.');
            }
            MerchantModel::updateProfile($merchantId, [
                'legal_name' => $legalName,
                'business_type' => Request::str('business_type'),
                'address_line1' => Request::str('address_line1'),
                'address_line2' => Request::str('address_line2'),
                'city' => Request::str('city'),
                'state' => Request::str('state'),
                'postal_code' => Request::str('postal_code'),
                'gstin' => $gstin,
                'pan' => $pan,
            ]);
            Audit::security('profile_kyc_updated', 'warning', ['merchant_id' => $merchantId], $userId, null);
            Flash::success('Business profile saved. KYC review is performed by the platform operator.');
            Response::redirect(url('/user/profile.php'));
        }

        throw new HttpException(422, 'Unknown profile action.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('Your profile could not be updated.');
    }
    Response::redirect(url('/user/profile.php'));
}

$merchant = MerchantModel::findByUser($userId) ?? $merchant;

View::render('user.profile', [
    'title' => 'Profile & business',
    'active' => 'profile',
    'pageTitle' => 'Profile & business',
    'pageSubtitle' => 'Your account, business identity and KYC details',
    'user' => $user,
    'merchant' => $merchant,
], 'dashboard');
