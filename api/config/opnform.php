<?php

return [
    'admin_emails' => explode(',', env('ADMIN_EMAILS') ?? ''),
    'moderator_emails' => explode(',', env('MODERATOR_EMAILS') ?? ''),
    'template_editor_emails' => explode(',', env('TEMPLATE_EDITOR_EMAILS') ?? ''),
    'extra_pro_users_emails' => explode(',', env('EXTRA_PRO_USERS_EMAILS') ?? ''),
    'show_official_templates' => env('SHOW_OFFICIAL_TEMPLATES', true),
    'condition_mapping' => json_decode(file_get_contents(resource_path('data/open_filters.json')), true),
    'custom_code' => [
        'enable_self_hosted' => env('CUSTOM_CODE_ENABLE_SELF_HOSTED', false),
    ],
    'public_uploads' => [
        'rate_limit' => [
            'per_minute' => (int) env('PUBLIC_UPLOADS_RATE_LIMIT_PER_MINUTE', 30),
            'per_hour' => (int) env('PUBLIC_UPLOADS_RATE_LIMIT_PER_HOUR', 300),
        ],
    ],
    'mcp' => [
        // Cloud instances expose MCP by default. Self-hosted instances must opt in.
        'enabled' => env('MCP_ENABLED', false),
        'observability_enabled' => env('MCP_OBSERVABILITY_ENABLED', true),
        'rate_limit' => [
            'per_minute' => (int) env('MCP_RATE_LIMIT_PER_MINUTE', 120),
            'per_hour' => (int) env('MCP_RATE_LIMIT_PER_HOUR', 3000),
            'draft_creates_per_minute' => (int) env('MCP_DRAFT_CREATES_PER_MINUTE', 20),
            'draft_creates_per_hour' => (int) env('MCP_DRAFT_CREATES_PER_HOUR', 200),
            'submission_exports_per_minute' => (int) env('MCP_SUBMISSION_EXPORTS_PER_MINUTE', 5),
            'submission_exports_per_hour' => (int) env('MCP_SUBMISSION_EXPORTS_PER_HOUR', 30),
        ],
    ],
    'form_summary_rate_limit_per_minute' => (int) env('FORM_SUMMARY_RATE_LIMIT_PER_MINUTE', 30),
    'webhooks' => [
        'allow_private_urls' => env('WEBHOOKS_ALLOW_PRIVATE_URLS', false),
    ],
];
