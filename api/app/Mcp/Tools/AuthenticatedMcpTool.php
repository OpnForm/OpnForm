<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;

abstract class AuthenticatedMcpTool extends McpTool
{
    /**
     * @return list<array{type: string, scopes: list<string>}>
     */
    protected function securitySchemes(): array
    {
        return [
            [
                'type' => 'oauth2',
                'scopes' => ['mcp:use'],
            ],
        ];
    }

    protected function user(Request $request): User
    {
        $user = $request->user('oauth');

        if (! $user instanceof User) {
            throw new AuthenticationException('Connect your OpnForm account with OAuth to use this tool.');
        }

        return $user;
    }
}
