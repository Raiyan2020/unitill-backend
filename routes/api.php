<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\BasketController;
use App\Http\Controllers\Api\Dashboard\AdminAuthController;
use App\Http\Controllers\Api\Dashboard\AdminController;
use App\Http\Controllers\Api\Dashboard\AdminUserController;
use App\Http\Controllers\Api\Dashboard\CategoryAdminController;
use App\Http\Controllers\Api\Dashboard\CityAdminController;
use App\Http\Controllers\Api\Dashboard\ContactReasonAdminController;
use App\Http\Controllers\Api\Dashboard\ContactUsAdminController;
use App\Http\Controllers\Api\Dashboard\CountryController;
use App\Http\Controllers\Api\Dashboard\LegalAffairController;
use App\Http\Controllers\Api\Dashboard\LanguageController;
use App\Http\Controllers\Api\Dashboard\PermissionController;
use App\Http\Controllers\Api\Dashboard\PaymentMethodAdminController;
use App\Http\Controllers\Api\Dashboard\RoleController;
use App\Models\FilterGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactReasonController;
use App\Http\Controllers\Api\ContactUsController;
use App\Http\Controllers\Api\UserController;


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



// Route::get('settings', SettingController::class);
// Route::get('city', CityController::class);
// Route::get('payment-method', PaymentMethodController::class);
// Route::post('contact-us' ,ContactUsController::class);

// Route::get('category', CategoryController::class);



Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login')->name('login');
    Route::post('verify-student-email', 'verifyStudentEmail');
    Route::post('resend-verification-email', 'resendVerificationEmail');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

Route::prefix('admin')->controller(AdminAuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::middleware('auth:sanctum')->post('logout', 'logout');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdminUserController::class)->group(function () {
    Route::get('users', 'index');
    Route::get('users/{id}', 'show');
    Route::put('users/{id}', 'update');
    Route::delete('users/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(AdminController::class)->group(function () {
    Route::get('admins', 'index');
    Route::post('admins', 'store');
    Route::get('admins/{id}', 'show');
    Route::put('admins/{id}', 'update');
    Route::delete('admins/{id}', 'destroy');
    Route::get('profile', 'profile');
    Route::put('profile', 'updateProfile');
    Route::put('profile/password', 'updatePassword');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(RoleController::class)->group(function () {
    Route::get('roles', 'index');
    Route::post('roles', 'store');
    Route::put('roles/{id}', 'update');
    Route::delete('roles/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(PermissionController::class)->group(function () {
    Route::get('permissions', 'index');
    Route::post('permissions', 'store');
    Route::put('permissions/{id}', 'update');
    Route::delete('permissions/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(CountryController::class)->group(function () {
    Route::get('countries', 'index');
    Route::post('countries', 'store');
    Route::put('countries/{id}', 'update');
    Route::delete('countries/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(CategoryAdminController::class)->group(function () {
    Route::get('categories', 'index');
    Route::get('categories/{id}/image', 'image');
    Route::post('categories', 'store');
    Route::put('categories/{id}', 'update');
    Route::delete('categories/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(CityAdminController::class)->group(function () {
    Route::get('cities', 'index');
    Route::post('cities', 'store');
    Route::put('cities/{id}', 'update');
    Route::delete('cities/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(PaymentMethodAdminController::class)->group(function () {
    Route::get('payment-methods', 'index');
    Route::post('payment-methods', 'store');
    Route::put('payment-methods/{id}', 'update');
    Route::delete('payment-methods/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(LanguageController::class)->group(function () {
    Route::get('languages', 'index');
    Route::post('languages', 'store');
    Route::put('languages/{id}', 'update');
    Route::delete('languages/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(LegalAffairController::class)->group(function () {
    Route::get('legal-affairs', 'index');
    Route::post('legal-affairs', 'store');
    Route::put('legal-affairs/{id}', 'update');
    Route::delete('legal-affairs/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(ContactReasonAdminController::class)->group(function () {
    Route::get('contact-reasons', 'index');
    Route::post('contact-reasons', 'store');
    Route::put('contact-reasons/{id}', 'update');
    Route::delete('contact-reasons/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->controller(ContactUsAdminController::class)->group(function () {
    Route::get('contact-us', 'index');
});

Route::get('contact-reasons', ContactReasonController::class);




Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    //profile
    Route::get('show-profile/{user_id?}', [UserController::class, 'show']);
    Route::post('update-profile', [UserController::class, 'update']);
    Route::post('contact-us', ContactUsController::class);
});

