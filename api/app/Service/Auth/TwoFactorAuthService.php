<?php

namespace App\Service\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
    /**
     * Check if user requires 2FA verification.
     */
    public function requiresVerification(User $user): bool
    {
        return $user->hasTwoFactorEnabled();
    }

    /**
     * Store pending authentication and return token.
     */
    public function storePendingAuth(User $user, array $context = []): string
    {
        $token = Str::random(64);

        Cache::put("2fa_pending:{$token}", [
            'user_id' => $user->id,
            'method' => $context['method'] ?? 'password',
            'remember' => $context['remember'] ?? false,
            'created_at' => now()->toIso8601String(),
            'context' => $context,
        ], now()->addMinutes(10));

        return $token;
    }

    /**
     * Get pending authentication data.
     */
    public function getPendingAuth(string $token): ?array
    {
        return Cache::get("2fa_pending:{$token}");
    }

    /**
     * Clear pending authentication.
     */
    public function clearPendingAuth(string $token): void
    {
        Cache::forget("2fa_pending:{$token}");
    }
}
