<?php

use App\Service\Telemetry\TelemetryEvent;

describe('TelemetryEvent', function () {
    it('has all required event cases', function () {
        expect(TelemetryEvent::cases())->toHaveCount(8);
    });

    it('has INSTANCE_CREATED case with correct value', function () {
        expect(TelemetryEvent::INSTANCE_CREATED->value)->toBe('instance.created');
    });

    it('has USER_CREATED case with correct value', function () {
        expect(TelemetryEvent::USER_CREATED->value)->toBe('user.created');
    });

    it('has FORM_CREATED case with correct value', function () {
        expect(TelemetryEvent::FORM_CREATED->value)->toBe('form.created');
    });

    it('has WORKSPACE_CREATED case with correct value', function () {
        expect(TelemetryEvent::WORKSPACE_CREATED->value)->toBe('workspace.created');
    });

    it('has FORM_SUBMISSION case with correct value', function () {
        expect(TelemetryEvent::FORM_SUBMISSION->value)->toBe('form.submission');
    });

    it('has INSTANCE_PING case with correct value', function () {
        expect(TelemetryEvent::INSTANCE_PING->value)->toBe('instance.ping');
    });

    it('has TWO_FACTOR_ENABLED case with correct value', function () {
        expect(TelemetryEvent::TWO_FACTOR_ENABLED->value)->toBe('two_factor.enabled');
    });

    it('has SSO_CREATED case with correct value', function () {
        expect(TelemetryEvent::SSO_CREATED->value)->toBe('sso.created');
    });

    it('value method returns the enum value', function () {
        expect(TelemetryEvent::INSTANCE_CREATED->value())->toBe('instance.created');
        expect(TelemetryEvent::USER_CREATED->value())->toBe('user.created');
    });
});
