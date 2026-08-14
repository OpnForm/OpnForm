<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class AuthenticatedMcpTool extends Tool
{
    public function shouldRegister(): bool
    {
        return auth('mcp')->check();
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Connect your OpnForm account with OAuth to use this tool.');
        }

        return $user;
    }
}
