<?php

namespace App\Http\Controllers\Auth\Traits;

use App\Models\User;
use App\Service\Auth\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;

trait ManagesJWT
{
    /**
     * Send the response after the user was authenticated.
     * Automatically handles blocked user check and 2FA verification.
     *
     * @param User $user The authenticated user
     * @param array $context Additional context (method, remember, etc.) for 2FA
     * @param array $additionalData Additional data to include in response
     * @return JsonResponse
     */
    protected function sendLoginResponse(
        User $user,
        array $context = [],
        array $additionalData = []
    ): JsonResponse {
        // Check if user is blocked
        if ($user->is_blocked) {
            // Clear any authentication before throwing exception
            // Wrap in try-catch because logout may fail if user isn't authenticated
            try {
                auth('api')->logout();
            } catch (\Exception $e) {
                // Ignore logout errors - user may not be authenticated yet
            }

            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                403,
                'Your account has been blocked. Please contact support.'
            );
        }

        // Check if 2FA is required
        $twoFactorService = app(TwoFactorAuthService::class);

        if ($twoFactorService->requiresVerification($user)) {
            // Logout the user since 2FA verification is required
            // The TwoFactorVerificationController uses 'guest' middleware
            auth('api')->logout();

            $pendingToken = $twoFactorService->storePendingAuth($user, $context);

            return response()->json(array_merge([
                'requires_2fa' => true,
                'pending_auth_token' => $pendingToken,
                'user' => $user->only(['id', 'name', 'email']), // Minimal user data
            ], $additionalData), 422);
        }

        // No 2FA required, issue token normally
        $guard = auth('api');
        $token = $guard->login($user);

        return response()->json(array_merge([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'new_user' => $user->new_user ?? false,
        ], $additionalData));
    }
}
