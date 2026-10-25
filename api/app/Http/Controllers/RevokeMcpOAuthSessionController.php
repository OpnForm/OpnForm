<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

class RevokeMcpOAuthSessionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $token instanceof Token || ! $token->can('mcp:use')) {
            throw new AuthenticationException('A valid MCP access token is required.', ['oauth']);
        }

        DB::transaction(function () use ($token): void {
            $token->refreshToken()->update(['revoked' => true]);
            $token->revoke();
        });

        return response()->noContent();
    }
}
