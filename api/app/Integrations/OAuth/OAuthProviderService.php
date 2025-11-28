<?php

namespace App\Integrations\OAuth;

use App\Integrations\OAuth\Drivers\Contracts\OAuthDriver;
use App\Integrations\OAuth\Drivers\Contracts\WidgetOAuthDriver;
use App\Integrations\OAuth\Drivers\OAuthGoogleDriver;
use App\Integrations\OAuth\Drivers\OAuthGoogleOneTapDriver;
use App\Integrations\OAuth\Drivers\OAuthStripeDriver;
use App\Integrations\OAuth\Drivers\OAuthTelegramDriver;
use App\Service\OAuth\OAuthFlowOrchestrator;

enum OAuthProviderService: string
{
    case Google = 'google';
    case GoogleOneTap = 'google_one_tap';
    case Stripe = 'stripe';
    case Telegram = 'telegram';

    public function getDriver(): OAuthDriver|WidgetOAuthDriver
    {
        return match ($this) {
            self::Google =>  new OAuthGoogleDriver(),
            self::GoogleOneTap => new OAuthGoogleOneTapDriver(),
            self::Stripe =>  new OAuthStripeDriver(),
            self::Telegram => new OAuthTelegramDriver(),
        };
    }

    public function supportsIntent(string $intent): bool
    {
        return match ($this) {
            self::Google => in_array($intent, OAuthFlowOrchestrator::INTENTS),
            self::GoogleOneTap => $intent === OAuthFlowOrchestrator::INTENT_AUTH,
            self::Stripe => $intent === OAuthFlowOrchestrator::INTENT_INTEGRATION,
            self::Telegram => $intent === OAuthFlowOrchestrator::INTENT_INTEGRATION,
        };
    }

    /**
     * Get the normalized provider name for database storage.
     * Both Google and GoogleOneTap should be stored as 'google' since they use the same OAuth provider.
     */
    public function getDatabaseProvider(): string
    {
        return match ($this) {
            self::GoogleOneTap => 'google', // Override to normalize to 'google'
            default => $this->value, // Use enum value by default
        };
    }
}
