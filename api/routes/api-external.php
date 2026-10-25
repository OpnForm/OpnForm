<?php

/**
 * External API calls
 */

use App\Http\Controllers\Integrations\Zapier;
use App\Http\Controllers\Integrations\Zapier\ListFormsController;
use App\Http\Controllers\Integrations\Zapier\ListWorkspacesController;
use Illuminate\Support\Facades\Route;

Route::prefix('external')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('zapier')->name('zapier.')->group(function () {
            Route::get('validate', Zapier\ValidateAuthController::class)
                ->name('validate');

            // Set and delete webhooks / manage integrations
            Route::middleware('ability:manage-integrations')
                ->name('webhooks.')
                ->group(function () {
                    Route::post('webhook', [Zapier\IntegrationController::class, 'store'])
                        ->name('store');

                    Route::delete('webhook', [Zapier\IntegrationController::class, 'destroy'])
                        ->name('destroy');
                    Route::get('submissions/recent', [Zapier\IntegrationController::class, 'poll'])->name('poll');
                });

            Route::get('workspaces', ListWorkspacesController::class)
                ->middleware('ability:workspaces-read')
                ->name('workspaces');

            Route::get('forms', ListFormsController::class)
                ->middleware('ability:forms-read')
                ->name('forms');
        });
    });

// Internal managed-cloud support routes. No wildcard token authorization.
Route::prefix('external/admin/v1')->middleware(['auth:sanctum', 'moderator'])->name('admin-api.')->group(function () {
    Route::get('users/{identifier}', [\App\Http\Controllers\Admin\AdminController::class, 'fetchUser'])->defaults('admin_scope', 'admin:users:read');
    Route::get('users/{user}/billing/customer', [\App\Http\Controllers\Admin\BillingController::class, 'getCustomer'])->defaults('admin_scope', 'admin:billing:read');
    Route::get('users/{user}/billing/subscriptions', [\App\Http\Controllers\Admin\BillingController::class, 'getSubscriptions'])->defaults('admin_scope', 'admin:billing:read');
    Route::get('users/{user}/billing/payments', [\App\Http\Controllers\Admin\BillingController::class, 'getPayments'])->defaults('admin_scope', 'admin:billing:read');
    Route::get('users/{user}/deleted-forms', [\App\Http\Controllers\Admin\FormController::class, 'getDeletedForms'])->defaults('admin_scope', 'admin:forms:read');
    Route::post('actions/block-user', [\App\Http\Controllers\Admin\AdminController::class, 'blockUser'])->defaults('operation', 'block-user')->defaults('admin_scope', 'admin:users:block')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/block-user/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'block-user')->defaults('admin_scope', 'admin:users:block');
    Route::post('actions/unblock-user', [\App\Http\Controllers\Admin\AdminController::class, 'unblockUser'])->defaults('operation', 'unblock-user')->defaults('admin_scope', 'admin:users:unblock')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/unblock-user/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'unblock-user')->defaults('admin_scope', 'admin:users:unblock');
    Route::post('actions/send-password-reset-email', [\App\Http\Controllers\Admin\AdminController::class, 'sendPasswordResetEmail'])->defaults('operation', 'send-password-reset-email')->defaults('admin_scope', 'admin:users:password-reset')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/send-password-reset-email/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'send-password-reset-email')->defaults('admin_scope', 'admin:users:password-reset');
    Route::post('actions/disable-two-factor-authentication', [\App\Http\Controllers\Admin\AdminController::class, 'disableTwoFactorAuthentication'])->defaults('operation', 'disable-two-factor-authentication')->defaults('admin_scope', 'admin:users:disable-2fa')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/disable-two-factor-authentication/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'disable-two-factor-authentication')->defaults('admin_scope', 'admin:users:disable-2fa');
    Route::post('actions/clear-user-cache', [\App\Http\Controllers\Admin\AdminController::class, 'clearUserCache'])->defaults('operation', 'clear-user-cache')->defaults('admin_scope', 'admin:users:clear-cache')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/clear-user-cache/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'clear-user-cache')->defaults('admin_scope', 'admin:users:clear-cache');
    Route::post('actions/apply-discount', [\App\Http\Controllers\Admin\AdminController::class, 'applyDiscount'])->defaults('operation', 'apply-discount')->defaults('admin_scope', 'admin:billing:discount')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/apply-discount/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'apply-discount')->defaults('admin_scope', 'admin:billing:discount');
    Route::post('actions/extend-trial', [\App\Http\Controllers\Admin\AdminController::class, 'extendTrial'])->defaults('operation', 'extend-trial')->defaults('admin_scope', 'admin:billing:extend-trial')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/extend-trial/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'extend-trial')->defaults('admin_scope', 'admin:billing:extend-trial');
    Route::post('actions/cancel-subscription', [\App\Http\Controllers\Admin\AdminController::class, 'cancelSubscription'])->defaults('operation', 'cancel-subscription')->defaults('admin_scope', 'admin:billing:cancel')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/cancel-subscription/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'cancel-subscription')->defaults('admin_scope', 'admin:billing:cancel');
    Route::post('actions/refund-payment', [\App\Http\Controllers\Admin\AdminController::class, 'refundPayment'])->defaults('operation', 'refund-payment')->defaults('admin_scope', 'admin:billing:refund')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/refund-payment/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'refund-payment')->defaults('admin_scope', 'admin:billing:refund');
    Route::post('actions/update-customer', [\App\Http\Controllers\Admin\BillingController::class, 'updateCustomer'])->defaults('operation', 'update-customer')->defaults('admin_scope', 'admin:billing:update')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/update-customer/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'update-customer')->defaults('admin_scope', 'admin:billing:update');
    Route::post('actions/restore-form', [\App\Http\Controllers\Admin\FormController::class, 'restoreDeletedForm'])->defaults('operation', 'restore-form')->defaults('admin_scope', 'admin:forms:restore')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/restore-form/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'restore-form')->defaults('admin_scope', 'admin:forms:restore');
    Route::post('actions/create-template', [\App\Http\Controllers\Admin\AdminController::class, 'createTemplate'])->defaults('operation', 'create-template')->defaults('admin_scope', 'admin:templates:create')->middleware(\App\Http\Middleware\AdminRequestReceipt::class);
    Route::get('actions/create-template/{actionId}', [\App\Http\Controllers\Admin\AdminController::class, 'receipt'])->defaults('operation', 'create-template')->defaults('admin_scope', 'admin:templates:create');
});
