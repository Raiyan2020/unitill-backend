<?php

use App\Http\Controllers\Api\Dashboard\AdminAuthController;
use App\Http\Controllers\Api\Dashboard\AdminController;
use App\Http\Controllers\Api\Dashboard\AdAdminController;
use App\Http\Controllers\Api\Dashboard\AdReportAdminController;
use App\Http\Controllers\Api\Dashboard\AdminUserController;
use App\Http\Controllers\Api\Dashboard\CategoryAdminController;
use App\Http\Controllers\Api\Dashboard\CityAdminController;
use App\Http\Controllers\Api\Dashboard\ContactReasonAdminController;
use App\Http\Controllers\Api\Dashboard\ContactUsAdminController;
use App\Http\Controllers\Api\Dashboard\CountryController;
use App\Http\Controllers\Api\Dashboard\UniversityController;
use App\Http\Controllers\Api\Dashboard\LegalAffairController;
use App\Http\Controllers\Api\Dashboard\LanguageController;
use App\Http\Controllers\Api\Dashboard\PermissionController;
use App\Http\Controllers\Api\Dashboard\PaymentMethodAdminController;
use App\Http\Controllers\Api\Dashboard\RoleController;
use App\Http\Controllers\Api\Dashboard\AdminPushNotificationController;
use App\Http\Controllers\Api\Dashboard\AdminSettingController;
use App\Http\Controllers\Api\Dashboard\TrustedSellerApplicationAdminController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AccountSecurityController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\AdReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactReasonController;
use App\Http\Controllers\Api\ContactUsController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FcmController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LanguageController as AppLanguageController;
use App\Http\Controllers\Api\FavoritedController;
use App\Http\Controllers\Api\MyAdController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TrustedSellerApplicationController;
use App\Http\Controllers\Api\UserRatingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserDeviceController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\VehicleLookupController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



// Pusher private-channel auth for the mobile app (token-based, base url includes /api).
// Mirrors the default /broadcasting/auth but reachable under the API prefix.
Route::middleware('auth:sanctum')->post('broadcasting/auth', function (\Illuminate\Http\Request $request) {
    return \Illuminate\Support\Facades\Broadcast::auth($request);
});

Route::get('settings', SettingController::class);
Route::get('languages', AppLanguageController::class);
Route::get('home', HomeController::class);
Route::get('categories', CategoryController::class);
Route::get('cities', \App\Http\Controllers\Api\CityController::class);
Route::get('ads', [AdController::class, 'index']);
Route::get('ads/{id}', [AdController::class, 'show']);
Route::get('ad-report-reasons', [AdReportController::class, 'reasons']);
Route::get('legal-affairs', [\App\Http\Controllers\Api\LegalAffairController::class, 'index']);

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login')->name('login');
    Route::post('auth/refresh', 'refresh');
    Route::post('verify-student-email', 'verifyStudentEmail');
    Route::post('resend-verification-email', 'resendVerificationEmail');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

// V2 login: two-step OTP flow with a 30-day access token.
Route::prefix('v2')->controller(\App\Http\Controllers\Api\V2\AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('login/verify-otp', 'verifyOtp');
    Route::post('login/resend-otp', 'resendOtp');
    Route::post('auth/refresh', 'refresh');
});

