<?php

namespace App\Service\Telemetry;

enum TelemetryEvent: string
{
    case INSTANCE_CREATED = 'instance.created';
    case INSTANCE_PING = 'instance.ping';
    case USER_CREATED = 'user.created';
    case FORM_CREATED = 'form.created';
    case WORKSPACE_CREATED = 'workspace.created';
    case FORM_SUBMISSION = 'form.submission';
    case TWO_FACTOR_ENABLED = 'two_factor.enabled';
    case SSO_CREATED = 'sso.created';

    public function value(): string
    {
        return $this->value;
    }
}
