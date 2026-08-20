<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Service\OAuth\McpOAuthSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpOAuthSessionController extends Controller
{
    public function __invoke(Request $request, McpOAuthSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'authorization_request_token' => ['required', 'string', 'size:64'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'authorization_url' => $sessions->issueLoginTicket(
                $validated['authorization_request_token'],
                $user
            ),
        ]);
    }
}
