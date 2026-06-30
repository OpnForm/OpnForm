<?php

namespace App\Mcp\Tools\Workspaces;

use App\Models\UserWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use App\Models\Workspace;

#[Description('List all workspaces the authenticated user belongs to. Returns workspace IDs needed for other tools like create-form and list-forms.')]
#[IsReadOnly]
#[IsIdempotent]
class ListWorkspacesTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAny', Workspace::class);

        $workspaces = UserWorkspace::where('user_id', $user->id)->with('workspace')->get();

        $data = $workspaces->map(fn(UserWorkspace $uw) => [
            'id' => $uw->workspace->id,
            'name' => $uw->workspace->name,
            'icon' => $uw->workspace->icon,
        ])->values()->all();

        return Response::structured(['workspaces' => $data]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
