<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default OIDC Scopes
    |--------------------------------------------------------------------------
    |
    | Default scopes to request from the identity provider.
    |
    */
    'default_scopes' => ['openid', 'profile', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Force OIDC Login
    |--------------------------------------------------------------------------
    |
    | When enabled and at least one OIDC connection exists, password-based
    | login will be disabled.
    |
    */
    'force_login' => env('OIDC_FORCE_LOGIN', false),

    /*
    |--------------------------------------------------------------------------
    | Blocked Email Providers
    |--------------------------------------------------------------------------
    |
    | List of common email provider domains that are not allowed for OIDC
    | connections. Users must use their own organization's email domain.
    |
    */
    'blocked_email_providers' => [
        // Google
        'gmail.com',
        'googlemail.com',
        // Microsoft
        'outlook.com',
        'hotmail.com',
        'live.com',
        'msn.com',
        // Yahoo
        'yahoo.com',
        'yahoo.co.uk',
        'ymail.com',
        'rocketmail.com',
        // Apple
        'icloud.com',
        'me.com',
        'mac.com',
        // AOL
        'aol.com',
        // ProtonMail
        'protonmail.com',
        'proton.me',
        // Other consumer providers
        'mail.com',
        'gmx.com',
        'gmx.de',
        'gmx.net',
        'zoho.com',
        'yandex.com',
        'yandex.ru',
        'mail.ru',
        'inbox.ru',
        'bk.ru',
        'list.ru',
        'fastmail.com',
        'fastmail.fm',
        // Chinese providers
        '163.com',
        'qq.com',
        'sina.com',
        'sohu.com',
        '126.com',
        'aliyun.com',
    ],
];
