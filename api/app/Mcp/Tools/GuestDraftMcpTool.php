<?php

namespace App\Mcp\Tools;

use App\Support\Mcp\McpAvailability;

abstract class GuestDraftMcpTool extends GuestMcpTool
{
    public function shouldRegister(McpAvailability $availability): bool
    {
        return $availability->guestDraftsEnabled();
    }
}
