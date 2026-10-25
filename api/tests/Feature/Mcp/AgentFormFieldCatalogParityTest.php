<?php

use App\Service\Forms\AgentFormFieldCatalog;

it('keeps the MCP field catalog aligned with the client block registry', function () {
    $registryPath = base_path('../client/data/blocks_types.json');

    expect($registryPath)->toBeFile();

    $registry = json_decode(file_get_contents($registryPath), true, flags: JSON_THROW_ON_ERROR);

    $clientInputTypes = collect($registry)
        ->filter(fn (array $block): bool => (bool) ($block['is_input'] ?? false))
        ->keys()
        ->sort()
        ->values()
        ->all();
    $mcpInputTypes = collect(AgentFormFieldCatalog::INPUT_TYPES)
        ->merge(array_keys(AgentFormFieldCatalog::ALIASES))
        ->sort()
        ->values()
        ->all();

    $clientLayoutTypes = collect($registry)
        ->reject(fn (array $block): bool => (bool) ($block['is_input'] ?? false))
        ->keys()
        ->sort()
        ->values()
        ->all();
    $mcpLayoutTypes = collect(AgentFormFieldCatalog::LAYOUT_TYPES)
        ->sort()
        ->values()
        ->all();

    expect($mcpInputTypes)->toBe($clientInputTypes)
        ->and($mcpLayoutTypes)->toBe($clientLayoutTypes)
        ->and(AgentFormFieldCatalog::INPUT_TYPES)->toHaveCount(count(array_unique(AgentFormFieldCatalog::INPUT_TYPES)))
        ->and(AgentFormFieldCatalog::LAYOUT_TYPES)->toHaveCount(count(array_unique(AgentFormFieldCatalog::LAYOUT_TYPES)));

    foreach (AgentFormFieldCatalog::ALIASES as $alias => $definition) {
        expect(AgentFormFieldCatalog::INPUT_TYPES)->not->toContain($alias)
            ->and(AgentFormFieldCatalog::INPUT_TYPES)->toContain($definition['type']);
    }
});
