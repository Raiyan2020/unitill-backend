<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\FilterController;
use App\Http\Controllers\Admin\PostController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::prefix(LaravelLocalization::setLocale() . '/admin')->middleware(['web'])
    ->name('admin.')
    ->group(function () {
        Route::get('login', [AuthController::class, 'showLoginForm'])->name('login.form');
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:admin')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            // Logout
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            Route::resource('admins', AdminController::class);
            //users
            Route::get('users', [UsersController::class, 'index'])->name('users.index');
            Route::get('users/{id}/edit', [UsersController::class, 'edit'])->name('users.edit');
            Route::put('users/{id}', [UsersController::class, 'update'])->name('users.update');
            Route::delete('users/{id}', [UsersController::class, 'destroy'])->name('users.destroy');

            //cities
            Route::resource('cities', CityController::class);
            Route::post('/cities/sort', [CityController::class, 'sort'])->name('cities.sort');
            Route::post('/cities/update-sort', [CityController::class, 'updateSort'])
                ->name('cities.updateSort');

            // Settings
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::post('settings/update', [SettingsController::class, 'update'])->name('settings.update');

            //PaymentMethod
            Route::resource('payment-methods', PaymentMethodController::class);

            //FilterGroup
            Route::resource('filters', FilterController::class);

            //categories
            Route::resource('categories', CategoryController::class);
            Route::post('/categories/update-sort', [CategoryController::class, 'updateSort'])
                ->name('categories.updateSort');
            //post
            Route::resource('posts', PostController::class);


            //coupons
            Route::resource('coupons', \App\Http\Controllers\Admin\CouponController::class);

            //notifications
            Route::get('notifications', [\App\Http\Controllers\Admin\notificationsController::class, 'index'])->name('notifications.index');
            Route::get('notifications/create', [\App\Http\Controllers\Admin\notificationsController::class, 'create'])->name('notifications.create');
            Route::post('notifications', [\App\Http\Controllers\Admin\notificationsController::class, 'store'])->name('notifications.store');


        });
    });

