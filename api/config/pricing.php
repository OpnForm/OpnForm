<?php

return [

    'production' => [
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
        ]
    ],

    'test' => [
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
        ]
    ],

    'discount_coupon_id' => env('STRIPE_DISCOUNT_COUPON_ID', null),
];
