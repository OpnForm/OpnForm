<?php

namespace App\Service\OAuth;

use App\Http\Controllers\Auth\Traits\ManagesJWT;
use App\Http\Resources\OAuthProviderResource;
use App\Integrations\OAuth\OAuthProviderService;
use App\Service\OAuth\OAuthProviderService as OAuthProviderServiceClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * OAuthFlowOrchestrator
 *
 * Orchestrates the OAuth authentication and integration flows.
 * Handles:
 * - OAuth redirect initiation with state token generation
 * - OAuth callback processing (authorization code exchange)
 * - Widget-based OAuth flows (Google One Tap)
 * - Intent routing (auth vs integration)
 * - Error handling and validation
 *
 * Delegates specific responsibilities to specialized services:
 * - OAuthContextService: Context/metadata storage
 * - OAuthUserDataService: User data extraction
 * - OAuthUserService: User creation/lookup
 * - OAuthInviteService: Invite validation
 */
class OAuthFlowOrchestrator
{
    use ManagesJWT;

    public const INTENT_AUTH = 'auth';
    public const INTENT_INTEGRATION = 'integration';
    public const INTENTS = [self::INTENT_AUTH, self::INTENT_INTEGRATION];

    public function __construct(
        private OAuthContextService $contextService,
        private OAuthInviteService $inviteService,
        private OAuthUserDataService $userDataService,
        private OAuthUserService $oauthUserService,
        private OAuthProviderServiceClass $oauthProviderService
    ) {
    }

    /**
     * Process OAuth redirect request
     * Stores context with UTM data and generates state token for security
     */
    public function processRedirect(string $provider, array $params): array
    {
        try {
            $providerService = OAuthProviderService::from($provider);
        } catch (\ValueError $e) {
            abort(400, "Invalid OAuth provider: {$provider}");
        }
        $intent = $params['intent'];
        $inviteToken = $params['invite_token'] ?? null;

        // Validate intent requirements
        $this->validateIntentRequirements($intent, $providerService);

        // Handle invite validation for auth flows
        $invitedEmail = null;
        if ($inviteToken && $intent === self::INTENT_AUTH) {
            $invitedEmail = $this->inviteService->getInvitedEmail($inviteToken);
        }

        // Store context and get state token
        $stateToken = $this->contextService->storeContext([
            'intent' => $intent,
            'utm_data' => $params['utm_data'] ?? null,
            'invited_email' => $invitedEmail,
            'invite_token' => $inviteToken,
            'intention' => $params['intention'] ?? null,
            'autoClose' => $params['autoClose'] ?? false,
        ]);

        // Configure OAuth driver
        $driver = $providerService->getDriver();
        $scopes = $driver->getScopesForIntent($intent);

        // Generic approach: Set email restrictions if supported
        $this->inviteService->configureDriverEmailRestrictions($driver, $invitedEmail);

        // Set scopes
        if (!empty($scopes)) {
            $driver->setScopes($scopes);
        }

        // Set state parameter for OAuth flow
        $driver->setState($stateToken);

        return [
            'url' => $driver->getRedirectUrl(),
            'state' => $stateToken
        ];
    }

    /**
     * Process OAuth callback
     * Retrieves context from state token and creates/authenticates user
     */
    public function processCallback(string $provider, array $params): JsonResponse
    {
        try {
            $providerService = OAuthProviderService::from($provider);
        } catch (\ValueError $e) {
            return response()->json(['error' => "Invalid OAuth provider: {$provider}"], 400);
        }

        // Get user data from OAuth provider
        $userData = $this->userDataService->extractFromRedirect($providerService);

        // Get context using state token from OAuth callback
        $stateToken = $params['state'] ?? null;
        $context = $this->contextService->getContext($stateToken);

        if (!$context) {
            return response()->json(['error' => 'OAuth context expired or invalid state'], 419);
        }

        $intent = $context['intent'];
        $invitedEmail = $context['invited_email'] ?? null;
        $inviteToken = $params['invite_token'] ?? $context['invite_token'] ?? null;

        $result = $this->handleIntent($intent, $providerService, $userData, $inviteToken, $invitedEmail);
        $this->contextService->clearContext($stateToken);

        return $result;
    }

