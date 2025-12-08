<?php

namespace App\Http\Controllers\Settings;

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Requests\StoreOidcConnectionRequest;
use App\Enterprise\Oidc\Requests\UpdateOidcConnectionRequest;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Service\Telemetry\TelemetryEvent;

class OidcConnectionController extends Controller
{
    /**
     * List OIDC connections for a workspace.
     */
    public function index(Workspace $workspace)
    {
        $this->authorize('viewAny', [IdentityConnection::class, $workspace]);

        // Show workspace-scoped and global connections
        // Eager load workspace relationship to prevent N+1 queries
        $connections = IdentityConnection::with('workspace')
            ->where(function ($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id)
                    ->orWhereNull('workspace_id');
            })
            ->get()
            ->map(function ($connection) {
                return $this->formatConnection($connection);
            });

        return response()->json($connections);
    }

    /**
     * Store a new OIDC connection.
     */
    public function store(StoreOidcConnectionRequest $request, Workspace $workspace)
    {
        $this->authorize('create', [IdentityConnection::class, $workspace]);

        $data = $request->validated();

        // Enforce single OIDC connection per workspace
        $existing = IdentityConnection::where('workspace_id', $workspace->id)
            ->where('type', IdentityConnection::TYPE_OIDC)
            ->first();

        if ($existing) {
            abort(422, 'This workspace already has an OIDC connection. Please update or delete the existing one.');
        }

        $data['workspace_id'] = $workspace->id;

        // Generate redirect URL if not provided
        if (empty($data['redirect_path'])) {
            $data['redirect_path'] = front_url("/auth/{$data['slug']}/callback");
        }

        $connection = IdentityConnection::create($data);

        telemetry(TelemetryEvent::SSO_CREATED);

        return response()->json($this->formatConnection($connection), 201);
    }

    /**
     * Show a specific OIDC connection.
     */
    public function show(Workspace $workspace, IdentityConnection $connection)
    {
        $this->authorize('view', $connection);

        return response()->json($this->formatConnection($connection));
    }

    /**
     * Update an OIDC connection.
     */
    public function update(UpdateOidcConnectionRequest $request, Workspace $workspace, IdentityConnection $connection)
    {
        $this->authorize('update', $connection);

        $data = $request->validated();

        // Don't update client_secret if not provided
        if (!isset($data['client_secret'])) {
            unset($data['client_secret']);
        }

        // Merge options if provided (to preserve other options)
        if (isset($data['options']) && is_array($data['options'])) {
            $existingOptions = $connection->options ?? [];
            $data['options'] = array_merge($existingOptions, $data['options']);
        }

        $connection->update($data);

        return response()->json($this->formatConnection($connection));
    }

    /**
     * Delete an OIDC connection.
     */
    public function destroy(Workspace $workspace, IdentityConnection $connection)
    {
        $this->authorize('delete', $connection);

        $connection->delete();

        return response()->json([], 204);
    }

    /**
     * Format connection for API response (hide sensitive data).
     */
    protected function formatConnection(IdentityConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'workspace_id' => $connection->workspace_id,
            'name' => $connection->name,
            'slug' => $connection->slug,
            'domain' => $connection->domain,
            'type' => $connection->type,
            'issuer' => $connection->issuer,
            'client_id' => $connection->client_id,
            // Never expose client_secret
            'scopes' => $connection->scopes,
            'options' => $connection->options, // Includes group_role_mappings
            'redirect_url' => $connection->redirect_url,
            'enabled' => $connection->enabled,
            'created_at' => $connection->created_at,
            'updated_at' => $connection->updated_at,
        ];
    }
}
