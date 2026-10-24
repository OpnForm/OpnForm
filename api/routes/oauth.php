<?php

use App\Http\Middleware\RequireOAuthS256;
use App\Http\Middleware\SecureOAuthConsent;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

Route::prefix(config('passport.path', 'oauth'))
    ->as('passport.')
    ->group(function () {
        Route::post('/token', [AccessTokenController::class, 'issueToken'])
            ->middleware('throttle')
            ->name('token');

        Route::get('/authorize', [AuthorizationController::class, 'authorize'])
            ->middleware(['web', RequireOAuthS256::class, SecureOAuthConsent::class])
            ->name('authorizations.authorize');

        Route::middleware(['web', 'auth:web', SecureOAuthConsent::class])->group(function () {
            Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])
                ->name('authorizations.approve');
            Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])
                ->name('authorizations.deny');
        });
    });
