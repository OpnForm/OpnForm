<?php

namespace App\Mcp\Tools\Forms;

use App\Concerns\NormalizesFormProperties;
use App\Models\Workspace;
use App\Rules\FormPropertiesRule;
use App\Service\Forms\FormCleaner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new form draft. This tool never persists a live form; authenticated users may provide workspace_id so the draft can be validated and cleaned against workspace capabilities.')]
class CreateFormTool extends Tool
{
    use NormalizesFormProperties;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => 'integer',
            'title' => 'required|string|max:255',
            'properties' => 'required|array|min:1',
            'visibility' => 'string|in:public,draft,closed',
        ]);

        $properties = $this->normalizeProperties($validated['properties'], backfillIds: true);

        $user = $request->user();
        $workspace = null;

        if ($user && !empty($validated['workspace_id'])) {
            $workspace = Workspace::findOrFail($validated['workspace_id']);
            Gate::forUser($user)->authorize('ownsWorkspace', $workspace);
        }

        $this->validateProperties($properties, $workspace);

        return $this->returnDraftJson($validated, $properties, $workspace);
    }

    private function validateProperties(array $properties, ?Workspace $workspace): void
    {
        $validator = Validator::make(
            ['properties' => $properties],
            ['properties' => ['required', 'array', new FormPropertiesRule($workspace)]]
        );

        $validator->validate();
    }

    private function returnDraftJson(array $validated, array $properties, ?Workspace $workspace): ResponseFactory
    {
        $properties = collect($properties)->map(function ($field) {
            if (empty($field['hidden'])) {
                $field['hidden'] = false;
            }
            if (!isset($field['required'])) {
                $field['required'] = false;
            }

            return $field;
        })->values()->all();

        $cleaner = (new FormCleaner())->processData([
            'title' => $validated['title'],
            'visibility' => 'draft',
            'properties' => $properties,
        ]);
        $cleanedDraft = $workspace
            ? $cleaner->performCleaning($workspace)->getData()
            : $cleaner->getData();

        $formData = array_merge([
            'title' => $validated['title'],
            'visibility' => 'draft',
            'theme' => 'default',
            'size' => 'md',
            'border_radius' => 'small',
            'dark_mode' => 'auto',
            'width' => 'centered',
            'presentation_style' => 'classic',
        ], $cleanedDraft);

        $registerUrl = rtrim(config('app.front_url', config('app.url')), '/') . '/register';
        $builderUrl = rtrim(config('app.front_url', config('app.url')), '/') . '/forms/create';

        $result = [
            'form_data' => $formData,
            'builder_url' => $builderUrl,
            'register_url' => $registerUrl,
            'next_steps' => implode("\n", [
                '1. Tell the user the form draft is ready.',
                '2. Ask the user to open OpnForm and review the draft before saving or publishing.',
                '3. Do not claim the form was saved by this MCP tool.',
            ]),
        ];

        if ($workspace && $cleaner->hasCleaned()) {
            $result['cleaning_warnings'] = $cleaner->getPerformedCleanings();
        }

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()
                ->description('Optional workspace to validate and clean the draft against. This tool still returns a draft JSON and does not persist a form.'),
            'title' => $schema->string()
                ->description('The form title.')
                ->required(),
            'properties' => $schema->array()
                ->description('Array of form field objects. Each field needs at least "type" and "name". Use the field-types resource for available types.')
                ->required(),
            'visibility' => $schema->string()
                ->enum(['public', 'draft', 'closed'])
                ->description('Form visibility. Defaults to "draft".')
                ->default('draft'),
        ];
    }
}
