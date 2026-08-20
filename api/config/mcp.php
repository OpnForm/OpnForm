<?php

$redirectDomains = array_values(array_filter(array_map(
    'trim',
    explode(',', env('MCP_OAUTH_REDIRECT_DOMAINS', implode(',', [
        'https://chatgpt.com',
        'https://chat.openai.com',
        'https://claude.ai',
        'http://localhost',
        'http://127.0.0.1',
        'http://[::1]',
    ])))
)));

$customSchemes = array_values(array_filter(array_map(
    'trim',
    explode(',', env('MCP_OAUTH_CUSTOM_SCHEMES', 'chatgpt,claude,cursor,vscode'))
)));

return [
    'redirect_domains' => $redirectDomains,
    'custom_schemes' => $customSchemes,
    'authorization_server' => env('MCP_AUTHORIZATION_SERVER') ?: null,
];
