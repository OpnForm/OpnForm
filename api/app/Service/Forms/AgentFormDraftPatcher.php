<?php

namespace App\Service\Forms;

use Illuminate\Validation\ValidationException;

class AgentFormDraftPatcher
{
    private const OPERATIONS = [
        'set_form_values',
        'add_block',
        'update_block',
        'remove_block',
        'move_block',
    ];

    public function apply(array $definition, array $operations): array
    {
        if ($operations === []) {
            throw ValidationException::withMessages([
                'operations' => ['At least one draft operation is required.'],
            ]);
        }

        foreach ($operations as $index => $operation) {
            if (! is_array($operation)) {
                throw $this->operationError($index, 'Each operation must be an object.');
            }

            $name = $operation['op'] ?? null;

            if (! is_string($name) || ! in_array($name, self::OPERATIONS, true)) {
                throw $this->operationError($index, 'Unsupported operation.');
            }

            $definition = match ($name) {
                'set_form_values' => $this->setFormValues($definition, $operation, $index),
                'add_block' => $this->addBlock($definition, $operation, $index),
                'update_block' => $this->updateBlock($definition, $operation, $index),
                'remove_block' => $this->removeBlock($definition, $operation, $index),
                'move_block' => $this->moveBlock($definition, $operation, $index),
            };
        }

        return $definition;
    }

    private function setFormValues(array $definition, array $operation, int $operationIndex): array
    {
        $values = $operation['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw $this->operationError($operationIndex, 'set_form_values.values must be a non-empty object.');
        }

        $protectedKeys = array_intersect(array_keys($values), ['schema_version', 'properties']);

        if ($protectedKeys !== []) {
            throw $this->operationError($operationIndex, 'Use block operations to change properties; schema_version cannot be changed.');
        }

        return array_replace($definition, $values);
    }

    private function addBlock(array $definition, array $operation, int $operationIndex): array
    {
        $block = $operation['block'] ?? null;

        if (! is_array($block) || $block === []) {
            throw $this->operationError($operationIndex, 'add_block.block must be an object.');
        }

        $properties = $this->properties($definition);
        $position = $operation['index'] ?? count($properties);

        if (! is_int($position) || $position < 0 || $position > count($properties)) {
            throw $this->operationError($operationIndex, 'add_block.index is out of range.');
        }

        array_splice($properties, $position, 0, [$block]);
        $definition['properties'] = array_values($properties);

        return $definition;
    }

    private function updateBlock(array $definition, array $operation, int $operationIndex): array
    {
        $patch = $operation['patch'] ?? null;

        if (! is_array($patch) || $patch === []) {
            throw $this->operationError($operationIndex, 'update_block.patch must be a non-empty object.');
        }

        if (array_key_exists('id', $patch)) {
            throw $this->operationError($operationIndex, 'Block IDs are stable and cannot be changed.');
        }

        $properties = $this->properties($definition);
        $blockIndex = $this->resolveBlockIndex($properties, $operation, $operationIndex);
        $properties[$blockIndex] = array_replace($properties[$blockIndex], $patch);
        $definition['properties'] = $properties;

        return $definition;
    }

    private function removeBlock(array $definition, array $operation, int $operationIndex): array
    {
        $properties = $this->properties($definition);
        $blockIndex = $this->resolveBlockIndex($properties, $operation, $operationIndex);
        array_splice($properties, $blockIndex, 1);
        $definition['properties'] = array_values($properties);

        return $definition;
    }

    private function moveBlock(array $definition, array $operation, int $operationIndex): array
    {
        $properties = $this->properties($definition);
        $blockIndex = $this->resolveBlockIndex($properties, $operation, $operationIndex);
        $toIndex = $operation['to_index'] ?? null;

        if (! is_int($toIndex) || $toIndex < 0 || $toIndex >= count($properties)) {
            throw $this->operationError($operationIndex, 'move_block.to_index is out of range.');
        }

        $block = $properties[$blockIndex];
        array_splice($properties, $blockIndex, 1);
        array_splice($properties, $toIndex, 0, [$block]);
        $definition['properties'] = array_values($properties);

        return $definition;
    }

    private function resolveBlockIndex(array $properties, array $operation, int $operationIndex): int
    {
        $blockId = $operation['block_id'] ?? null;

        if (is_string($blockId) && $blockId !== '') {
            foreach ($properties as $index => $property) {
                if (($property['id'] ?? null) === $blockId) {
                    return $index;
                }
            }

            throw $this->operationError($operationIndex, "Block [{$blockId}] was not found.");
        }

        $index = $operation['index'] ?? null;

        if (! is_int($index) || ! array_key_exists($index, $properties)) {
            throw $this->operationError($operationIndex, 'A valid block_id or index is required.');
        }

        return $index;
    }

    private function properties(array $definition): array
    {
        return array_values($definition['properties'] ?? []);
    }

    private function operationError(int $index, string $message): ValidationException
    {
        return ValidationException::withMessages([
            "operations.{$index}" => [$message],
        ]);
    }
}
