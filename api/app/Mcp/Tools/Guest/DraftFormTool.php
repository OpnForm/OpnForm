<?php

namespace App\Mcp\Tools\Guest;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Build a form draft without authentication. Returns validated form JSON that can be saved later with create-form after the user registers. No data is persisted — the AI agent should hold onto the returned form_data.')]
#[IsReadOnly]
class DraftFormTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'properties' => 'required|array|min:1',
        ]);

        $properties = collect($validated['properties'])->map(function ($field) {
            if (empty($field['id'])) {
                $field['id'] = Str::uuid()->toString();
            }
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
            'title' => $schema->string()
                ->description('The form title.')
                ->required(),
            'properties' => $schema->array()
                ->description('Array of form field objects. Each field needs at least "type" and "name". Use the field-types resource for available types.')
                ->required(),
        ];
    }
}
