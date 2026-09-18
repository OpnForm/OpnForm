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

// This surface deliberately excludes impersonation and customer-token wildcard access.
Route::prefix('external/admin/v1')->middleware(['auth:sanctum', \App\Http\Middleware\AdminApiRemoteTimeout::class])->name('admin-api.')->group(function () {
    $auth = \App\Http\Middleware\AuthenticateAdminApi::class;
    $controller = \App\Http\Controllers\External\AdminApiController::class;
    Route::get('users/{identifier}', [$controller, 'user'])->middleware($auth.':admin:users:read')->name('users.show');
    Route::get('workspaces/{id}', [$controller, 'workspace'])->middleware($auth.':admin:workspaces:read')->name('workspaces.show');
    Route::get('users/{user}/billing/{resource}', [$controller, 'billing'])->middleware($auth.':admin:billing:read')->name('billing.show');
    Route::get('users/{user}/deleted-forms', [$controller, 'deletedForms'])->middleware($auth.':admin:forms:read')->name('forms.deleted');
    Route::get('actions/{actionId}', [$controller, 'actionStatus'])->middleware($auth)->name('actions.show');
    Route::post('actions/{operation}', [$controller, 'execute'])->middleware($auth)->name('actions.execute');
});
