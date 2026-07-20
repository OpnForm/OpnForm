<?php

namespace App\Http\Controllers\Integrations\Make;

use App\Http\Resources\Zapier\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class ListWorkspacesController
{
    use AuthorizesRequests;

    public function __invoke()
    {
        $this->authorize('viewAny', Workspace::class);

        return WorkspaceResource::collection(
            Auth::user()->workspaces()->get()
        );
    }
}
