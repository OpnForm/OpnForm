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
];
