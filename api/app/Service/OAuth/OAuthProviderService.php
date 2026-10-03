<?php

namespace App\Service\OAuth;

use App\Integrations\OAuth\OAuthProviderService as OAuthProviderServiceEnum;
use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class OAuthProviderService
{
    public function createOrUpdateProvider(User $user, OAuthProviderServiceEnum $providerService, array $userData): OAuthProvider
    {
        $provider = $providerService->getDatabaseProvider();
        $providerUserId = $userData['provider_user_id'];

        // Check if this OAuth provider account is already linked to a different user
        $existingProvider = OAuthProvider::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($existingProvider) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'This ' . ucfirst($providerService->value) . ' account is already connected to another user.',
                    'error' => 'oauth_provider_already_linked'
                ], 409)
            );
        }

        $attributes = [
            'access_token' => $userData['access_token'],
            'name' => $userData['name'],
            'email' => $userData['email'] ?? null,
            'scopes' => $userData['scopes'] ?? [],
        ];
        if (!empty($userData['refresh_token'])) {
            $attributes['refresh_token'] = $userData['refresh_token'];
        }

        return OAuthProvider::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ],
            $attributes
        );
    }
}
