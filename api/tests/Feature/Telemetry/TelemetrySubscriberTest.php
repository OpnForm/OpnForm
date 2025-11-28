<?php

use App\Events\Forms\FormSubmitted;
use App\Events\Models\FormCreated;
use App\Events\Models\UserCreated;
use App\Events\Models\WorkspaceCreated;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

describe('TelemetrySubscriber', function () {
    beforeEach(function () {
        Queue::fake();
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true); // Enable self-hosted for tests
    });

    it('handles FormCreated event', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $event = new FormCreated($form);

        event($event);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::FORM_CREATED->value();
        });
    });

    it('handles WorkspaceCreated event', function () {
        $workspace = Workspace::factory()->create();
        $event = new WorkspaceCreated($workspace);

        event($event);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::WORKSPACE_CREATED->value();
        });
    });

    it('handles UserCreated event', function () {
        $user = User::factory()->create();
        $event = new UserCreated($user);

        event($event);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::USER_CREATED->value();
        });
    });

    it('handles FormSubmitted event', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $event = new FormSubmitted($form, []);

        event($event);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::FORM_SUBMISSION->value();
        });
    });

    it('sends events without properties for anonymous telemetry', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $event = new FormCreated($form);

        event($event);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return empty($job->properties);
        });
    });
});
