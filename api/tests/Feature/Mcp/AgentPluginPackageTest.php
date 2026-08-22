<?php

function opnformPluginPath(string $path = ''): string
{
    return base_path('../plugins/opnform'.($path === '' ? '' : '/'.$path));
}

function readOpnformPluginJson(string $path): array
{
    return json_decode(
        file_get_contents(opnformPluginPath($path)),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

it('ships portable manifests that conform to the Agent Plugins 1.0 schemas', function () {
    $plugin = readOpnformPluginJson('plugin.json');
    $mcp = readOpnformPluginJson('mcp.json');

    $portablePluginFields = [
        '$schema', 'name', 'version', 'description', 'author', 'homepage',
        'repository', 'license', 'keywords', 'extensions',
    ];

    expect($plugin)
        ->toHaveKeys(['$schema', 'name'])
        ->and($plugin['$schema'])->toBe('https://agent-plugins.org/schemas/1.0.0/plugin.schema.json')
        ->and($plugin['name'])->toMatch('/^(?!.*(?:--|\.\.))[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/')
        ->and(strlen($plugin['name']))->toBeLessThanOrEqual(64)
        ->and(array_diff(array_keys($plugin), $portablePluginFields))->toBe([])
        ->and(array_diff(array_keys($plugin['author']), ['name', 'email', 'url']))->toBe([])
        ->and($plugin['keywords'])->each->toBeString()
        ->and($mcp)->toHaveKeys(['$schema', 'mcpServers'])
        ->and($mcp['$schema'])->toBe('https://agent-plugins.org/schemas/1.0.0/mcp.schema.json')
        ->and(array_diff(array_keys($mcp), ['$schema', 'mcpServers']))->toBe([])
        ->and($mcp['mcpServers']['opnform'])->toBe([
            'type' => 'streamable-http',
            'url' => 'https://api.opnform.com/mcp',
        ]);
});

it('ships a valid native OpenAI plugin wrapper for the hosted MCP server', function () {
    $portable = readOpnformPluginJson('plugin.json');
    $portableMcp = readOpnformPluginJson('mcp.json');
    $native = readOpnformPluginJson('.codex-plugin/plugin.json');
    $nativeApps = readOpnformPluginJson('.app.json');
    $nativeMcp = readOpnformPluginJson('.mcp.json');
    $nativePluginFields = [
        'id', 'name', 'version', 'description', 'skills', 'apps', 'mcpServers',
        'interface', 'author', 'homepage', 'repository', 'license', 'keywords',
    ];
    $nativeInterfaceFields = [
        'displayName', 'shortDescription', 'longDescription', 'developerName',
        'category', 'capabilities', 'websiteURL', 'privacyPolicyURL',
        'termsOfServiceURL', 'brandColor', 'composerIcon', 'logo', 'logoDark',
        'screenshots', 'defaultPrompt', 'default_prompt',
    ];

    expect($native)
        ->toHaveKeys([
            'name', 'version', 'description', 'author', 'skills', 'apps', 'mcpServers', 'interface',
        ])
        ->and($native['name'])->toBe($portable['name'])
        ->and($native['version'])->toBe($portable['version'])
        ->and($native['version'])->toMatch('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/')
        ->and($native['author'])->toBe($portable['author'])
        ->and($native['homepage'])->toBe($portable['homepage'])
        ->and($native['repository'])->toBe($portable['repository'])
        ->and(array_diff(array_keys($native), $nativePluginFields))->toBe([])
        ->and($native['skills'])->toBe('./skills/')
        ->and($native['apps'])->toBe('./.app.json')
        ->and($native['mcpServers'])->toBe('./.mcp.json')
        ->and($native['interface'])->toHaveKeys([
            'displayName', 'shortDescription', 'longDescription', 'developerName',
            'category', 'capabilities', 'websiteURL', 'privacyPolicyURL',
            'termsOfServiceURL', 'defaultPrompt', 'brandColor', 'composerIcon', 'logo',
        ])
        ->and(array_diff(array_keys($native['interface']), $nativeInterfaceFields))->toBe([])
        ->and($native['interface']['websiteURL'])->toBe($portable['author']['url'])
        ->and($native['interface']['defaultPrompt'])->toHaveCount(3)
        ->and($nativeMcp['mcpServers']['opnform'])->toBe([
            'type' => 'http',
            'url' => $portableMcp['mcpServers']['opnform']['url'],
            'auth' => 'oauth',
        ])
        ->and(array_diff(array_keys($nativeMcp), ['mcpServers']))->toBe([])
        ->and($nativeApps)->toBe([
            'apps' => [
                'opnform' => [
                    'id' => 'plugin_asdk_app_6a86dddc8f6c8191b0cc91f3a2a76d19',
                ],
            ],
        ]);

    foreach ($native['interface']['defaultPrompt'] as $prompt) {
        expect($prompt)->toBeString()
            ->and(strlen($prompt))->toBeLessThanOrEqual(128);
    }
});

it('keeps every native manifest path relative to and inside the plugin package', function () {
    $native = readOpnformPluginJson('.codex-plugin/plugin.json');
    $paths = [
        $native['skills'],
        $native['apps'],
        $native['mcpServers'],
        $native['interface']['composerIcon'],
        $native['interface']['logo'],
    ];
    $root = realpath(opnformPluginPath());

    foreach ($paths as $path) {
        $resolved = realpath(opnformPluginPath(substr($path, 2)));

        expect($path)->toStartWith('./')
            ->and($path)->not->toContain('..')
            ->and($resolved)->not->toBeFalse()
            ->and($resolved === $root || str_starts_with($resolved, $root.DIRECTORY_SEPARATOR))->toBeTrue();
    }
});

it('ships a discoverable OpnForm skill with the complete safety workflow', function () {
    $skillPath = opnformPluginPath('skills/opnform/SKILL.md');
    $skill = file_get_contents($skillPath);

    expect($skillPath)->toBeFile()
        ->and($skill)->toStartWith("---\n")
        ->and($skill)->toMatch('/\A---\nname: opnform\ndescription: .+\n---\n/s')
        ->and($skill)->toContain(
            'opnform://schemas/agent-form-definition/v1',
            'validate_form_definition',
            'draft_token',
            'preview_form_draft',
            'get_form_draft',
            'open_form_draft_in_editor',
            'get_account_context',
            'list_workspaces',
            'publish_form',
            'trash_form',
            'get_submission_stats',
            'export_submissions',
            'disabled_features',
            'expected_version',
            'revision',
        )
        ->and($skill)->toContain(
            'Authenticated tools remain discoverable before the account is connected',
            'Do not report that account tools are unavailable',
            'Never launch another Codex or ChatGPT agent',
            'run `codex exec`',
            'OpnForm is not attached to the current conversation',
            'Distinguish missing tools from missing authentication',
            'Enabling or selecting the plugin is not OAuth authentication',
            'Settings → MCP servers → OpnForm → Authenticate',
            'start a new conversation with OpnForm selected before the first message',
            'may not hot-reload OAuth credentials',
            'do not loop or claim the connection succeeded',
            '`textarea` is not a valid OpnForm field type',
            'never continue to draft creation after validation fails',
        )
        ->and($skill)->toContain('confirm_publish: true', 'confirm_trash: true')
        ->and($skill)->not->toContain('`confirm: true`')
        ->and($skill)->toContain('Submission access is read-only')
        ->and(substr_count($skill, "\n"))->toBeLessThan(500);
});

it('declares the hosted OpnForm MCP dependency in native skill metadata', function () {
    $metadataPath = opnformPluginPath('skills/opnform/agents/openai.yaml');
    $metadata = file_get_contents($metadataPath);

    expect($metadataPath)->toBeFile()
        ->and($metadata)->toContain(
            'display_name: "OpnForm"',
            'default_prompt: "Use $opnform',
            'type: "mcp"',
            'value: "opnform"',
            'transport: "streamable_http"',
            'url: "https://api.opnform.com/mcp"',
        );
});

it('contains valid JSON, no local endpoints, and only the registered ChatGPT app ID', function () {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(opnformPluginPath(), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        $contents = file_get_contents($file->getPathname());

        expect($contents)
            ->not->toMatch('~https?://(?:localhost|127(?:\.\d+){3}|\[?::1\]?)(?::\d+)?~i');

        if ($file->getFilename() !== '.app.json') {
            expect($contents)->not->toContain('plugin_asdk_app');
        }

        if ($file->getExtension() === 'json') {
            expect(fn () => json_decode($contents, true, flags: JSON_THROW_ON_ERROR))->not->toThrow(JsonException::class);
        }
    }
});

it('does not expose plugin package files from the monorepo root', function () {
    expect(base_path('../plugin.json'))->not->toBeFile()
        ->and(base_path('../mcp.json'))->not->toBeFile()
        ->and(base_path('../skills/opnform'))->not->toBeDirectory()
        ->and(base_path('../.agents/plugins/marketplace.json'))->not->toBeFile();
});

it('teaches agents how to change presentation and media without repository inspection', function () {
    $skill = file_get_contents(opnformPluginPath('skills/opnform/SKILL.md'));

    expect($skill)
        ->toContain('Re-read the field reference before changing presentation style, fields, layout, or media')
        ->toContain('every visible block becomes one step automatically')
        ->toContain('add an `image` object to that input or `nf-text` block')
        ->toContain('Never persist localhost, private addresses, temporary tunnel domains');
});

it('renders a flattened and hardened form preview frame', function () {
    $view = file_get_contents(resource_path('views/mcp/form-draft-preview-app.blade.php'));

    expect($view)
        ->toContain('class="preview-surface"')
        ->toContain('<p class="eyebrow">Private preview</p>')
        ->toContain("previewUrl.searchParams.set('embedded', '1')")
        ->toContain('/widgets/iframeResize.min.js')
        ->toContain('checkOrigin: [previewUrl.origin]')
        ->toContain('scrolling: false')
        ->toContain('const canvasWidth = 1280')
        ->toContain('const focusedHeight = 720')
        ->toContain('const defaultZoom = 0.8')
        ->toContain('availableWidth / canvasWidth')
        ->toContain('new ResizeObserver(applyPreviewLayout).observe(previewViewport)')
        ->toContain("sizeHeight: presentationStyle !== 'focused'")
        ->toContain('aria-label="Preview zoom"')
        ->toContain('sandbox="allow-forms allow-modals allow-popups allow-scripts allow-same-origin"')
        ->toContain('referrerpolicy="no-referrer"')
        ->not->toContain('class="card"')
        ->not->toContain('border-radius: 10px')
        ->not->toContain('allow-top-navigation');
});
