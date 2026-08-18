<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tool;

abstract class McpTool extends Tool
{
    /**
     * @return list<array{type: string, scopes?: list<string>}>
     */
    abstract protected function securitySchemes(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'securitySchemes' => $this->securitySchemes(),
        ];
    }
}
