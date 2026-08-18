<?php

namespace App\Mcp\Tools;

abstract class GuestMcpTool extends McpTool
{
    /**
     * @return list<array{type: string}>
     */
    protected function securitySchemes(): array
    {
        return [
            ['type' => 'noauth'],
        ];
    }
}
