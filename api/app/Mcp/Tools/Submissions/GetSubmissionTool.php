<?php

namespace App\Mcp\Tools\Submissions;

use App\Mcp\Concerns\ResolvesForm;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get a single submission by ID for a specific form. Returns the full submission data including all field responses.')]
#[IsReadOnly]
#[IsIdempotent]
class GetSubmissionTool extends Tool
{
    use ResolvesForm;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => 'required',
            'submission_id' => 'required|integer',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('view', $form);

        $submission = $form->submissions()
            ->where('id', $validated['submission_id'])
            ->firstOrFail();

        return Response::structured([
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'data' => $submission->data,
            'status' => $submission->status,
            'created_at' => $submission->created_at?->toIso8601String(),
            'completion_time' => $submission->completion_time,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->string()
                ->description('The form ID (integer) or slug.')
                ->required(),
            'submission_id' => $schema->integer()
                ->description('The submission ID.')
                ->required(),
        ];
    }
}
