<?php

namespace App\Providers;

use App\Events\Billing\SubscriptionCreated;
use App\Events\Billing\SubscriptionUpdated;
use App\Events\Forms\FormSubmitted;
use App\Events\Models\FormSubmissionDeleting;
use App\Events\Forms\FormSaved;
use App\Events\Models\FormCreated;
use App\Events\Models\FormIntegrationCreated;
use App\Events\Models\FormIntegrationSaved;
use App\Events\Models\FormIntegrationsEventCreated;
use App\Listeners\Billing\HandleSubscriptionCreated;
use App\Listeners\Billing\RemoveWorkspaceGuestsIfNeeded;
use App\Listeners\Forms\FormCreationConfirmation;
use App\Listeners\Forms\FormIntegrationCreatedHandler;
use App\Listeners\Forms\FormIntegrationsEventListener;
use App\Listeners\Forms\NotifyFormSubmission;
use App\Listeners\Forms\DeleteFormSubmissionFiles;
use App\Listeners\Forms\FormSpamCheckListener;
use App\Listeners\Integration\FormIntegrationSpamCheckListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        FormCreated::class => [
            FormCreationConfirmation::class,
        ],
        FormSaved::class => [
            FormSpamCheckListener::class,
        ],
        FormSubmitted::class => [
            NotifyFormSubmission::class,
            \App\Listeners\Forms\InvalidateFormSubmissionCache::class,
        ],
        FormSubmissionDeleting::class => [
            DeleteFormSubmissionFiles::class,
        ],
        FormIntegrationCreated::class => [
            FormIntegrationCreatedHandler::class,
        ],
        FormIntegrationSaved::class => [
            FormIntegrationSpamCheckListener::class,
        ],
        FormIntegrationsEventCreated::class => [
            FormIntegrationsEventListener::class,
        ],
        SubscriptionCreated::class => [
            HandleSubscriptionCreated::class,
        ],
        SubscriptionUpdated::class => [
            RemoveWorkspaceGuestsIfNeeded::class
        ]
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        \App\Service\Telemetry\TelemetrySubscriber::class,
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
