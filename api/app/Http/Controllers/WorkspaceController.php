<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\CustomDomainRequest;
use App\Http\Requests\Workspace\CustomCodeSettingsRequest;
use App\Http\Requests\Workspace\EmailSettingsRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorize('viewAny', Workspace::class);

        // Eager load users with pivot roles to prevent N+1 queries in WorkspaceResource
        // Make this query identical to UserController to avoid duplicate database work
        $user = Auth::user();
        if ($user instanceof \App\Models\User) {
            $user->load([
                'workspaces' => function ($query) {
                    $query->withPivot('role')->with([
                        'users' => function ($subQuery) {
                            $subQuery->withPivot('role');
                        }
                    ]);
                }
            ]);
        }

        $workspaces = $user->workspaces;

        return WorkspaceResource::collection($workspaces);
    }

    public function saveCustomDomain(CustomDomainRequest $request)
    {
        $this->authorize('adminAction', $request->workspace);
        if (!$request->workspace->is_pro) {
            return $this->error([
                'message' => 'A Pro plan is required to use this feature.',
            ], 403);
        }

        $request->workspace->custom_domains = $request->customDomains;
        $request->workspace->save();

        return new WorkspaceResource($request->workspace);
    }

    public function saveEmailSettings(EmailSettingsRequest $request)
    {
        $this->authorize('adminAction', $request->workspace);
        if (!$request->workspace->is_pro) {
            return $this->error([
                'message' => 'A Pro plan is required to use this feature.',
            ], 403);
        }

        $request->workspace->update(['settings' => array_merge($request->workspace->settings, ['email_settings' => $request->validated()])]);

        return new WorkspaceResource($request->workspace);
    }

    public function saveCustomCodeSettings(CustomCodeSettingsRequest $request)
    {
        $this->authorize('adminAction', $request->workspace);
        if (!$request->workspace->is_pro) {
            return $this->error([
                'message' => 'A Pro plan is required to use this feature.',
            ], 403);
        }

        $validated = $request->validated();
        $request->workspace->update([
            'settings' => array_merge($request->workspace->settings ?? [], [
                'custom_code' => $validated['custom_code'] ?? null,
                'custom_css' => $validated['custom_css'] ?? null,
            ]),
        ]);

        return new WorkspaceResource($request->workspace);
    }

    public function delete(Workspace $workspace)
    {
        $this->authorize('delete', $workspace);

        $id = $workspace->id;
        $workspace->delete();

        return $this->success([
            'message' => 'Workspace successfully deleted.',
            'workspace_id' => $id,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Workspace::class);

        $user   = $request->user();

        $this->validate($request, [
            'name' => 'required',
        ]);

        // Create workspace
        $workspace = Workspace::create([
            'name' => $request->name,
            'icon' => ($request->emoji) ? $request->emoji : '',
        ]);

        // Add relation with user
        $user->workspaces()->sync([
            $workspace->id => [
                'role' => 'admin',
            ],
        ], false);

        return $this->success([
            'message' => 'Workspace created.',
            'workspace_id' => $workspace->id,
            'workspace' => new WorkspaceResource($workspace),
        ]);
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $this->validate($request, [
            'name' => 'required',
        ]);

        $workspace->update([
            'name' => $request->name,
            'icon' => $request->emoji ?? '',
        ]);

        return $this->success([
            'message' => 'Workspace updated.',
            'workspace' => new WorkspaceResource($workspace),
        ]);
    }
}
