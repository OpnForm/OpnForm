<?php

namespace App\Mcp\Tools\Forms;

use App\Concerns\NormalizesFormProperties;
use App\Models\Forms\Form;
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

#[Description('Create a new form. With authentication and a workspace_id the form is saved and a share URL is returned. Without authentication, returns draft JSON for the agent to hold until the user registers.')]
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
            Gate::forUser($user)->authorize('create', [Form::class, $workspace]);
        }

        $this->validateProperties($properties, $workspace);

        if ($workspace && $user) {
            return $this->persistForm($user, $workspace, $validated, $properties);
        }

        return $this->returnDraftJson($validated, $properties);
    }

    private function validateProperties(array $properties, ?Workspace $workspace): void
    {
        $validator = Validator::make(
            ['properties' => $properties],
            ['properties' => ['required', 'array', new FormPropertiesRule($workspace)]]
        );

        $validator->validate();
    }

    private function persistForm($user, Workspace $workspace, array $validated, array $properties): ResponseFactory
    {
        $formData = [
            'title' => $validated['title'],
            'workspace_id' => $workspace->id,
            'properties' => $properties,
            'visibility' => $validated['visibility'] ?? 'draft',
            'creator_id' => $user->id,
        ];

        $cleaner = (new FormCleaner())->processData($formData);
        $formData = $cleaner->performCleaning($workspace)->getData();
        $formData['workspace_id'] = $workspace->id;
        $formData['creator_id'] = $user->id;

        $form = Form::create($formData);

        $editorUrl = rtrim(config('app.front_url', config('app.url')), '/') . '/forms/' . $form->slug . '/edit';

        $result = [
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'visibility' => $form->visibility,
            'editor_url' => $editorUrl,
            'share_url' => $form->share_url,
        ];

        if ($cleaner->hasCleaned()) {
            $result['cleaning_warnings'] = $cleaner->getPerformedCleanings();
        }

        return Response::structured($result);
    }

    private function returnDraftJson(array $validated, array $properties): ResponseFactory
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

        $formData = [
            'title' => $validated['title'],
            'visibility' => 'draft',
            'properties' => $properties,
            'theme' => 'default',
            'size' => 'md',
            'border_radius' => 'small',
            'dark_mode' => 'auto',
            'width' => 'centered',
            'presentation_style' => 'classic',
        ];

        $registerUrl = rtrim(config('app.front_url', config('app.url')), '/') . '/register';

        return Response::structured([
            'form_data' => $formData,
            'register_url' => $registerUrl,
            'next_steps' => implode("\n", [
                '1. Tell the user: "Your form is ready! Open this link to create your OpnForm account: ' . $registerUrl . '"',
                '2. Tell the user: "After registering, go to Settings > API Tokens, create a token, and provide it to configure the MCP connection."',
                '3. Once the user provides the token, reconfigure the MCP connection with auth.',
                '4. Call list-workspaces to get the user\'s workspace_id.',
                '5. Call create-form with the form_data above and the workspace_id to save the form.',
                '6. Return the share_url to the user.',
            ]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()
                ->description('The workspace to create the form in. Omit to get a draft JSON instead. Use list-workspaces to find workspace IDs.'),
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
