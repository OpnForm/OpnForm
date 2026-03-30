<?php

/**
 * Stripe Products and Pricing Configuration
 *
 * Contains Stripe product IDs and price IDs.
 * For tier definitions and feature gating, see config/plans.php
 */

return [
    'production' => [
        'pro' => [
            'product_id' => env('STRIPE_PROD_PRO_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_PROD_PRO_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_PROD_PRO_PRICING_YEARLY'),
            ],
        ],

        'business' => [
            'product_id' => env('STRIPE_PROD_BUSINESS_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_PROD_BUSINESS_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_PROD_BUSINESS_PRICING_YEARLY'),
            ],
        ],

        'enterprise' => [
            'product_id' => env('STRIPE_PROD_ENTERPRISE_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_PROD_ENTERPRISE_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_PROD_ENTERPRISE_PRICING_YEARLY'),
            ],
        ],

        // Legacy "default" subscription - maps to Pro tier
        'default' => [
            'product_id' => env('STRIPE_PROD_DEFAULT_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_PROD_DEFAULT_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_PROD_DEFAULT_PRICING_YEARLY'),
            ],
        ],

        'extra_user' => [
            'product_id' => env('STRIPE_PROD_EXTRA_USER_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_PROD_EXTRA_USER_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_PROD_EXTRA_USER_PRICING_YEARLY'),
            ],
        ],
    ],

    'test' => [
        'pro' => [
            'product_id' => env('STRIPE_TEST_PRO_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_TEST_PRO_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_TEST_PRO_PRICING_YEARLY'),
            ],
        ],

        'business' => [
            'product_id' => env('STRIPE_TEST_BUSINESS_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_TEST_BUSINESS_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_TEST_BUSINESS_PRICING_YEARLY'),
            ],
        ],

        'enterprise' => [
            'product_id' => env('STRIPE_TEST_ENTERPRISE_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_TEST_ENTERPRISE_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_TEST_ENTERPRISE_PRICING_YEARLY'),
            ],
        ],

        // Legacy "default" subscription - maps to Pro tier
        'default' => [
            'product_id' => env('STRIPE_TEST_DEFAULT_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_TEST_DEFAULT_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_TEST_DEFAULT_PRICING_YEARLY'),
            ],
        ],

        'extra_user' => [
            'product_id' => env('STRIPE_TEST_EXTRA_USER_PRODUCT_ID'),
            'pricing' => [
                'monthly' => env('STRIPE_TEST_EXTRA_USER_PRICING_MONTHLY'),
                'yearly' => env('STRIPE_TEST_EXTRA_USER_PRICING_YEARLY'),
            ],
        ],
    ],

    'discount_coupon_id' => env('STRIPE_DISCOUNT_COUPON_ID', null),
];
