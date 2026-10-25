<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Traits\ManagesJWT;
use App\Http\Controllers\Controller;
use App\Service\Auth\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TwoFactorVerificationController extends Controller
{
    use ManagesJWT;

    public function __construct(
        private TwoFactorAuthService $twoFactorService
    ) {
        $this->middleware('throttle:30,1')->only('verify');
    }

    /**
     * Verify 2FA code during login and complete authentication.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'pending_auth_token' => ['required', 'string', 'size:64', 'regex:/^[a-zA-Z0-9]+$/'],
            'code' => 'required|string|min:6|max:8', // Supports both TOTP (6) and recovery codes (8)
        ]);

        // Rate limiting - use pending_auth_token to limit per-session, with IP as fallback
        $key = '2fa_verify:' . ($request->pending_auth_token ? sha1($request->pending_auth_token) : $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => ["Too many attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        RateLimiter::hit($key, 60); // 5 attempts per minute

        return Cache::lock('2fa_verify_lock:' . hash('sha256', $request->pending_auth_token), 15)
            ->block(5, function () use ($request) {
                // Get pending auth data
                $pendingAuth = $this->twoFactorService->getPendingAuth($request->pending_auth_token);

                if (!$pendingAuth) {
                    throw ValidationException::withMessages([
                        'pending_auth_token' => ['Invalid or expired verification session. Please log in again.'],
                    ]);
                }

                // Get user
                $user = \App\Models\User::find($pendingAuth['user_id']);

                if (!$user || ($pendingAuth['auth_version'] ?? null) !== $user->auth_version) {
                    throw ValidationException::withMessages([
                        'pending_auth_token' => ['User not found. Please log in again.'],
                    ]);
                }

                // Verify code (TOTP or recovery code - validateTwoFactorCode handles both automatically)
                if (!$user->validateTwoFactorCode($request->code)) {
                    throw ValidationException::withMessages([
                        'code' => ['Invalid verification code. Please try again.'],
                    ]);
                }

                // Issue JWT token
                $guard = auth('api');
                $token = $guard->login($user);

                // Set remember TTL if needed
                if ($pendingAuth['remember'] ?? false) {
                    $guard->setTTL(config('jwt.remember_ttl'));
                    $token = $guard->login($user); // Re-login with new TTL
                }

                $expiration = $guard->getPayload()->get('exp');

                $this->twoFactorService->clearPendingAuth($request->pending_auth_token);

                return response()->json([
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $expiration - time(),
                    'user' => $user,
                ]);
            });
    }
}
