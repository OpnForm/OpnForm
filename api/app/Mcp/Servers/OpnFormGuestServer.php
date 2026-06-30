<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\FieldTypesResource;
use App\Mcp\Tools\Guest\DraftFormTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('OpnForm Guest')]
#[Version('1.0.0')]
#[Instructions('OpnForm Guest MCP Server. Build a form draft without authentication. After drafting, register at the provided URL, create an API token, and use the authenticated server to persist your form.')]
class OpnFormGuestServer extends Server
{
    protected array $tools = [
        DraftFormTool::class,
    ];

    protected array $resources = [
        FieldTypesResource::class,
    ];

    protected array $prompts = [];
}
