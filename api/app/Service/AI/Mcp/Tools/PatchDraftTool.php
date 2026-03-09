<?php

namespace App\Service\AI\Mcp\Tools;

use App\Service\AI\Mcp\AppsUiMetaService;
use App\Service\AI\Mcp\ChatGptDraftsService;
use App\Service\AI\Mcp\FormStateNormalizationService;
use App\Service\AI\Mcp\FormStateValidationService;
use App\Service\AI\Mcp\GuideTokenService;
use RuntimeException;

class PatchDraftTool extends McpTool
{
    public function __construct(
        private readonly ChatGptDraftsService $drafts,
        private readonly GuideTokenService $guideTokenService,
        private readonly FormStateNormalizationService $formStateNormalizationService,
        private readonly FormStateValidationService $formStateValidationService,
        private readonly AppsUiMetaService $appsUiMetaService
    ) {
    }

    public function name(): string
    {
        return 'patch_draft';
    }

    public function title(): string
    {
        return 'Patch Draft';
    }

    public function description(): string
    {
        return 'Partially update a draft without sending full form_state. Supports config updates and indexed property add/update/remove/move. After success, call render_draft_preview before replying.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['gpt_chat_id', 'guide_token', 'operations'],
            'properties' => [
                'gpt_chat_id' => ['type' => 'string', 'format' => 'uuid'],
                'guide_token' => [
                    'type' => 'string',
                    'description' => 'Required token from get_form_generation_guide.',
                ],
                'operations' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'description' => 'Ordered patch operations. op in: set_form_config, add_property, update_property, remove_property, move_property.',
                    'items' => [
                        'type' => 'object',
                        'required' => ['op'],
                        'properties' => [
                            'op' => [
                                'type' => 'string',
                                'enum' => ['set_form_config', 'add_property', 'update_property', 'remove_property', 'move_property'],
                            ],
                            'values' => ['type' => 'object'],
                            'field' => ['type' => 'object'],
                            'patch' => ['type' => 'object'],
                            'index' => ['type' => 'integer', 'minimum' => 0],
                            'to_index' => ['type' => 'integer', 'minimum' => 0],
                            'field_id' => ['type' => 'string'],
                            'from_index' => ['type' => 'integer', 'minimum' => 0],
                            'from_field_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $this->guideTokenService->assertValid((string) ($arguments['guide_token'] ?? ''));
        $chatId = (string) ($arguments['gpt_chat_id'] ?? '');
        $operations = $arguments['operations'] ?? null;
        if (! is_array($operations) || $operations === []) {
            throw new RuntimeException('operations must be a non-empty array');
        }

        $draft = $this->drafts->fetch($chatId);
        $formState = is_array($draft->form_state ?? null) ? $draft->form_state : [];
        $formState = $this->formStateNormalizationService->normalize($formState);

        foreach ($operations as $operation) {
            if (! is_array($operation)) {
                throw new RuntimeException('each operation must be an object');
            }
            $formState = $this->applyOperation($formState, $operation);
        }

        $formState = $this->formStateNormalizationService->normalize($formState);
        $this->formStateValidationService->assertValidForUpdate($formState);

        $updatedDraft = $this->drafts->update($chatId, $formState);
        $serialized = $this->drafts->serialize($updatedDraft);
        $assistantDraft = $this->drafts->assistantDraft($serialized);

        return [
            'draft' => $assistantDraft,
            'draft_context' => $this->drafts->draftContext($serialized),
        ];
    }

    private function applyOperation(array $formState, array $operation): array
    {
        $op = (string) ($operation['op'] ?? '');

        return match ($op) {
            'set_form_config' => $this->applySetFormConfig($formState, $operation),
            'add_property' => $this->applyAddProperty($formState, $operation),
            'update_property' => $this->applyUpdateProperty($formState, $operation),
            'remove_property' => $this->applyRemoveProperty($formState, $operation),
            'move_property' => $this->applyMoveProperty($formState, $operation),
            default => throw new RuntimeException('unsupported operation: ' . $op),
        };
    }

    private function applySetFormConfig(array $formState, array $operation): array
    {
        $values = $operation['values'] ?? null;
        if (! is_array($values) || $values === []) {
            throw new RuntimeException('set_form_config.values must be a non-empty object');
        }

        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if ($key === 'properties') {
                throw new RuntimeException('set_form_config cannot modify properties; use property operations');
            }
            $formState[$key] = $value;
        }

        return $formState;
    }

    private function applyAddProperty(array $formState, array $operation): array
    {
        $field = $operation['field'] ?? null;
        if (! is_array($field) || $field === []) {
            throw new RuntimeException('add_property.field must be an object');
        }

        $properties = $this->properties($formState);
        $index = $operation['index'] ?? null;

        if ($index === null) {
            $properties[] = $field;
            $formState['properties'] = $properties;
            return $formState;
        }

        if (! is_int($index)) {
            throw new RuntimeException('add_property.index must be an integer');
        }

        $index = max(0, min($index, count($properties)));
        array_splice($properties, $index, 0, [$field]);
        $formState['properties'] = $properties;

        return $formState;
    }

    private function applyUpdateProperty(array $formState, array $operation): array
    {
        $patch = $operation['patch'] ?? null;
        if (! is_array($patch) || $patch === []) {
            throw new RuntimeException('update_property.patch must be a non-empty object');
        }

        $properties = $this->properties($formState);
        $index = $this->resolvePropertyIndex($properties, $operation);

        $properties[$index] = array_replace_recursive($properties[$index], $patch);
        $formState['properties'] = $properties;

        return $formState;
    }

    private function applyRemoveProperty(array $formState, array $operation): array
    {
        $properties = $this->properties($formState);
        $index = $this->resolvePropertyIndex($properties, $operation);

        array_splice($properties, $index, 1);
        $formState['properties'] = array_values($properties);

        return $formState;
    }

    private function applyMoveProperty(array $formState, array $operation): array
    {
        $properties = $this->properties($formState);
        if ($properties === []) {
            throw new RuntimeException('move_property cannot run on empty properties');
        }

        $fromOp = [
            'index' => $operation['from_index'] ?? null,
            'field_id' => $operation['from_field_id'] ?? null,
        ];
        $fromIndex = $this->resolvePropertyIndex($properties, $fromOp, ['index', 'field_id']);

        $toIndex = $operation['to_index'] ?? null;
        if (! is_int($toIndex)) {
            throw new RuntimeException('move_property.to_index must be an integer');
        }

        $toIndex = max(0, min($toIndex, count($properties) - 1));
        $moving = $properties[$fromIndex];
        array_splice($properties, $fromIndex, 1);
        array_splice($properties, $toIndex, 0, [$moving]);
        $formState['properties'] = array_values($properties);

        return $formState;
    }

    private function resolvePropertyIndex(array $properties, array $operation, array $acceptedSelectors = ['index', 'field_id']): int
    {
        if (in_array('index', $acceptedSelectors, true)) {
            $index = $operation['index'] ?? null;
            if (is_int($index)) {
                if (! isset($properties[$index])) {
                    throw new RuntimeException('property index out of range: ' . $index);
                }
                return $index;
            }
        }

        if (in_array('field_id', $acceptedSelectors, true)) {
            $fieldId = $operation['field_id'] ?? null;
            if (is_string($fieldId) && $fieldId !== '') {
                foreach ($properties as $i => $property) {
                    if ((string) ($property['id'] ?? '') === $fieldId) {
                        return $i;
                    }
                }
                throw new RuntimeException('property id not found: ' . $fieldId);
            }
        }

        throw new RuntimeException('operation requires a valid property selector (index or field_id)');
    }

    private function properties(array $formState): array
    {
        $properties = $formState['properties'] ?? [];
        if (! is_array($properties)) {
            throw new RuntimeException('form_state.properties must be an array');
        }

        return array_values($properties);
    }

    protected function meta(array $arguments, array $structuredContent): array
    {
        $context = $structuredContent['draft_context'] ?? null;
        if (! is_array($context)) {
            return [];
        }

        return $this->appsUiMetaService->forDraftContext($context);
    }

    protected function toolMeta(): array
    {
        return $this->appsUiMetaService->toolMeta();
    }

    protected function isReadOnly(): bool
    {
        return true;
    }
}
