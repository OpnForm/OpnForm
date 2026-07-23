<?php

namespace App\Mcp\Tools\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List all forms in a workspace. Returns form IDs, titles, slugs, visibility, submission/view counts, and share URLs.')]
#[IsReadOnly]
#[IsIdempotent]
class ListFormsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer',
        ]);

        $user = $request->user();
        $workspace = Workspace::findOrFail($validated['workspace_id']);

        Gate::forUser($user)->authorize('ownsWorkspace', $workspace);
        Gate::forUser($user)->authorize('viewAny', Form::class);

        $forms = $workspace->forms()
            ->select(['id', 'slug', 'title', 'visibility', 'workspace_id', 'custom_domain', 'created_at', 'updated_at'])
            ->withCount(['submissions as submissions_count' => fn ($q) => $q->where('status', FormSubmission::STATUS_COMPLETED)])
            ->withTotalViews()
            ->orderByDesc('updated_at')
            ->get();

        $data = $forms->map(fn (Form $form) => [
            'id' => $form->id,
            'title' => $form->title,
            'slug' => $form->slug,
            'visibility' => $form->visibility,
            'submissions_count' => $form->submissions_count,
            'views_count' => $form->views_count,
            'share_url' => $form->share_url,
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ])->values()->all();

        return Response::structured(['forms' => $data]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()
                ->description('The workspace ID to list forms for. Use list-workspaces to find available workspace IDs.')
                ->required(),
        ];
    }
}
