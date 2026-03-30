<?php

return [
    'subscription_types' => ['default', 'pro', 'business', 'enterprise'],

    'product_tier_mapping' => [
        'default' => 'pro',
        'pro' => 'pro',
        'business' => 'business',
        'enterprise' => 'enterprise',
    ],

    'grandfathered_prices' => [
        // Add grandfathered legacy flat prices here when available.
    ],
];