Route::prefix('admin')->controller(AdminAuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::middleware('auth:sanctum')->post('logout', 'logout');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdminUserController::class)->group(function () {
    Route::get('users', 'index')->middleware('permission:users.view');
    Route::get('users/{id}', 'show')->middleware('permission:users.view');
    Route::get('users/{id}/devices', 'devices')->middleware('permission:users.view');
    Route::get('users/{id}/favorites', 'favorites')->middleware('permission:users.view');
    Route::put('users/{id}', 'update')->middleware('permission:users.update');
    Route::delete('users/{id}', 'destroy')->middleware('permission:users.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdAdminController::class)->group(function () {
    Route::get('ads', 'index')->middleware('permission:categories.view');
    Route::get('ads/{id}', 'show')->middleware('permission:categories.view');
    Route::put('ads/{id}', 'update')->middleware('permission:categories.update');
    Route::delete('ads/{id}', 'destroy')->middleware('permission:categories.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdReportAdminController::class)->group(function () {
    Route::get('ad-reports', 'index')->middleware('permission:ad_reports.view');
    Route::get('ad-reports/{id}', 'show')->middleware('permission:ad_reports.view');
    Route::put('ad-reports/{id}', 'update')->middleware('permission:ad_reports.update');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdminController::class)->group(function () {
    Route::get('dashboard/stats', 'dashboardStats')->middleware('permission:dashboard.view');
    Route::get('admins', 'index')->middleware('permission:admins.view');
    Route::post('admins', 'store')->middleware('permission:admins.create');
    Route::get('admins/{id}', 'show')->middleware('permission:admins.view');
    Route::put('admins/{id}', 'update')->middleware('permission:admins.update');
    Route::delete('admins/{id}', 'destroy')->middleware('permission:admins.delete');
    Route::get('profile', 'profile');
    Route::put('profile', 'updateProfile');
    Route::put('profile/password', 'updatePassword');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(RoleController::class)->group(function () {
    Route::get('roles', 'index')->middleware('permission:roles.view');
    Route::post('roles', 'store')->middleware('permission:roles.create');
    Route::put('roles/{id}', 'update')->middleware('permission:roles.update');
    Route::delete('roles/{id}', 'destroy')->middleware('permission:roles.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(PermissionController::class)->group(function () {
    Route::get('permissions', 'index')->middleware('permission:permissions.view');
    Route::post('permissions', 'store')->middleware('permission:permissions.create');
    Route::put('permissions/{id}', 'update')->middleware('permission:permissions.update');
    Route::delete('permissions/{id}', 'destroy')->middleware('permission:permissions.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(CountryController::class)->group(function () {
    Route::get('countries', 'index')->middleware('permission:countries.view');
    Route::post('countries', 'store')->middleware('permission:countries.create');
    Route::put('countries/{id}', 'update')->middleware('permission:countries.update');
    Route::delete('countries/{id}', 'destroy')->middleware('permission:countries.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(UniversityController::class)->group(function () {
    Route::get('universities', 'index')->middleware('permission:universities.view');
    Route::post('universities', 'store')->middleware('permission:universities.create');
    Route::put('universities/{id}', 'update')->middleware('permission:universities.update');
    Route::delete('universities/{id}', 'destroy')->middleware('permission:universities.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(CategoryAdminController::class)->group(function () {
    Route::get('categories', 'index')->middleware('permission:categories.view');
    Route::get('categories/{id}/image', 'image')->middleware('permission:categories.view');
    Route::post('categories', 'store')->middleware('permission:categories.create');
    Route::put('categories/{id}', 'update')->middleware('permission:categories.update');
    Route::delete('categories/{id}', 'destroy')->middleware('permission:categories.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(CityAdminController::class)->group(function () {
    Route::get('cities', 'index')->middleware('permission:cities.view');
    Route::post('cities', 'store')->middleware('permission:cities.create');
    Route::put('cities/{id}', 'update')->middleware('permission:cities.update');
    Route::delete('cities/{id}', 'destroy')->middleware('permission:cities.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(PaymentMethodAdminController::class)->group(function () {
    Route::get('payment-methods', 'index')->middleware('permission:payment_methods.view');
    Route::post('payment-methods', 'store')->middleware('permission:payment_methods.create');
    Route::put('payment-methods/{id}', 'update')->middleware('permission:payment_methods.update');
    Route::delete('payment-methods/{id}', 'destroy')->middleware('permission:payment_methods.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(LanguageController::class)->group(function () {
    Route::get('languages', 'index')->middleware('permission:languages.view');
    Route::post('languages', 'store')->middleware('permission:languages.create');
    Route::put('languages/{id}', 'update')->middleware('permission:languages.update');
    Route::delete('languages/{id}', 'destroy')->middleware('permission:languages.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(LegalAffairController::class)->group(function () {
    Route::get('legal-affairs', 'index')->middleware('permission:legal_affairs.view');
    Route::post('legal-affairs', 'store')->middleware('permission:legal_affairs.create');
    Route::put('legal-affairs/{id}', 'update')->middleware('permission:legal_affairs.update');
    Route::delete('legal-affairs/{id}', 'destroy')->middleware('permission:legal_affairs.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(ContactReasonAdminController::class)->group(function () {
    Route::get('contact-reasons', 'index')->middleware('permission:contact_reasons.view');
    Route::post('contact-reasons', 'store')->middleware('permission:contact_reasons.create');
    Route::put('contact-reasons/{id}', 'update')->middleware('permission:contact_reasons.update');
    Route::delete('contact-reasons/{id}', 'destroy')->middleware('permission:contact_reasons.delete');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(ContactUsAdminController::class)->group(function () {
    Route::get('contact-us', 'index')->middleware('permission:contact_us.view');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(TrustedSellerApplicationAdminController::class)->group(function () {
    Route::get('trusted-seller-applications/pending-count', 'pendingCount')->middleware('permission:users.view');
    Route::get('trusted-seller-applications', 'index')->middleware('permission:users.view');
    Route::get('trusted-seller-applications/{id}', 'show')->middleware('permission:users.view');
    Route::put('trusted-seller-applications/{id}', 'update')->middleware('permission:users.update');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdminPushNotificationController::class)->group(function () {
    Route::get('push-notifications/meta', 'meta')->middleware('permission:dashboard.view');
    Route::get('push-notifications', 'index')->middleware('permission:dashboard.view');
    Route::post('push-notifications', 'store')->middleware('permission:dashboard.view');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdminSettingController::class)->group(function () {
    Route::get('settings', 'index')->middleware('permission:dashboard.view');
    Route::put('settings', 'update')->middleware('permission:dashboard.view');
});

Route::get('contact-reasons', ContactReasonController::class);






Route::middleware('auth:sanctum')->group(function () {
    Route::post('ads/draft', [AdController::class, 'storeDraft']);
    Route::post('ads/{id}/images', [AdController::class, 'uploadImage']);
    Route::post('ads/{id}/publish', [AdController::class, 'publishDraft']);
    Route::post('ads', [AdController::class, 'store']);
    Route::post('ads/{id}/report', [AdReportController::class, 'store']);
    Route::get('my-ads', [MyAdController::class, 'index']);
    Route::get('my-orders', [\App\Http\Controllers\Api\OrderController::class, 'index']);
    Route::post('orders', [\App\Http\Controllers\Api\OrderController::class, 'store']);
    Route::get('my-ads/{id}/buyers', [MyAdController::class, 'buyers']);
    Route::post('my-ads/{id}/mark-sold', [MyAdController::class, 'markAsSold']);
    Route::post('my-ads/{id}/pause', [MyAdController::class, 'pause']);
    Route::post('my-ads/{id}/activate', [MyAdController::class, 'activate']);
    Route::delete('my-ads/{id}', [MyAdController::class, 'destroy']);
    Route::get('ratings/{user_id?}', [UserRatingController::class, 'index']);
    Route::post('ratings', [UserRatingController::class, 'store']);
    Route::get('favorites', [FavoritedController::class, 'index']);
    Route::post('favorites', [FavoritedController::class, 'store']);
    Route::delete('favorites/{ad_id}', [FavoritedController::class, 'destroy']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('auth/biometric-token', [AuthController::class, 'issueBiometricToken']);
    Route::post('auth/revoke-biometric', [AuthController::class, 'revokeBiometric']);
    Route::post('device-identifier', [UserDeviceController::class, 'storeIdentifier']);
    Route::post('fcm/register', [FcmController::class, 'register']);

    Route::controller(UserNotificationController::class)->prefix('notifications')->group(function () {
        Route::get('/', 'index');
        Route::get('unread-count', 'unreadCount');
        Route::post('read-all', 'markAllRead');
        Route::post('{id}/read', 'markRead');
        Route::delete('{id}', 'destroy');
    });

    //profile
    Route::get('show-profile/{user_id?}', [UserController::class, 'show']);
    Route::post('update-profile', [UserController::class, 'update']);
    // Self-service account deletion (App Store guideline 5.1.1(v)).
    // Requires an OTP sent to the personal email before it can run.
    Route::post('delete-account/send-otp', [UserController::class, 'sendDeletionOtp']);
    Route::delete('delete-account', [UserController::class, 'destroy']);
    // GDPR Subject Access Request — emails the user a copy of their data.
    Route::post('account/data-request', [UserController::class, 'requestDataExport']);
    Route::get('account-security', [AccountSecurityController::class, 'index']);
    Route::post('change-password', [AccountSecurityController::class, 'changePassword']);
    Route::post('logout-other-devices', [AccountSecurityController::class, 'logoutOtherDevices']);
    Route::get('trusted-seller-application', [TrustedSellerApplicationController::class, 'show']);
    Route::post('trusted-seller-application', [TrustedSellerApplicationController::class, 'store']);
    Route::post('contact-us', ContactUsController::class);


    Route::controller(ConversationController::class)->prefix('conversations')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::get('{id}/messages', 'messages');
        Route::post('{id}/messages', 'sendMessage');
        Route::post('{id}/read', 'markRead');
        Route::post('{id}/archive', 'archive');
        Route::post('{id}/unarchive', 'unarchive');
        Route::post('{id}/report', 'report');
        Route::delete('{id}', 'destroy');
    });

    Route::get('vehicles/lookup', [VehicleLookupController::class, 'lookup']);
    Route::get('/postcode/lookup', [App\Http\Controllers\Api\PostcodeController::class, 'lookup']);
});

