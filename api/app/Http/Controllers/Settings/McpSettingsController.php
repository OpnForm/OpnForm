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
        McpConnectionConfiguration $connection,
    ): JsonResponse {
        if (config('app.self_hosted')) {
            Gate::authorize('manage-instance-settings');
        }

        return response()->json($this->payload($availability, $connection));
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

        return response()->json($this->payload($availability, $connection, $readinessResult));
    }

    /**
     * @param  array{ready: bool, blockers: array<int, array{code: string, message: string}>}|null  $readiness
     */
    private function payload(
        McpAvailability $availability,
        McpConnectionConfiguration $connection,
        ?array $readiness = null,
    ): array {
        $status = $availability->status($readiness);

        return array_merge($status, [
            'self_hosted' => (bool) config('app.self_hosted'),
            'source' => $status['configured_value'] === null ? 'environment' : 'settings',
        ], $connection->forInstance());
    }
}
