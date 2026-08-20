<?php

namespace App\Support\Mcp;

use App\Enums\SettingsKey;
use App\Models\Setting;

final class McpAvailability
{
    public function __construct(private readonly McpOAuthReadiness $readiness)
    {
    }

    public function enabled(): bool
    {
        if (! config('app.self_hosted')) {
            return true;
        }

        $stored = $this->configuredValue();

        return $stored ?? (bool) config('opnform.mcp.enabled', false);
    }

    public function configuredValue(): ?bool
    {
        $stored = Setting::get(SettingsKey::MCP_ENABLED);

        return is_bool($stored) ? $stored : null;
    }

    public function available(): bool
    {
        if (! config('app.self_hosted')) {
            return true;
        }

        return $this->enabled() && $this->readiness->inspect()['ready'];
    }

    public function guestDraftsEnabled(): bool
    {
        return ! config('app.self_hosted') && $this->enabled();
    }
}
