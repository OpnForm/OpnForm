<?php

namespace App\Mcp\Tools\Forms;

use App\Concerns\NormalizesFormProperties;
use App\Mcp\Concerns\ResolvesForm;
use App\Rules\FormPropertiesRule;
use App\Service\Forms\FormCleaner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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
    use NormalizesFormProperties;
    use ResolvesForm;

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

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => 'required',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('update', $form);

        $updateData = collect($request->all())
            ->except(['form_id'])
            ->only(self::ALLOWED_FIELDS)
            ->all();

        if (empty($updateData)) {
            return Response::error('No valid fields provided to update. Provide at least one field like title, visibility, or properties.');
        }

        if (isset($updateData['title']) && is_string($updateData['title'])) {
            $updateData['title'] = Str::substr(trim($updateData['title']), 0, 255);
        }

        if (isset($updateData['properties'])) {
            $updateData['properties'] = $this->normalizeProperties($updateData['properties'], backfillIds: true);

            $this->validateProperties($updateData['properties'], $form->workspace);

            $this->applyCommonCleaning($updateData);

            $newPropertyIds = collect($updateData['properties'])->pluck('id')->flip()->all();
            $updateData['removed_properties'] = array_merge(
                $form->removed_properties ?? [],
                collect($form->properties)->filter(function ($field) use ($newPropertyIds) {
                    return !Str::of($field['type'])->startsWith('nf-') && !isset($newPropertyIds[$field['id']]);
                })->values()->toArray()
            );
        }

        $cleaner = (new FormCleaner())->processData($updateData);
        $cleaned = $cleaner->performCleaning($form->workspace)->getData();

        foreach (array_keys($updateData) as $key) {
            if (array_key_exists($key, $cleaned)) {
                $updateData[$key] = $cleaned[$key];
            }
        }

        $form->update($updateData);

        $result = [
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'share_url' => $form->share_url,
            'visibility' => $form->visibility,
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];

        if ($cleaner->hasCleaned()) {
            $result['cleaning_warnings'] = $cleaner->getPerformedCleanings();
        }

        return Response::structured($result);
    }

    private function validateProperties(array $properties, $workspace): void
    {
        $validator = Validator::make(
            ['properties' => $properties],
            ['properties' => ['required', 'array', new FormPropertiesRule($workspace)]]
        );

        $validator->validate();
    }

    private function applyCommonCleaning(array &$data): void
    {
        if (empty($data['properties']) || !is_array($data['properties'])) {
            return;
        }

        foreach ($data['properties'] as $index => &$property) {
            if (($property['type'] ?? null) === 'nf-text' && isset($property['content'])) {
                $property['content'] = Purify::clean($property['content']);
            }
        }
        unset($property);
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
}
