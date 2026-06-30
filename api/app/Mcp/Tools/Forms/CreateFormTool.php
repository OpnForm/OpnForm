<?php

namespace App\Mcp\Tools\Forms;

use App\Models\Forms\Form;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new form. With workspace_id and authentication the form is saved and a share URL is returned. Without authentication (or without workspace_id) a draft JSON is returned that the agent can save later after the user registers.')]
class CreateFormTool extends Tool
{
    private const ALLOWED_FIELDS = [
        'title',
        'visibility',
        'properties',
        'theme',
        'color',
        'dark_mode',
        'size',
        'border_radius',
        'width',
        'presentation_style',
        'language',
        'submit_button_text',
        'submitted_text',
        'redirect_url',
        're_fillable',
        'use_captcha',
        'confetti_on_submission',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => 'integer',
            'title' => 'required|string|max:255',
            'properties' => 'required|array|min:1',
            'visibility' => 'string|in:public,draft,closed',
        ]);

        $properties = collect($validated['properties'])->map(function ($field) {
            if (empty($field['id'])) {
                $field['id'] = Str::uuid()->toString();
            }

            return $field;
        })->values()->all();

        $user = $request->user();

        if ($user && !empty($validated['workspace_id'])) {
            return $this->persistForm($request, $user, $validated, $properties);
        }

        return $this->draftForm($validated, $properties);
    }

    private function persistForm(Request $request, $user, array $validated, array $properties): ResponseFactory
    {
        $workspace = Workspace::findOrFail($validated['workspace_id']);

        Gate::forUser($user)->authorize('ownsWorkspace', $workspace);
        Gate::forUser($user)->authorize('create', [Form::class, $workspace]);

        $formData = collect($request->all())
            ->only(self::ALLOWED_FIELDS)
            ->merge([
                'workspace_id' => $workspace->id,
                'properties' => $properties,
                'visibility' => $validated['visibility'] ?? 'draft',
                'creator_id' => $user->id,
            ])
            ->all();

        $form = Form::create($formData);

        return Response::structured([
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'share_url' => $form->share_url,
            'visibility' => $form->visibility,
        ]);
    }

    private function draftForm(array $validated, array $properties): ResponseFactory
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
                ->description('The workspace to create the form in. Omit to get a draft instead of persisting. Use list-workspaces to find workspace IDs.'),
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
