<?php

namespace App\Service\Billing\Data;

class BillingState
{
    public function __construct(
        public readonly ?int $workspaceId,
        public readonly string $tier,
        public readonly bool $isPaid,
        public readonly ?string $interval = null,
        public readonly ?string $stripeSubscriptionId = null,
        public readonly ?string $stripePriceId = null,
        public readonly ?string $subscriptionType = null,
        public readonly bool $isGrandfathered = false,
        public readonly bool $hasLicense = false,
        public readonly bool $hasOverrides = false,
    ) {
    }
}
