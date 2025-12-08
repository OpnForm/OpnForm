<?php

use App\Service\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

describe('telemetry helper function', function () {
    beforeEach(function () {
        Queue::fake();
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true); // Enable self-hosted for tests
    });

    it('dispatches job when telemetry is enabled', function () {
        telemetry(TelemetryEvent::FORM_CREATED);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::FORM_CREATED->value()
                && empty($job->properties);
        });
    });

    it('dispatches job with properties', function () {
        $properties = ['foo' => 'bar', 'baz' => 'qux'];
        telemetry(TelemetryEvent::FORM_CREATED, $properties);

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) use ($properties) {
            return $job->eventName === TelemetryEvent::FORM_CREATED->value()
                && $job->properties === $properties;
        });
    });

    it('dispatches job even when telemetry is disabled (job handles the check)', function () {
        Config::set('telemetry.enabled', false);

        telemetry(TelemetryEvent::FORM_CREATED);

        // Job is dispatched, but will check and return early in handle()
        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::FORM_CREATED->value();
        });
    });

    it('works with all event types', function () {
        $events = [
            TelemetryEvent::INSTANCE_CREATED,
            TelemetryEvent::USER_CREATED,
            TelemetryEvent::FORM_CREATED,
            TelemetryEvent::WORKSPACE_CREATED,
            TelemetryEvent::FORM_SUBMISSION,
        ];

        foreach ($events as $event) {
            telemetry($event);
        }

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, 5);
    });
});
