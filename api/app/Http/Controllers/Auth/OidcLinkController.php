<?php

namespace App\Http\Controllers\Auth;

use App\Enterprise\Oidc\Models\UserIdentity;
use App\Enterprise\Oidc\OidcLinkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OidcLinkController extends Controller
{
    public function link(Request $request, OidcLinkService $linkService)
    {
        $validated = $request->validate([
            'link_token' => ['required', 'string'],
        ]);

        $payload = $linkService->getLinkToken($validated['link_token']);

        if (!$payload) {
            return response()->json([
                'message' => 'This link request has expired. Please try again.',
                'error' => 'oidc_link_expired',
            ], 410);
        }

        $user = $request->user();
        $email = strtolower($user->email);

        if ($email !== strtolower($payload['email'])) {
            return response()->json([
                'message' => 'This link request does not match your account.',
                'error' => 'oidc_link_email_mismatch',
            ], 403);
        }

        $existingIdentity = UserIdentity::where('connection_id', $payload['connection_id'])
            ->where('subject', $payload['subject'])
            ->first();

        if ($existingIdentity && $existingIdentity->user_id !== $user->id) {
            return response()->json([
                'message' => 'This SSO identity is already linked to another user.',
                'error' => 'oidc_link_already_linked',
            ], 409);
        }

        $payload = $linkService->consumeLinkToken($validated['link_token']);

        if (!$payload) {
            return response()->json([
                'message' => 'This link request has expired. Please try again.',
                'error' => 'oidc_link_expired',
            ], 410);
        }

        UserIdentity::updateOrCreate(
            [
                'connection_id' => $payload['connection_id'],
                'subject' => $payload['subject'],
            ],
            [
                'user_id' => $user->id,
                'email' => $email,
                'claims' => $payload['claims'] ?? [],
            ]
        );

        return response()->json([
            'linked' => true,
        ]);
    }
}
