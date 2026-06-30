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

#[Description('Create a new form in a workspace. Requires workspace_id, title, and properties (array of field objects). Each field needs at minimum: type, name. Use the field-types resource to see available field types and their options.')]
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
            'workspace_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'properties' => 'required|array|min:1',
            'visibility' => 'string|in:public,draft,closed',
        ]);

        $user = $request->user();
        $workspace = Workspace::findOrFail($validated['workspace_id']);

        Gate::forUser($user)->authorize('ownsWorkspace', $workspace);
        Gate::forUser($user)->authorize('create', [Form::class, $workspace]);

        $properties = collect($validated['properties'])->map(function ($field) {
            if (empty($field['id'])) {
                $field['id'] = Str::uuid()->toString();
            }

            return $field;
        })->values()->all();

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

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()
                ->description('The workspace to create the form in. Use list-workspaces to find workspace IDs.')
                ->required(),
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