    /**
     * Process widget-based OAuth callback
     * Handles widget flows (Google One Tap) that don't use state tokens
     * Extracts and stores context for user creation
     */
    public function processWidgetCallback(string $service, Request $request): JsonResponse
    {
        try {
            $providerService = OAuthProviderService::from($service);
        } catch (\ValueError $e) {
            return response()->json(['error' => "Invalid OAuth provider: {$service}"], 400);
        }
        $intent = $request->input('intent');
        $inviteToken = $request->input('invite_token');

        // Validate intent requirements
        try {
            $this->validateIntentRequirements($intent, $providerService);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        // Handle invite validation for auth flows
        $invitedEmail = null;
        if ($inviteToken && $intent === self::INTENT_AUTH) {
            $invitedEmail = $this->inviteService->getInvitedEmail($inviteToken);
        }

        // Get user data from widget
        $userData = $this->userDataService->extractFromWidget($providerService, $request);

        // Store widget context for later retrieval during user creation
        $context = [
            'intent' => $intent,
            'utm_data' => $request->input('utm_data'),
            'invite_token' => $inviteToken,
            'invited_email' => $invitedEmail,
        ];
        $this->contextService->storeWidgetContext($context);

        $result = $this->handleIntent($intent, $providerService, $userData, $inviteToken, $invitedEmail);
        $this->contextService->clearWidgetContext();

        return $result;
    }

    /**
     * Handle intent-based flow
     */
    private function handleIntent(
        string $intent,
        OAuthProviderService $providerService,
        array $userData,
        ?string $inviteToken = null,
        ?string $invitedEmail = null
    ): JsonResponse {
        return match ($intent) {
            self::INTENT_AUTH => $this->handleAuthenticationFlow(
                $providerService,
                $userData,
                $inviteToken,
                $invitedEmail
            ),
            self::INTENT_INTEGRATION => $this->handleIntegrationFlow(
                $providerService,
                $userData
            ),
            default => response()->json(['error' => 'Invalid intent'], 400)
        };
    }

    /**
     * Handle authentication flow (create user + authenticate)
     * OAuthUserService will retrieve UTM data directly from context service
     */
    private function handleAuthenticationFlow(
        OAuthProviderService $providerService,
        array $userData,
        ?string $inviteToken = null,
        ?string $invitedEmail = null
    ): JsonResponse {
        // Generic email validation (no provider-specific logic)
        try {
            $this->inviteService->validateEmailRestrictions($userData['email'], $invitedEmail);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        // Find or create user
        $user = $this->oauthUserService->findOrCreateUser($userData, $providerService, $inviteToken);

        // Create/update OAuth provider record
        $this->oauthProviderService->createOrUpdateProvider($user, $providerService, $userData);

        // sendLoginResponse() automatically handles 2FA check and blocked user check
        return $this->sendLoginResponse($user, [
            'method' => 'oauth',
            'provider' => $providerService->value,
        ]);
    }

    /**
     * Handle integration flow (connect OAuth provider to existing user)
     */
    private function handleIntegrationFlow(OAuthProviderService $providerService, array $userData): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Authentication required for integration connections'], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Create/update OAuth provider record
        $oauthProvider = $this->oauthProviderService->createOrUpdateProvider(
            $user,
            $providerService,
            $userData
        );

        // Retrieve context data (autoClose and intention from stored context)
        $context = $this->contextService->getContext() ?? [];

        return response()->json([
            'provider' => OAuthProviderResource::make($oauthProvider),
            'autoClose' => $context['autoClose'] ?? false,
            'intention' => $context['intention'] ?? null,
        ]);
    }

    /**
     * Validate intent requirements
     */
    private function validateIntentRequirements(string $intent, OAuthProviderService $providerService): void
    {
        if ($intent === self::INTENT_INTEGRATION && !Auth::check()) {
            abort(401, 'Integration requires authentication');
        }

        if (!$providerService->supportsIntent($intent)) {
            abort(400, "Service {$providerService->value} does not support intent {$intent}");
        }
    }
}
