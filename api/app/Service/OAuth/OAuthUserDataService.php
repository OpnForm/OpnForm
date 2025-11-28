<?php

namespace App\Service\OAuth;

use App\Exceptions\OAuth\InvalidWidgetDataException;
use App\Integrations\OAuth\OAuthProviderService;
use App\Integrations\OAuth\Drivers\Contracts\WidgetOAuthDriver;
use Illuminate\Http\Request;

/**
 * OAuthUserDataService
 *
 * Extracts and normalizes user data from OAuth providers.
 * Handles:
 * - Redirect-based OAuth flows (authorization code grant)
 * - Widget-based OAuth flows (Google One Tap)
 * - User data validation and normalization
 * - Missing field detection
 *
 * Normalizes data from different OAuth providers into a consistent format
 * for downstream processing by OAuthUserService.
 */
class OAuthUserDataService
{
    /**
     * Extract user data from OAuth redirect callback
     */
    public function extractFromRedirect(OAuthProviderService $providerService): array
    {
        $driver = $providerService->getDriver();
        $socialiteUser = $driver->getUser();

        return $this->normalizeUserData([
            'id' => $socialiteUser->getId(),
            'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname(),
            'email' => $socialiteUser->getEmail(),
            'provider_user_id' => $socialiteUser->getId(),
            'access_token' => $socialiteUser->token,
            'refresh_token' => $socialiteUser->refreshToken,
            'avatar' => $socialiteUser->getAvatar(),
            'scopes' => $socialiteUser->approvedScopes ?? [],
        ]);
    }

    /**
     * Extract user data from widget (e.g., Google One Tap)
     */
    public function extractFromWidget(OAuthProviderService $providerService, Request $request): array
    {
        $driver = $providerService->getDriver();

        if (!$driver instanceof WidgetOAuthDriver) {
            abort(400, 'This provider does not support widget authentication');
        }

        try {
            $driver->verifyWidgetData($request->all());
        } catch (InvalidWidgetDataException $e) {
            abort(400, $e->getMessage());
        }

        return $this->normalizeUserData(
            $driver->getUserFromWidgetData($request->all())
        );
    }

    /**
     * Normalize and validate user data from any source
     */
    private function normalizeUserData(array $userData): array
    {
        // Ensure required fields exist
        $required = ['name', 'provider_user_id'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                abort(400, "Missing required field: {$field}");
            }
        }

        // Email is optional (e.g., Telegram widget doesn't provide it)
        if (isset($userData['email']) && !empty($userData['email'])) {
            // Normalize email to lowercase
            $userData['email'] = strtolower($userData['email']);
        }

        // Set defaults for optional fields
        $userData['avatar'] = $userData['avatar'] ?? null;
        $userData['scopes'] = $userData['scopes'] ?? [];
        $userData['access_token'] = $userData['access_token'] ?? null;
        $userData['refresh_token'] = $userData['refresh_token'] ?? null;

        return $userData;
    }
}
