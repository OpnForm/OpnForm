<?php

namespace App\Service\Telemetry;

use App\Enums\SettingsKey;
use App\Models\Setting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class TelemetryService
{
    /**
     * Check if telemetry should be sent.
     *
     * Telemetry only runs when BOTH production environment AND self-hosted mode
     * are enabled. It can be explicitly disabled via OPNFORM_ANONYMOUS_TELEMETRY_DISABLED.
     *
     * @return bool
     */
    public function shouldSendTelemetry(): bool
    {
        // Explicitly disabled via environment variable
        if (!config('telemetry.enabled', true)) {
            return false;
        }

        // Only enable in production AND self-hosted mode
        $isProduction = App::isProduction();
        $isSelfHosted = config('app.self_hosted', false);

        return $isProduction && $isSelfHosted;
    }

    /**
     * Get the instance ID from settings.
     *
     * Instance ID is cached since it's read frequently (on every telemetry event)
     * but only set once during initialization and never changes.
     *
     * @return string|null
     */
    public function getInstanceId(): ?string
    {
        return Cache::rememberForever('telemetry.instance_id', function () {
            return Setting::get(SettingsKey::INSTANCE_ID);
        });
    }

    /**
     * Get the telemetry endpoint URL.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return config('telemetry.endpoint');
    }

    /**
     * Get the OpenPanel client ID.
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return config('telemetry.client_id');
    }

    /**
     * Get the OpenPanel client secret.
     *
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return config('telemetry.client_secret');
    }

    /**
     * Get the application version.
     *
     * @return string|null
     */
    public function getAppVersion(): ?string
    {
        return config('app.docker_version');
    }

    /**
     * Create a configured OpenPanel client instance.
     *
     * @return OpenPanelClient
     */
    public function createClient(): OpenPanelClient
    {
        return new OpenPanelClient(
            $this->getEndpoint(),
            $this->getClientId(),
            $this->getClientSecret()
        );
    }
}
