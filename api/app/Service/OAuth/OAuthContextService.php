<?php

namespace App\Service\OAuth;

use Illuminate\Support\Facades\Cache;

/**
 * OAuthContextService
 *
 * Manages OAuth flow context and metadata across authentication attempts.
 * Handles storage and retrieval of temporary data needed during OAuth callbacks:
 * - State tokens (for security in redirect-based flows)
 * - UTM tracking data (for user acquisition attribution)
 * - Invite tokens (for workspace invitations)
 * - Intent flags (auth vs integration)
 *
 * Uses cache for temporary storage with 5-minute TTL to prevent stale data.
 * Supports both redirect-based OAuth flows (with state tokens) and widget-based flows.
 */
class OAuthContextService
{
    private const CACHE_TTL_MINUTES = 5;
    private const REDIRECT_CONTEXT_PREFIX = 'oauth-context:state:';
    private const WIDGET_CONTEXT_PREFIX = 'oauth-context:widget:';

    /**
     * Store OAuth context with a unique state token
     * Used for redirect-based OAuth flows (Google OAuth, GitHub, etc.)
     *
     * @param array $context {
     *     @type string $intent 'auth' or 'integration'
     *     @type array|null $utm_data UTM parameters for tracking
     *     @type string|null $invited_email Email address for invite validation
     *     @type string|null $invite_token Token for workspace invitations
     *     @type string|null $intention User's intention/purpose
     *     @type bool $autoClose Whether popup should auto-close
     * }
     * @return string State token for OAuth callback
     */
    public function storeContext(array $context): string
    {
        // Generate a unique state token for this OAuth flow
        $stateToken = bin2hex(random_bytes(16));
        $key = self::REDIRECT_CONTEXT_PREFIX . $stateToken;

        Cache::put($key, $context, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $stateToken;
    }

    /**
     * Store widget context with session-based key
     * Used for widget-based OAuth flows (Google One Tap, etc.)
     *
     * @param array $context OAuth context data
     * @return string Session ID key for retrieval
     */
    public function storeWidgetContext(array $context): string
    {
        $key = self::WIDGET_CONTEXT_PREFIX . session()->getId();
        Cache::put($key, $context, now()->addMinutes(self::CACHE_TTL_MINUTES));
        return $key;
    }

    /**
     * Get context from cache using state token
     */
    public function getContext(?string $stateToken = null): ?array
    {
        $stateToken = $stateToken ?? request()->input('state');

        if (!$stateToken) {
            return null;
        }

        $key = self::REDIRECT_CONTEXT_PREFIX . $stateToken;
        return Cache::get($key);
    }

    /**
     * Get widget context from cache using session ID
     */
    public function getWidgetContext(): ?array
    {
        $key = self::WIDGET_CONTEXT_PREFIX . session()->getId();
        return Cache::get($key);
    }

    /**
     * Clear context after use
     */
    public function clearContext(?string $stateToken = null): void
    {
        $stateToken = $stateToken ?? request()->input('state');

        if ($stateToken) {
            $key = self::REDIRECT_CONTEXT_PREFIX . $stateToken;
            Cache::forget($key);
        }
    }

    /**
     * Clear widget context after use
     */
    public function clearWidgetContext(): void
    {
        $key = self::WIDGET_CONTEXT_PREFIX . session()->getId();
        Cache::forget($key);
    }

    /**
     * Determine intent from stored context
     */
    public function getIntent(): string
    {
        $context = $this->getContext();
        if (!isset($context['intent'])) {
            abort(419, 'OAuth context expired');
        }
        return $context['intent'];
    }

    /**
     * Get invited email from context
     */
    public function getInvitedEmail(): ?string
    {
        $context = $this->getContext();
        return $context['invited_email'] ?? null;
    }

    /**
     * Get invite token from context if present
     */
    public function getInviteToken(): ?string
    {
        $context = $this->getContext();
        return $context['invite_token'] ?? null;
    }

    /**
     * Get UTM data from context
     * Retrieves tracking data (source, medium, campaign, etc.) for user attribution
     */
    public function getUtmData(): ?array
    {
        $context = $this->getContext();
        return $context['utm_data'] ?? null;
    }
}
