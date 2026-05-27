<?php

namespace App\Service\Billing;

final class PlanTier
{
    public const FREE = 'free';
    public const PRO = 'pro';
    public const BUSINESS = 'business';
    public const ENTERPRISE = 'enterprise';
    public const SELF_HOSTED = 'self_hosted';

    public const ORDER = [
        self::FREE => 0,
        self::PRO => 1,
        self::BUSINESS => 2,
        self::ENTERPRISE => 3,
        self::SELF_HOSTED => 4,
    ];

    public static function all(): array
    {
        return array_keys(self::ORDER);
    }
}
