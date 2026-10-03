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

        return $this->resolveEnabled($this->configuredValue());
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

    /**
     * Return a consistent settings-page snapshot without repeating database and
     * OAuth readiness checks during the same request.
     *
     * @param  array{ready: bool, blockers: array<int, array{code: string, message: string}>}|null  $readiness
     * @return array{enabled: bool, available: bool, configured_value: ?bool, ready: bool, blockers: array<int, array{code: string, message: string}>}
     */
    public function status(?array $readiness = null): array
    {
        if (! config('app.self_hosted')) {
            return [
                'enabled' => true,
                'available' => true,
                'configured_value' => null,
                'ready' => true,
                'blockers' => [],
            ];
        }

        $configuredValue = $this->configuredValue();
        $enabled = $this->resolveEnabled($configuredValue);
        $readiness ??= $this->readiness->inspect();

        return [
            'enabled' => $enabled,
            'available' => $enabled && $readiness['ready'],
            'configured_value' => $configuredValue,
            ...$readiness,
        ];
    }

    public function guestDraftsEnabled(): bool
    {
        return ! config('app.self_hosted') && $this->enabled();
    }

    private function resolveEnabled(?bool $configuredValue): bool
    {
        return $configuredValue ?? (bool) config('opnform.mcp.enabled', false);
    }
}
