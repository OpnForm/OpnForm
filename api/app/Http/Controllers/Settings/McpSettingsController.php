<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SettingsKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMcpSettingsRequest;
use App\Models\Setting;
use App\Support\Mcp\McpAvailability;
use App\Support\Mcp\McpConnectionConfiguration;
use App\Support\Mcp\McpOAuthReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class McpSettingsController extends Controller
{
    public function show(
        McpAvailability $availability,
        McpOAuthReadiness $readiness,
        McpConnectionConfiguration $connection,
    ): JsonResponse {
        Gate::authorize('manage-instance-settings');

        return response()->json($this->payload($availability, $readiness, $connection));
    }

    public function update(
        UpdateMcpSettingsRequest $request,
        McpAvailability $availability,
        McpOAuthReadiness $readiness,
        McpConnectionConfiguration $connection,
    ): JsonResponse {
        $enabled = (bool) $request->validated('enabled');
        $readinessResult = $readiness->inspect();

        if ($enabled && ! $readinessResult['ready']) {
            return response()->json([
                'message' => 'Complete the MCP OAuth prerequisites before enabling MCP.',
                'blockers' => $readinessResult['blockers'],
            ], 422);
        }

        Setting::set(SettingsKey::MCP_ENABLED, $enabled);

        return response()->json($this->payload($availability, $readiness, $connection));
    }

    private function payload(
        McpAvailability $availability,
        McpOAuthReadiness $readiness,
        McpConnectionConfiguration $connection,
    ): array {
        $configuredValue = $availability->configuredValue();

        return array_merge([
            'enabled' => $availability->enabled(),
            'configured_value' => $configuredValue,
            'source' => $configuredValue === null ? 'environment' : 'settings',
        ], $readiness->inspect(), $connection->forSelfHostedInstance());
    }
}
