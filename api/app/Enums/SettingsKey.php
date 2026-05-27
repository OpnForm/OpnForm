<?php

namespace App\Enums;

enum SettingsKey: string
{
    case INSTANCE_ID = 'instance_id';
    case INSTANCE_CREATED_AT = 'instance_created_at';
    case SELF_HOSTED_LICENSE = 'self_hosted_license';

    public function value(): string
    {
        return $this->value;
    }
}
