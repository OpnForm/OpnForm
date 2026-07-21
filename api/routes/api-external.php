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
