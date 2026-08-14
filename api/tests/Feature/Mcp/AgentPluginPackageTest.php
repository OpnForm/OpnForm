<?php

it('ships a conforming Agent Plugins 1.0 manifest and remote MCP declaration', function () {
    $root = base_path('..');
    $plugin = json_decode(file_get_contents($root.'/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
    $mcp = json_decode(file_get_contents($root.'/mcp.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($plugin)
        ->toHaveKeys(['$schema', 'name'])
        ->and($plugin['$schema'])->toBe('https://agent-plugins.org/schemas/1.0.0/plugin.schema.json')
        ->and($plugin['name'])->toMatch('/^(?!.*(?:--|\.\.))[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/')
        ->and(strlen($plugin['name']))->toBeLessThanOrEqual(64)
        ->and(array_diff(array_keys($plugin), [
            '$schema', 'name', 'version', 'description', 'author', 'homepage',
            'repository', 'license', 'keywords', 'extensions',
        ]))->toBe([])
        ->and(array_diff(array_keys($plugin['author']), ['name', 'email', 'url']))->toBe([])
        ->and($mcp['$schema'])->toBe('https://agent-plugins.org/schemas/1.0.0/mcp.schema.json')
        ->and(array_keys($mcp))->toBe(['$schema', 'mcpServers'])
        ->and($mcp['mcpServers']['opnform'])->toBe([
            'type' => 'streamable-http',
            'url' => 'https://api.opnform.com/mcp',
        ]);
});

it('ships a discoverable Agent Skill with matching frontmatter', function () {
    $skillPath = base_path('../skills/opnform/SKILL.md');
    $skill = file_get_contents($skillPath);

    expect($skillPath)->toBeFile()
        ->and($skill)->toStartWith("---\n")
        ->and($skill)->toMatch('/\A---\nname: opnform\ndescription: .+\n---\n/s')
        ->and(substr_count($skill, "\n"))->toBeLessThan(500);
});

it('hardens the nested form preview frame', function () {
    $view = file_get_contents(resource_path('views/mcp/form-draft-preview-app.blade.php'));

    expect($view)
        ->toContain('sandbox="allow-forms allow-modals allow-popups allow-scripts allow-same-origin"')
        ->toContain('referrerpolicy="no-referrer"')
        ->not->toContain('allow-top-navigation');
});
