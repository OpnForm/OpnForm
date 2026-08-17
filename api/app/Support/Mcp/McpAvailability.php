<?php

namespace App\Support\Mcp;

final class McpAvailability
{
    public function enabled(): bool
    {
        return ! config('app.self_hosted')
            || (bool) config('opnform.mcp.enabled', false);
    }
}
