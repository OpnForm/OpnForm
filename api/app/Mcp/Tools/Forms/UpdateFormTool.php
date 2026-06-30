<?php

namespace App\Mcp\Tools\Forms;

use App\Models\Forms\Form;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Update an existing form. Pass form_id and any fields to change (title, visibility, properties, theme, color, etc.). Only provided fields are updated.')]
#[IsIdempotent]
class UpdateFormTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => 'required',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('update', $form);

        $updateData = collect($request->all())
            ->except(['form_id'])
            ->only(array_merge(
                (new Form())->getFillable(),
                ['properties']
            ))
            ->all();

        if (empty($updateData)) {
            return Response::error('No valid fields provided to update. Provide at least one field like title, visibility, or properties.');
        }

        if (isset($updateData['properties'])) {
            $newPropertyIds = collect($updateData['properties'])->pluck('id')->flip()->all();
            $updateData['removed_properties'] = array_merge(
                $form->removed_properties ?? [],
                collect($form->properties)->filter(function ($field) use ($newPropertyIds) {
                    return !Str::of($field['type'])->startsWith('nf-') && !isset($newPropertyIds[$field['id']]);
                })->values()->toArray()
            );
        }

        $form->update($updateData);

        return Response::structured([
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'share_url' => $form->share_url,
            'visibility' => $form->visibility,
            'updated_at' => $form->updated_at?->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->string()
                ->description('The form ID (integer) or slug to update.')
                ->required(),
            'title' => $schema->string()
                ->description('New form title.'),
            'visibility' => $schema->string()
                ->enum(['public', 'draft', 'closed'])
                ->description('Form visibility.'),
            'properties' => $schema->array()
                ->description('Updated array of form field objects. Replaces all existing fields.'),
            'theme' => $schema->string()
                ->description('Form theme: default, simple, notion, minimal, transparent.'),
            'color' => $schema->string()
                ->description('Primary color hex code (e.g. "#3B82F6").'),
            'dark_mode' => $schema->string()
                ->description('Dark mode setting: auto, light, dark.'),
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
