<?php

namespace App\Http\Controllers\Auth;

use App\Enterprise\Oidc\ConnectionManager;
use App\Enterprise\Oidc\Exceptions\OidcAccountLinkRequiredException;
use App\Enterprise\Oidc\IdTokenVerifier;
use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\OidcLinkService;
use App\Http\Controllers\Auth\Traits\ManagesJWT;
use App\Http\Controllers\Controller;
use App\Enterprise\Oidc\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SsoController extends Controller
{
    use ManagesJWT;

    private const STATE_CACHE_PREFIX = 'oidc_login_state:';
    private const STATE_TTL_SECONDS = 600;
    private const STATE_VERIFIER_HEADER = 'X-OIDC-State-Verifier';

    public function __construct(
        private ConnectionManager $connectionManager,
        private ProvisioningService $provisioningService,
        private IdTokenVerifier $idTokenVerifier,
        private OidcLinkService $oidcLinkService
    ) {
    }

    /**
     * Get redirect URL for OIDC provider authentication.
     * Returns JSON response so frontend can handle redirect and errors.
     */
    public function redirect(Request $request, string $slug)
    {
        $connection = $this->connectionManager->getConnectionBySlug($slug);

        if (!$connection || !$connection->enabled) {
            return response()->json([
                'error' => 'OIDC connection not found or disabled',
            ], 404);
        }

        // Verify HTTPS in production
        if (config('app.env') === 'production' && !request()->secure()) {
            return response()->json([
                'error' => 'HTTPS is required for OIDC authentication',
            ], 400);
        }

        try {
            $driver = $this->connectionManager->buildDriver($connection);
            $state = null;
            $stateVerifier = null;

            if ($this->requiresState($connection)) {
                $state = Str::random(32);
                $stateVerifier = Str::random(64);
                $this->storeState($connection, $state, $stateVerifier);
                $driver->setState($state);
            }

            $redirectUrl = $driver->getRedirectUrl();

            $payload = [
                'redirect_url' => $redirectUrl,
            ];

            if ($state && $stateVerifier) {
                $payload['state'] = $state;
                $payload['state_verifier'] = $stateVerifier;
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            Log::error('OIDC redirect failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);


            return response()->json([
                'error' => 'Failed to initiate OIDC authentication',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle OIDC callback from provider.
     */
    public function callback(Request $request, string $slug)
    {
        $connection = $this->connectionManager->getConnectionBySlug($slug);

        if (!$connection || !$connection->enabled) {
            abort(404, 'OIDC connection not found or disabled');
        }

        // Verify HTTPS in production
        if (config('app.env') === 'production' && !request()->secure()) {
            abort(400, 'HTTPS is required for OIDC authentication');
        }

        try {
            if ($this->requiresState($connection)) {
                $state = $request->input('state');
                if (!$state) {
                    return response()->json([
                        'message' => 'Missing or invalid state. Please try again.',
                    ], 400);
                }

                $stateVerifier = $request->header(self::STATE_VERIFIER_HEADER);

                if (!$this->consumeState($connection, (string) $state, is_string($stateVerifier) ? $stateVerifier : null)) {
                    return response()->json([
                        'message' => 'Invalid state. Please try again.',
                    ], 400);
                }
            }

            $driver = $this->connectionManager->buildDriver($connection);
            $driver->setRedirectUrl($connection->redirect_url);

            // Get user from OIDC provider
            $socialiteUser = $driver->getUser();

            // Get ID token claims from provider's token response
            $idToken = $driver->getIdToken();
            $idTokenClaims = [];
            if ($idToken) {
                // Verify ID token signature before processing
                $this->idTokenVerifier->verifySignature($connection, $idToken);

                // Decode ID token payload
                [$header, $payload, $signature] = explode('.', $idToken);
                $idTokenClaims = json_decode(base64_decode(str_pad(
                    strtr($payload, '-_', '+/'),
                    strlen($payload) % 4,
                    '=',
                    STR_PAD_RIGHT
                )), true) ?? [];
            }

            // Also get claims from socialite user's raw data if available
            // The ID token claims should already be in the user object from OidcProvider
            if (is_object($socialiteUser)) {
                // Try to get raw data if method exists (Socialite User objects have getRaw())
                if (method_exists($socialiteUser, 'getRaw')) {
                    /** @var \Laravel\Socialite\Two\User $socialiteUser */
                    $raw = $socialiteUser->getRaw();
                    if ($raw && is_array($raw)) {
                        // Merge raw data into ID token claims (raw takes precedence for missing fields)
                        $idTokenClaims = array_merge($idTokenClaims, $raw);
                    }
                }
            }

            // If email is missing from ID token, try fetching from userinfo endpoint
            if (empty($idTokenClaims['email']) && !$socialiteUser->getEmail()) {
                try {
                    $accessToken = $driver->getAccessToken();

                    if ($accessToken) {
                        // Get OpenID config from provider (we need to access it via the provider)
                        // For now, construct userinfo URL from issuer
                        $issuer = rtrim($connection->issuer, '/');
                        $userInfoUrl = $issuer . '/userinfo';

                        $userInfoResponse = \Illuminate\Support\Facades\Http::withHeaders([
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $accessToken,
                        ])->get($userInfoUrl);

                        if ($userInfoResponse->successful()) {
                            $userInfo = $userInfoResponse->json();
                            // Merge userinfo claims into ID token claims
                            $idTokenClaims = array_merge($idTokenClaims, $userInfo);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('OIDC callback failed to fetch userinfo', [
                        'slug' => $slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Check if user already exists before provisioning
            $email = $socialiteUser->getEmail() ?? $idTokenClaims['email'] ?? null;
            $existingUser = null;
            if ($email) {
                $existingUser = \App\Models\User::where('email', strtolower($email))->first();
            }

            // Provision or authenticate user
            $user = $this->provisioningService->provisionUser(
                $connection,
                $socialiteUser,
                $idTokenClaims
            );

            // Determine if this is a new user
            $isNewUser = !$existingUser || $existingUser->id !== $user->id;

            // sendLoginResponse() automatically handles 2FA check and blocked user check
            // callback.vue always sends JSON, so we can directly return the response
            // HttpException (like 403 for blocked users) will be handled by the exception handler
            return $this->sendLoginResponse($user, [
                'method' => 'oidc',
                'slug' => $slug,
            ], [
                'user' => $user,
                'new_user' => $isNewUser,
                'redirect_url' => $request->cookie('intended_url') ?? '/home',
            ]);
        } catch (OidcAccountLinkRequiredException $e) {
            $linkToken = $this->oidcLinkService->createLinkToken(
                connectionId: $e->getConnectionId(),
                subject: $e->getSubject(),
                email: $e->getEmail(),
                claims: $e->getClaims(),
            );

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'oidc_account_link_required',
                'link_token' => $linkToken,
            ], 409);
        } catch (HttpException $e) {
            // Handle HTTP exceptions (like 403 for blocked users) - preserve status code
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('OIDC callback failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // callback.vue always sends JSON, so always return JSON response
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get OIDC connection options for an email address.
     * Used by login form to determine if OIDC is available and should redirect.
     */
    public function getOptionsForEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($request->input('email'));
        $domain = $this->extractDomain($email);

        if (!$domain) {
            return response()->json([
                'action' => 'fallback',
            ]);
        }

        // Find enabled OIDC connection matching domain
        // Domain is stored directly on the connection record
        $connection = IdentityConnection::enabled()
            ->where('type', IdentityConnection::TYPE_OIDC)
            ->where('domain', $domain)
            ->first();

        if (!$connection) {
            $forced = config('oidc.force_login', false);
            return response()->json([
                'action' => $forced ? 'blocked' : 'fallback',
            ]);
        }

        return response()->json([
            'action' => 'redirect',
            'slug' => $connection->slug,
        ]);
    }

    /**
     * Extract domain from email address.
     */
    protected function extractDomain(string $email): ?string
    {
        $parts = explode('@', strtolower(trim($email)));
        return count($parts) === 2 ? $parts[1] : null;
    }

    private function requiresState(IdentityConnection $connection): bool
    {
        return data_get($connection->options, 'require_state', true) !== false;
    }

    private function storeState(IdentityConnection $connection, string $state, string $stateVerifier): void
    {
        Cache::put($this->stateCacheKey($state), [
            'connection_id' => $connection->id,
            'verifier_hash' => hash('sha256', $stateVerifier),
        ], self::STATE_TTL_SECONDS);
    }

    private function consumeState(IdentityConnection $connection, string $state, ?string $stateVerifier): bool
    {
        if (!$stateVerifier) {
            return false;
        }

        $storedState = Cache::get($this->stateCacheKey($state));
        if (!is_array($storedState)) {
            return false;
        }

        if ((int) ($storedState['connection_id'] ?? 0) !== $connection->id) {
            return false;
        }

        if (!hash_equals((string) ($storedState['verifier_hash'] ?? ''), hash('sha256', $stateVerifier))) {
            return false;
        }

        Cache::forget($this->stateCacheKey($state));

        return true;
    }

    private function stateCacheKey(string $state): string
    {
        return self::STATE_CACHE_PREFIX.$state;
    }
}
