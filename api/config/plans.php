<?php

/**
 * OpnForm Plans Configuration
 *
 * SINGLE SOURCE OF TRUTH for all pricing, features, and limits.
 * Do not hardcode tier logic elsewhere in the codebase.
 */

return [
    /**
     * Tier definitions and ordering
     */
    'tiers' => [
        'free' => ['order' => 0, 'name' => 'Free', 'price_monthly' => 0, 'price_yearly' => 0],
        'pro' => ['order' => 1, 'name' => 'Pro', 'price_monthly' => 29, 'price_yearly' => 290],
        'business' => ['order' => 2, 'name' => 'Business', 'price_monthly' => 79, 'price_yearly' => 790],
        'enterprise' => ['order' => 3, 'name' => 'Enterprise', 'price_monthly' => 250, 'price_yearly' => 3000],
    ],

    /**
     * Map subscription names to tiers (for Stripe subscriptions)
     */
    'subscription_tier_mapping' => [
        'default' => 'pro',      // Legacy subscriptions
        'pro' => 'pro',
        'business' => 'business',
        'enterprise' => 'enterprise',
    ],

    /**
     * Feature to minimum tier mapping
     * If a feature is not listed, it's available to all tiers (free)
     */
    'features' => [
        // Branding
        'branding.removal' => 'pro',
        'branding.advanced' => 'business',  // CSS, fonts, favicons

        // Workspaces
        'workspaces.multiple' => 'pro',

        // Multi-user
        'multi_user.roles' => 'business',

        // Domains
        'custom_domain' => 'pro',
        'custom_domain.wildcard' => 'business',

        // Email/SMTP
        'custom_smtp' => 'pro',

        // Security
        'security.password_protection' => 'pro',
        'security.form_expiration' => 'pro',
        'security.captcha' => 'pro',

        // Integrations (basic ones like email, webhook, zapier, google_sheets are free)
        'integrations.slack' => 'pro',
        'integrations.discord' => 'pro',
        'integrations.telegram' => 'pro',
        'integrations.hubspot' => 'business',
        'integrations.salesforce' => 'business',
        'integrations.airtable' => 'business',

        // Form Features
        'partial_submissions' => 'business',
        'enable_partial_submissions' => 'business',
        'form_versioning' => 'business',
        'google_address_autocomplete' => 'business',
        'editable_submissions' => 'business',
        'database_fields_update' => 'business',
        'enable_ip_tracking' => 'business',

        // Enterprise
        'sso.oidc' => 'enterprise',
        'sso.saml' => 'enterprise',
        'sso.ldap' => 'enterprise',
        'audit_logs' => 'enterprise',
        'compliance_features' => 'enterprise',
        'external_storage' => 'enterprise',
        'white_label' => 'enterprise',
    ],

    /**
     * Numeric limits per tier (null = unlimited)
     */
    'limits' => [
        'file_upload_size' => [
            'free' => 10 * 1024 * 1024,        // 10 MB
            'pro' => 50 * 1024 * 1024,         // 50 MB
            'business' => 1024 * 1024 * 1024,  // 1 GB
            'enterprise' => null,               // Unlimited/configurable
        ],
        'custom_domain_count' => [
            'free' => 0,
            'pro' => 1,
            'business' => 10,
            'enterprise' => null,  // Unlimited
        ],
        'workspace_count' => [
            'free' => 1,
            'pro' => null,      // Unlimited
            'business' => null,
            'enterprise' => null,
        ],
    ],

    /**
     * Form feature to tier mapping (used by FormCleaner)
     */
    'form_features' => [
        // Pro tier features
        'no_branding' => 'pro',
        'redirect_url' => 'pro',
        'secret_input' => 'pro',
        'analytics' => 'pro',

        // Business tier features
        'custom_css' => 'business',
        'seo_meta' => 'business',
        'enable_partial_submissions' => 'business',
        'editable_submissions' => 'business',
        'database_fields_update' => 'business',
        'enable_ip_tracking' => 'business',

        // Business tier features

    ],

    /**
     * Default values for form features when cleaned (tier requirement not met)
     */
    'form_feature_defaults' => [
        'no_branding' => false,
        'redirect_url' => null,
        'custom_css' => null,
        'seo_meta' => [],
        'analytics' => [],
        'enable_partial_submissions' => false,
        'editable_submissions' => false,
        'database_fields_update' => null,
        'enable_ip_tracking' => false,
        'secret_input' => false,
    ],
];
