<?php

namespace App\Providers;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormZapierWebhook;
use App\Models\OAuthProvider;
use App\Models\Template;
use App\Models\Workspace;
use App\Policies\FormPolicy;
use App\Policies\FormSubmissionPolicy;
use App\Policies\Integration\FormZapierWebhookPolicy;
use App\Policies\OAuthProviderPolicy;
use App\Policies\PersonalAccessTokenPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Form::class => FormPolicy::class,
        FormSubmission::class => FormSubmissionPolicy::class,
        Workspace::class => WorkspacePolicy::class,
        FormZapierWebhook::class => FormZapierWebhookPolicy::class,
        Template::class => TemplatePolicy::class,
        OAuthProvider::class => OAuthProviderPolicy::class,
        PersonalAccessToken::class => PersonalAccessTokenPolicy::class,
        \App\Enterprise\Oidc\Models\IdentityConnection::class => \App\Enterprise\Oidc\Policies\IdentityConnectionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        \Illuminate\Support\Facades\Gate::define('viewMailcoach', function ($user = null) {
            return optional($user)->admin;
        });
    }
}
