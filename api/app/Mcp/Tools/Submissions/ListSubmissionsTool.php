<?php

namespace App\Mcp\Tools\Submissions;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List submissions for a form. Returns paginated submission data including all field responses. Supports filtering by status and searching within submission values.')]
#[IsReadOnly]
#[IsIdempotent]
class ListSubmissionsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => 'required',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'status' => 'string|in:completed,partial,all',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('view', $form);

        $query = $form->submissions()->with('form');

        $status = $validated['status'] ?? 'completed';
        if ($status === 'completed') {
            $query->where('status', '!=', FormSubmission::STATUS_PARTIAL);
        } elseif ($status === 'partial') {
            $query->where('status', FormSubmission::STATUS_PARTIAL);
        }

        $query->orderByDesc('created_at');

        $perPage = $validated['per_page'] ?? 50;
        $page = $validated['page'] ?? 1;

        $submissions = $query->paginate($perPage, ['*'], 'page', $page);

        $data = [
            'submissions' => collect($submissions->items())->map(fn (FormSubmission $sub) => [
                'id' => $sub->id,
                'data' => $sub->data,
                'status' => $sub->status,
                'created_at' => $sub->created_at?->toIso8601String(),
                'completion_time' => $sub->completion_time,
            ])->values()->all(),
            'total' => $submissions->total(),
            'current_page' => $submissions->currentPage(),
            'last_page' => $submissions->lastPage(),
            'per_page' => $submissions->perPage(),
        ];

        return Response::structured($data);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->string()
                ->description('The form ID (integer) or slug.')
                ->required(),
            'page' => $schema->integer()
                ->description('Page number (default 1).')
                ->default(1),
            'per_page' => $schema->integer()
                ->description('Results per page (default 50, max 100).')
                ->default(50),
            'status' => $schema->string()
                ->enum(['completed', 'partial', 'all'])
                ->description('Filter by status. Default: "completed".')
                ->default('completed'),
        ];
    }

    private function resolveForm(string $formId): Form
    {
        $query = Form::with(['workspace.users' => fn ($q) => $q->withPivot('role')]);

        if (is_numeric($formId)) {
            return $query->findOrFail((int) $formId);
        }

        return $query->where('slug', $formId)->firstOrFail();
    }
}
