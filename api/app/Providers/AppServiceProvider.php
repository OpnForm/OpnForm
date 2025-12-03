<?php

namespace App\Providers;

use App\Models\Billing\Subscription;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Dusk\DuskServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('filesystems.default') === 'local') {
            Storage::disk('local')->buildTemporaryUrlsUsing(function ($path, $expiration, $options) {
                return URL::temporarySignedRoute(
                    'local.temp',
                    $expiration,
                    array_merge($options, ['path' => $path])
                );
            });
        }

        JsonResource::withoutWrapping();
        Cashier::calculateTaxes();
        Cashier::useSubscriptionModel(Subscription::class);

        if ($this->app->runningUnitTests()) {
            Schema::defaultStringLength(191);
        }

        Validator::includeUnvalidatedArrayKeys();

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('stripe', \SocialiteProviders\Stripe\Provider::class);
        });

        // Route model bindings for performance optimization
        Route::bind('workspace', function ($value) {
            return \App\Models\Workspace::with([
                'users' => fn ($q) => $q->withPivot('role')
            ])->findOrFail((int) $value);
        });

        Route::bind('form', function ($value) {
            // Support both numeric ID and slug resolution (compact, common part outside ternary)
            $query = \App\Models\Forms\Form::with(['workspace.users' => fn ($q) => $q->withPivot('role')]);
            return is_numeric($value)
                ? $query->findOrFail((int) $value)
                : $query->where('slug', $value)->firstOrFail();
        });

        Route::bind('user', function ($value) {
            return \App\Models\User::findOrFail((int) $value);
        });

        // OIDC connection binding - load with workspace relationship for policy checks
        Route::bind('connection', function ($value) {
            return \App\Enterprise\Oidc\Models\IdentityConnection::with('workspace')
                ->findOrFail((int) $value);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment('local', 'testing') && class_exists(DuskServiceProvider::class)) {
            $this->app->register(DuskServiceProvider::class);
        }

        // OAuth Services
        $this->app->singleton(\App\Service\OAuth\OAuthContextService::class);
        $this->app->singleton(\App\Service\OAuth\OAuthInviteService::class);
        $this->app->singleton(\App\Service\OAuth\OAuthUserDataService::class);
        $this->app->singleton(\App\Service\OAuth\OAuthFlowOrchestrator::class);
    }
}
