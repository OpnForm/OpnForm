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

function readOpnformSkillBundle(): string
{
    $skillRoot = opnformPluginPath('skills/opnform');
    $references = collect(glob($skillRoot.'/references/*.md'))
        ->map(fn (string $path): string => file_get_contents($path))
        ->implode("\n");

    return file_get_contents($skillRoot.'/SKILL.md')."\n".$references;
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
        ->and($native['interface']['defaultPrompt'][0])->toContain('polished contact form')
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
    $bundle = readOpnformSkillBundle();

    expect($skillPath)->toBeFile()
        ->and($skill)->toStartWith("---\n")
        ->and($skill)->toMatch('/\A---\nname: opnform\ndescription: .+\n---\n/s')
        ->and($bundle)->toContain(
            'opnform://schemas/agent-form-definition/v1',
            'validate_form_definition',
            'draft_handle',
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
        ->and($bundle)->toContain(
            'Do not use shell, raw HTTP, another connector, or a recursive agent as a fallback',
            'Distinguish missing tools from missing authentication',
            'Enabling or selecting the plugin is not OAuth authentication',
            'start a new conversation with OpnForm selected',
            'Do not loop on an OAuth challenge or claim that connection succeeded without a successful account-scoped result',
            '`textarea` is not a valid type',
            'do not create or patch after a validation failure',
            '`quality_warnings`',
            '`name` is always visible respondent-facing copy',
            'Add placeholders only for useful examples or expected formats',
            'Use a contextual action',
        )
        ->and($skill)->toContain('confirm_publish: true', 'confirm_trash: true')
        ->and($skill)->not->toContain('`confirm: true`')
        ->and($skill)->not->toContain('draft_token', 'capability secret')
        ->and($skill)->toContain('Submission access is read-only')
        ->and($bundle)->toContain(
            'describe a recoverable field error as a generic server error',
            '`border_radius` is `none`, `small`, or `full`',
        )
        ->and(str_word_count($skill))->toBeLessThan(900)
        ->and(opnformPluginPath('skills/opnform/references/form-authoring.md'))->toBeFile()
        ->and(opnformPluginPath('skills/opnform/references/account-and-submissions.md'))->toBeFile()
        ->and(opnformPluginPath('skills/opnform/references/connection-and-recovery.md'))->toBeFile();
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
    $bundle = readOpnformSkillBundle();

    expect($bundle)
        ->toContain('opnform://schemas/agent-form-definition/v1')
        ->toContain('opnform://reference/form-fields/v1')
        ->toContain('before generating or materially changing fields, layout, presentation, or media')
        ->toContain('In focused mode, every visible block is already one step')
        ->toContain('attach an `image` object directly to that input or `nf-text` block')
        ->toContain('Never persist localhost, private addresses, temporary tunnels');
});

it('renders a guest preview automatically after creation and every draft change', function () {
    $skill = file_get_contents(opnformPluginPath('skills/opnform/SKILL.md'));
    $server = file_get_contents(app_path('Mcp/Servers/OpnFormServer.php'));
    $createTool = file_get_contents(app_path('Mcp/Tools/CreateFormDraftTool.php'));
    $patchTool = file_get_contents(app_path('Mcp/Tools/PatchFormDraftTool.php'));
    $previewTool = file_get_contents(app_path('Mcp/Tools/PreviewFormDraftTool.php'));

    expect($skill)
        ->toContain('Call `preview_form_draft` exactly once with that handle')
        ->toContain('then call `preview_form_draft` exactly once')
        ->and($server)
        ->toContain('Create and patch are data-only')
        ->toContain('call preview_form_draft exactly once')
        ->and($createTool)
        ->not->toContain('RendersApp')
        ->and($patchTool)
        ->not->toContain('RendersApp')
        ->and($previewTool)
        ->toContain('RendersApp(resource: FormDraftPreviewApp::class)');
});

it('guides the agent from guest preview to account save and explicit publication', function () {
    $skill = file_get_contents(opnformPluginPath('skills/opnform/SKILL.md'));
    $server = file_get_contents(app_path('Mcp/Servers/OpnFormServer.php'));
    $createTool = file_get_contents(app_path('Mcp/Tools/CreateFormDraftTool.php'));
    $patchTool = file_get_contents(app_path('Mcp/Tools/PatchFormDraftTool.php'));
    $previewTool = file_get_contents(app_path('Mcp/Tools/PreviewFormDraftTool.php'));

    expect($skill)
        ->toContain('ask exactly one question offering two choices')
        ->toContain('A status-only response is incomplete')
        ->toContain('saved as an unpublished draft')
        ->toContain('Asking is not confirmation')
        ->and($server)
        ->toContain('ask whether to modify or save')
        ->toContain('require explicit confirmation')
        ->and($createTool)
        ->toContain('data-only')
        ->and($patchTool)
        ->toContain('data-only')
        ->and($previewTool)
        ->toContain("'next_step'")
        ->toContain('modify the draft again or save it')
        ->toContain('Do not request OAuth unless the user chooses save');
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
        ->toContain('const focusedHeight = 720')
        ->toContain('const zoomLevels = [0.75, 0.85, 1]')
        ->toContain('const defaultZoomIndex = 1')
        ->toContain('availableWidth / zoom')
        ->toContain('new ResizeObserver(applyPreviewLayout).observe(previewViewport)')
        ->toContain("sizeHeight: presentationStyle !== 'focused'")
        ->toContain("app.callServerTool('open_form_draft_in_editor'")
        ->toContain('currentPreviewUrl !== nextPreviewUrl')
        ->toContain('Ask ChatGPT to refresh this preview.')
        ->toContain('aria-label="Preview zoom"')
        ->toContain('sandbox="allow-forms allow-modals allow-popups allow-scripts allow-same-origin"')
        ->toContain('referrerpolicy="no-referrer"')
        ->not->toContain('class="card"')
        ->not->toContain('border-radius: 10px')
        ->not->toContain('window.openai')
        ->not->toContain('openai:set_globals')
        ->not->toContain('allow-top-navigation');
});
