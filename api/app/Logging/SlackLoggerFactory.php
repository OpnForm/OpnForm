<?php

namespace App\Logging;

use Monolog\Handler\NullHandler;
use Monolog\Logger;

class SlackLoggerFactory
{
    /**
     * Create a custom Slack logger instance.
     */
    public function __invoke(array $config): Logger
    {
        $token = config('logging.slack.token');

        // If no token is configured, use a null handler to silently ignore logs
        if (empty($token)) {
            return new Logger('slack', [new NullHandler()]);
        }

        $handler = new SlackLogHandler(
            $token,
            $config['channel'] ?? null
        );

        return new Logger('slack', [$handler]);
    }
}
