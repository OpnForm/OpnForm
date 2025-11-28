<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Slack Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Slack logging integration.
    |
    */

    'slack' => [
        'token' => env('SLACK_BOT_TOKEN'),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'combined' => [
            'driver' => 'stack',
            'channels' => [env('LOG_CHANNEL') === 'combined' ? 'stack' : env('LOG_CHANNEL', 'stack'), 'slack_errors'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack_errors' => [
            'driver' => 'custom',
            'via' => \App\Logging\SlackLoggerFactory::class,
            'channel' => env('SLACK_ERRORS_CHANNEL', '#errors-opnform'),
        ],

        'slack_alerts' => [
            'driver' => 'custom',
            'via' => \App\Logging\SlackLoggerFactory::class,
            'channel' => env('SLACK_ALERTS_CHANNEL', '#alerts-opnform'),
        ],

        'slack_admin' => [
            'driver' => 'custom',
            'via' => \App\Logging\SlackLoggerFactory::class,
            'channel' => env('SLACK_ADMIN_CHANNEL', '#admin-opnform'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'level' => env('LOG_LEVEL'),
            'with' => [
                'stream' => 'php://stderr',
                'level' => env('LOG_LEVEL'),
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
