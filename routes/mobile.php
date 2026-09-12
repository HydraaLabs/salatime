<?php

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\PreferencesController;
use App\Http\Middleware\AuthenticateMobileAccount;
use App\Http\Middleware\MobileApiEnabled;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::get('auth/config', [AuthController::class, 'configuration']);
    Route::middleware(MobileApiEnabled::class)->group(function () {
        Route::prefix('auth')->group(function () {
            Route::middleware('throttle:mobile-login')->group(function () {
                Route::post('register', [AuthController::class, 'register']);
                Route::post('login', [AuthController::class, 'login']);
                Route::post('google', [AuthController::class, 'google']);
                Route::post('apple', [AuthController::class, 'apple']);
                Route::get('challenge', [AuthController::class, 'challenge']);
            });
            Route::post('apple/callback', [AuthController::class, 'appleCallback'])->middleware('throttle:mobile-login');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:mobile-email');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:mobile-code');
            Route::middleware(AuthenticateMobileAccount::class)->group(function () {
                Route::get('me', [AuthController::class, 'me']);
                Route::post('logout', [AuthController::class, 'logout']);
                Route::delete('account', [AuthController::class, 'destroy'])->middleware('throttle:mobile-login');
                Route::post('verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:mobile-code');
                Route::post('resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:mobile-email');
                Route::post('link/google', [AuthController::class, 'linkGoogle'])->middleware('throttle:mobile-login');
                Route::post('link/apple', [AuthController::class, 'linkApple'])->middleware('throttle:mobile-login');
            });
        });
        Route::middleware(AuthenticateMobileAccount::class)->group(function () {
            Route::get('preferences', [PreferencesController::class, 'show']);
            Route::put('preferences', [PreferencesController::class, 'update'])->middleware('throttle:mobile-preferences');
        });
    });
});
