<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class FeatureFlagsController extends Controller
{
    public function index()
    {
        $featureFlags = Cache::remember('feature_flags', 3600, function () {
            return [
                'self_hosted' => config('app.self_hosted', true),
                'setup_required' => config('app.self_hosted', true) && !\App\Models\User::max('id'),
                'custom_domains' => config('custom-domains.enabled', false),
                'ai_features' => !empty(config('services.openai.api_key')),
                'version' => $this->getAppVersion(),

                'billing' => [
                    'enabled' => !empty(config('cashier.key')) && !empty(config('cashier.secret')),
                    'appsumo' => !empty(config('services.appsumo.api_key')) && !empty(config('services.appsumo.api_secret')),
                    'stripe_publishable_key' => config('cashier.key'),
                ],
                'storage' => [
                    'local' => config('filesystems.default') === 'local',
                    's3' => config('filesystems.default') !== 'local',
                ],
                'services' => [
                    'unsplash' => !empty(config('services.unsplash.access_key')),
                    'google' => [
                        'fonts' => !empty(config('services.google.fonts_api_key')),
                        'auth' => !empty(config('services.google.client_id')) && !empty(config('services.google.client_secret')),
                        'client_id' => config('services.google.client_id'),
                    ],
                    'telegram' => [
                        'bot_id' => $this->extractTelegramBotId()
                    ]
                ],
                'integrations' => [
                    'zapier' => config('services.zapier.enabled'),
                    'google_sheets' => !empty(config('services.google.client_id')) && !empty(config('services.google.client_secret')),
                    'telegram' => !empty(config('services.telegram.bot_token')),
                ],
                'custom_code' => [
                    'enable_self_hosted' => (bool) config('opnform.custom_code.enable_self_hosted', false),
                ],
                'oidc' => [
                    'available' => $this->isOidcAvailable(),
                    'forced' => config('oidc.force_login', false) && $this->isOidcAvailable(),
                ],
            ];
        });

        return response()->json($featureFlags);
    }

    /**
     * Check if OIDC is available (at least one enabled connection exists).
     */
    private function isOidcAvailable(): bool
    {
        return \App\Enterprise\Oidc\Models\IdentityConnection::enabled()
            ->where('type', \App\Enterprise\Oidc\Models\IdentityConnection::TYPE_OIDC)
            ->exists();
    }

    /**
     * Get the application version from Docker environment or fallback
     */
    private function getAppVersion(): ?string
    {
        // Only return version for self-hosted installations
        if (!config('app.self_hosted', true)) {
            return null;
        }

        return config('app.docker_version');
    }

    /**
     * Extract bot ID from Telegram bot token
     */
    private function extractTelegramBotId(): string|false
    {
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            return false;
        }

        // Bot token format: 1234567890:ABCdefGHIjklMNOpqrsTUVwxyz
        // Extract the bot ID (everything before the colon)
        $parts = explode(':', $botToken);

        return $parts[0] ?? false;
    }
}
