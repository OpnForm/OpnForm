<?php

use App\Models\OAuthProvider;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Controller-level import tests
// ---------------------------------------------------------------------------

it('returns prefill data for a valid import request', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    Http::fake([
        '*.typeform.com/*' => Http::response(typeformHtmlFixture(), 200),
    ]);

    $this->postJson(route('open.forms.import'), [
        'source' => 'typeform',
        'import_data' => ['url' => 'https://example.typeform.com/to/abc123'],
        'workspace_id' => $workspace->id,
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'form' => ['title', 'properties', 'presentation_style', 'size', 'settings'],
            'source',
            'fields_count',
        ])
        ->assertJsonPath('form.presentation_style', 'focused')
        ->assertJsonPath('form.size', 'lg')
        ->assertJsonPath('form.settings.navigation_arrows', true)
        ->assertJsonPath('source', 'typeform');
});

it('does not persist a form record on import', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    Http::fake([
        '*.typeform.com/*' => Http::response(typeformHtmlFixture(), 200),
    ]);

    $countBefore = \App\Models\Forms\Form::count();

    $this->postJson(route('open.forms.import'), [
        'source' => 'typeform',
        'import_data' => ['url' => 'https://example.typeform.com/to/abc123'],
        'workspace_id' => $workspace->id,
    ])->assertSuccessful();

    expect(\App\Models\Forms\Form::count())->toBe($countBefore);
});

it('rejects import with missing URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    $this->postJson(route('open.forms.import'), [
        'source' => 'typeform',
        'import_data' => [],
        'workspace_id' => $workspace->id,
    ])->assertStatus(422);
});

it('rejects import with unsupported source', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    $this->postJson(route('open.forms.import'), [
        'source' => 'notion',
        'import_data' => ['url' => 'https://notion.so/form/abc'],
        'workspace_id' => $workspace->id,
    ])->assertStatus(422);
});

it('rejects unauthenticated import requests', function () {
    $this->postJson(route('open.forms.import'), [
        'source' => 'typeform',
        'import_data' => ['url' => 'https://example.typeform.com/to/abc123'],
        'workspace_id' => 1,
    ])->assertStatus(401);
});

// ---------------------------------------------------------------------------
// Typeform importer
// ---------------------------------------------------------------------------

describe('TypeformImporter', function () {
    it('maps basic Typeform fields correctly', function () {
        Http::fake([
            '*.typeform.com/*' => Http::response(typeformHtmlFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        expect($result['title'])->toBe('Test Contact Form');
        expect($result['properties'])->toHaveCount(4);
        expect($result['presentation_style'])->toBe('focused');
        expect($result['size'])->toBe('lg');
        expect($result['settings']['navigation_arrows'])->toBeTrue();

        $types = array_column($result['properties'], 'type');
        expect($types)->toBe(['text', 'email', 'text', 'select']);
    });

    it('flattens composite fields without adding page breaks', function () {
        $fixture = typeformFormData();
        $fixture['fields'][] = [
            'type' => 'contact_info',
            'title' => 'Contact',
            'properties' => [
                'fields' => [
                    ['type' => 'short_text', 'title' => 'First Name', 'validations' => ['required' => true]],
                    ['type' => 'email', 'title' => 'Email', 'validations' => ['required' => true]],
                ],
            ],
        ];

        Http::fake([
            '*.typeform.com/*' => Http::response(wrapTypeformHtml($fixture), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        $types = array_column($result['properties'], 'type');
        expect($types)->not->toContain('nf-page-break');
        expect($types)->toContain('email');
    });

    it('does not add page breaks for group composite fields', function () {
        $fixture = typeformFormData();
        $fixture['fields'][] = [
            'type' => 'group',
            'title' => 'Grouped Questions',
            'properties' => [
                'fields' => [
                    ['type' => 'short_text', 'title' => 'Company', 'validations' => ['required' => true]],
                    ['type' => 'number', 'title' => 'Team Size', 'validations' => ['required' => false]],
                ],
            ],
        ];

        Http::fake([
            '*.typeform.com/*' => Http::response(wrapTypeformHtml($fixture), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        $types = array_column($result['properties'], 'type');
        expect($types)->not->toContain('nf-page-break');
        expect($types)->toContain('number');
    });

    it('maps multiple choice with allow_multiple_selection', function () {
        $fixture = typeformFormData();
        $fixture['fields'][] = [
            'type' => 'multiple_choice',
            'title' => 'Colors',
            'validations' => ['required' => false],
            'properties' => [
                'allow_multiple_selection' => true,
                'choices' => [
                    ['label' => 'Red'],
                    ['label' => 'Blue'],
                ],
            ],
        ];

        Http::fake([
            '*.typeform.com/*' => Http::response(wrapTypeformHtml($fixture), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        $multi = collect($result['properties'])->firstWhere('name', 'Colors');
        expect($multi['type'])->toBe('multi_select');
        expect($multi['multi_select']['options'])->toHaveCount(2);
    });

    it('imports welcome screen as an nf-text block', function () {
        $fixture = typeformFormData();
        $fixture['welcome_screens'] = [[
            'id' => 'w1',
            'ref' => 'welcome-ref',
            'title' => "Welcome!\nThanks for stopping by.",
            'properties' => ['show_button' => true, 'button_text' => 'Start'],
        ]];

        Http::fake([
            '*.typeform.com/*' => Http::response(wrapTypeformHtml($fixture), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        $welcome = $result['properties'][0];
        expect($welcome['type'])->toBe('nf-text');
        expect($welcome['content'])->toContain('Welcome!');
        // Newlines in the title should survive as <br>.
        expect($welcome['content'])->toContain('<br');
    });

    it('imports thank-you screens into submitted_text and skips the default one', function () {
        $fixture = typeformFormData();
        $fixture['thankyou_screens'] = [
            [
                'id' => 'ty1',
                'ref' => 'author-defined',
                'type' => 'thankyou_screen',
                'title' => 'Thanks — we will reach out soon!',
                'properties' => [],
            ],
            // Generic screen Typeform appends to every form; must be dropped.
            [
                'id' => 'DefaultTyScreen',
                'ref' => 'default_tys',
                'type' => 'thankyou_screen',
                'title' => "Thanks for completing this typeform\nNow *create your own*",
                'properties' => [],
            ],
        ];

        Http::fake([
            '*.typeform.com/*' => Http::response(wrapTypeformHtml($fixture), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        expect($result)->toHaveKey('submitted_text');
        expect($result['submitted_text'])->toContain('Thanks — we will reach out soon!');
        expect($result['submitted_text'])->not->toContain('create your own');
    });

    it('keeps long Typeform dropdowns as a real dropdown (not a flat list)', function () {
        $choices = array_map(
            fn ($i) => ['label' => "Option {$i}"],
            range(1, 16)
        );

        $fixture = typeformFormData();
        $fixture['fields'] = [[
            'type' => 'dropdown',
            'title' => 'Country',
            'validations' => ['required' => true],
            'properties' => ['choices' => $choices],
        ]];

        Http::fake([
            '*.typeform.com/*' => Http::response(wrapTypeformHtml($fixture), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://example.typeform.com/to/abc123']);

        $dropdown = collect($result['properties'])->firstWhere('name', 'Country');
        expect($dropdown['type'])->toBe('select');
        expect($dropdown['select']['options'])->toHaveCount(16);
        // Crucial: do NOT expand a 16-option list into radio buttons — that
        // would make the imported form unusable.
        expect($dropdown)->not->toHaveKey('without_dropdown');
    });

    it('imports the real Typeform CSAT form cleanly', function () {
        Http::fake([
            '*.typeform.com/*' => Http::response(typeformCsatHtmlFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $result = $importer->import(['url' => 'https://3xxbbppenpx.typeform.com/to/DHTu6yrw']);

        expect($result['title'])->toBe('Customer Satisfaction Feedback Form');
        expect($result['presentation_style'])->toBe('focused');

        // Welcome screen is the first property.
        $first = $result['properties'][0];
        expect($first['type'])->toBe('nf-text');
        expect($first['content'])->toContain('Thank you for choosing our product/service');

        // Author's thank-you text survives; the default Typeform blurb does not.
        expect($result['submitted_text'])->toContain('Thank you for providing feedback');
        expect($result['submitted_text'])->not->toContain('create your own');

        // 16-option country dropdown stays as a proper dropdown.
        $country = collect($result['properties'])
            ->firstWhere(fn ($p) => str_contains($p['name'] ?? '', 'country'));
        expect($country['type'])->toBe('select');
        expect($country['select']['options'])->toHaveCount(16);
        expect($country)->not->toHaveKey('without_dropdown');
    });

    it('throws on 404 from Typeform page', function () {
        Http::fake([
            '*.typeform.com/*' => Http::response('Not found', 404),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $importer->import(['url' => 'https://example.typeform.com/to/bad']);
    })->throws(\App\Service\FormImport\FormImportException::class);

    it('throws on invalid Typeform URL format', function () {
        $importer = app(\App\Service\FormImport\Importers\TypeformImporter::class);
        $importer->import(['url' => 'https://example.typeform.com/signup']);
    })->throws(\App\Service\FormImport\FormImportException::class);
});

// ---------------------------------------------------------------------------
// Tally importer
// ---------------------------------------------------------------------------

describe('TallyImporter', function () {
    it('maps basic Tally fields from __NEXT_DATA__', function () {
        Http::fake([
            'tally.so/*' => Http::response(tallyHtmlFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/testform']);

        expect($result['title'])->toBe('Tally Test Form');
        expect($result['properties'])->not->toBeEmpty();

        $types = array_column($result['properties'], 'type');
        expect($types)->toContain('text');
        expect($types)->toContain('email');
    });

    it('synthesizes parent for child-only MULTI_SELECT blocks', function () {
        $blocks = tallyBlocks();
        $blocks[] = ['uuid' => 't1', 'type' => 'TITLE', 'groupUuid' => 'q1', 'groupType' => 'QUESTION', 'payload' => ['safeHTMLSchema' => [['Favorite']]]];
        $blocks[] = ['uuid' => 'o1', 'type' => 'MULTI_SELECT_OPTION', 'groupUuid' => 'ms1', 'groupType' => 'MULTI_SELECT', 'payload' => ['index' => 0, 'isRequired' => true, 'text' => 'Opt A']];
        $blocks[] = ['uuid' => 'o2', 'type' => 'MULTI_SELECT_OPTION', 'groupUuid' => 'ms1', 'groupType' => 'MULTI_SELECT', 'payload' => ['index' => 1, 'isRequired' => true, 'text' => 'Opt B']];

        Http::fake([
            'tally.so/*' => Http::response(wrapTallyBlocks('Multi Select Form', $blocks), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/ms']);

        $multiSelect = collect($result['properties'])->firstWhere('type', 'multi_select');
        expect($multiSelect)->not->toBeNull();
        expect($multiSelect['multi_select']['options'])->toHaveCount(2);
    });

    it('does not duplicate matrix when parent block exists', function () {
        $blocks = tallyBlocks();
        $blocks[] = ['uuid' => 'm1', 'type' => 'MATRIX', 'groupUuid' => 'mg1', 'groupType' => 'MATRIX', 'payload' => ['isRequired' => true, 'name' => 'Grid']];
        $blocks[] = ['uuid' => 'mr1', 'type' => 'MATRIX_ROW', 'groupUuid' => 'mg1', 'groupType' => 'MATRIX', 'payload' => ['index' => 0, 'safeHTMLSchema' => [['R1']]]];
        $blocks[] = ['uuid' => 'mc1', 'type' => 'MATRIX_COLUMN', 'groupUuid' => 'mg1', 'groupType' => 'MATRIX', 'payload' => ['index' => 0, 'safeHTMLSchema' => [['C1']]]];

        Http::fake([
            'tally.so/*' => Http::response(wrapTallyBlocks('Matrix Form', $blocks), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/mx']);

        $matrices = collect($result['properties'])->where('type', 'matrix')->values();
        expect($matrices)->toHaveCount(1);
    });

    it('imports the real Tally lead-generation form cleanly', function () {
        Http::fake([
            'tally.so/*' => Http::response(tallyLeadGenHtmlFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/wMGppm']);

        expect($result['title'])->toBe('Lead generation form');

        $names = array_column($result['properties'], 'name');
        $types = array_column($result['properties'], 'type');

        // Standalone LABEL widgets are consumed by the following input
        // instead of leaking through as nf-text / Untitled.
        expect($names)->not->toContain('Untitled');
        expect(array_count_values($types)['nf-text'] ?? 0)->toBe(0);

        $byName = collect($result['properties'])->keyBy('name');
        expect($byName->has('Company name'))->toBeTrue();
        expect($byName->get('Company name')['type'])->toBe('text');
        expect($byName->has('Company size'))->toBeTrue();
        expect($byName->get('Company size')['type'])->toBe('select');
        expect($byName->get('Company size')['select']['options'])->toHaveCount(4);

        // Standalone CHECKBOX block (newsletter opt-in) → real boolean checkbox
        $checkbox = collect($result['properties'])->firstWhere('type', 'checkbox');
        expect($checkbox)->not->toBeNull();
        expect($checkbox['name'])->toContain('newsletter');
        expect($checkbox)->not->toHaveKey('multi_select');

        // Thank-you page content ends up as submitted_text, not as properties
        expect($result)->toHaveKey('submitted_text');
        expect($result['submitted_text'])->toContain('Thanks for downloading');
        expect($result['submitted_text'])->toContain('<a href="https://tally.so/"');

        expect(
            collect($result['properties'])->contains(fn ($p) => str_contains($p['name'] ?? '', 'Thanks for downloading'))
        )->toBeFalse();
    });

    it('maps a standalone CHECKBOX block to a real checkbox field', function () {
        $blocks = tallyBlocks();
        $blocks[] = [
            'uuid' => 'c1',
            'type' => 'CHECKBOX',
            'groupUuid' => 'cg1',
            'groupType' => 'CHECKBOXES',
            'payload' => ['index' => 0, 'isFirst' => true, 'isLast' => true, 'text' => 'I agree to the terms'],
        ];

        Http::fake([
            'tally.so/*' => Http::response(wrapTallyBlocks('Consent Form', $blocks), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/consent']);

        $checkbox = collect($result['properties'])->firstWhere('type', 'checkbox');
        expect($checkbox)->not->toBeNull();
        expect($checkbox['name'])->toBe('I agree to the terms');
    });

    it('uses standalone LABEL widgets as the next input name', function () {
        $blocks = tallyBlocks();
        $blocks[] = ['uuid' => 'l1', 'type' => 'LABEL', 'groupUuid' => 'lg1', 'groupType' => 'LABEL', 'payload' => ['safeHTMLSchema' => [['Company name']]]];
        $blocks[] = ['uuid' => 'i1', 'type' => 'INPUT_TEXT', 'groupUuid' => 'ig1', 'groupType' => 'INPUT_TEXT', 'payload' => ['placeholder' => '']];

        Http::fake([
            'tally.so/*' => Http::response(wrapTallyBlocks('Label Form', $blocks), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/lbl']);

        $types = array_column($result['properties'], 'type');
        expect($types)->not->toContain('nf-text');

        $named = collect($result['properties'])->firstWhere('name', 'Company name');
        expect($named)->not->toBeNull();
        expect($named['type'])->toBe('text');
    });

    it('stops at thank-you page break', function () {
        $blocks = tallyBlocks();
        $blocks[] = ['uuid' => 'pb1', 'type' => 'PAGE_BREAK', 'groupUuid' => 'pg1', 'groupType' => 'PAGE_BREAK', 'payload' => ['isThankYouPage' => true]];
        $blocks[] = ['uuid' => 'after', 'type' => 'INPUT_TEXT', 'groupUuid' => 'after', 'groupType' => 'INPUT_TEXT', 'payload' => ['placeholder' => 'Hidden']];

        Http::fake([
            'tally.so/*' => Http::response(wrapTallyBlocks('Thank You Form', $blocks), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\TallyImporter::class);
        $result = $importer->import(['url' => 'https://tally.so/r/ty']);

        $names = array_column($result['properties'], 'name');
        expect($names)->not->toContain('Hidden');
    });
});

// ---------------------------------------------------------------------------
// Fillout importer
// ---------------------------------------------------------------------------

describe('FilloutImporter', function () {
    it('maps basic Fillout fields', function () {
        Http::fake([
            'fillout.com/*' => Http::response(filloutHtmlFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\FilloutImporter::class);
        $result = $importer->import(['url' => 'https://example.fillout.com/t/abc123']);

        expect($result['title'])->toBe('Fillout Test Form');
        expect($result['properties'])->not->toBeEmpty();

        $types = array_column($result['properties'], 'type');
        expect($types)->toContain('text');
    });

    it('imports the real Fillout registration form cleanly', function () {
        Http::fake([
            'fillout.com/*' => Http::response(filloutRegistrationHtmlFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\FilloutImporter::class);
        $result = $importer->import(['url' => 'https://example.fillout.com/t/88efTSDCqTus']);

        expect($result['title'])->toBe('Online Registration Form Template 510');

        // Step order follows firstStep → nextStep (button or step-level),
        // not the insertion order of the steps dictionary.
        $names = array_column($result['properties'], 'name');
        $orderedFieldNames = array_values(array_filter($names, fn ($n) => $n !== 'Page Break' && $n !== 'Register with us online!'));
        expect($orderedFieldNames[0])->toBe("What's your full name?");
        expect($orderedFieldNames[1])->toBe("What's your primary email address?");
        expect($orderedFieldNames[2])->toBe("Anything else you'd like to share?");

        // Focused presentation paginates one field at a time, so we drop the
        // explicit nf-page-break blocks that would otherwise produce empty
        // intermediate pages.
        expect(array_count_values(array_column($result['properties'], 'type'))['nf-page-break'] ?? 0)->toBe(0);

        // Cover welcome screen survives as an nf-text block.
        $cover = collect($result['properties'])->firstWhere('type', 'nf-text');
        expect($cover)->not->toBeNull();
        expect($cover['content'])->toContain('Register with us online!');

        // Thank-you screen is mapped to submitted_text with placeholder
        // reference tokens stripped out so the copy reads cleanly.
        expect($result)->toHaveKey('submitted_text');
        expect($result['submitted_text'])->toContain("You're officially registered");
        expect($result['submitted_text'])->not->toMatch('/\{\{[^}]+\}\}/');

        // Nice-to-have: page-per-question flow → focused layout hint.
        expect($result['presentation_style'] ?? null)->toBe('focused');
        expect($result['size'] ?? null)->toBe('lg');

        // No leftover Button widgets or blank nf-text banners.
        expect($names)->not->toContain('Untitled button field');
        foreach ($result['properties'] as $p) {
            if (($p['type'] ?? '') === 'nf-text') {
                expect(trim(strip_tags($p['content'] ?? '')))->not->toBe('');
            }
        }
    });

    it('keeps page breaks when the layout stays classic (non-focused)', function () {
        $pageProps = [
            'flow' => ['name' => 'Classic Form'],
            'flowSnapshot' => [
                'template' => [
                    'firstStep' => 's1',
                    'steps' => [
                        's1' => [
                            'id' => 's1', 'type' => 'form',
                            'nextStep' => ['defaultNextStep' => 's2'],
                            'template' => ['widgets' => [
                                ['type' => 'ShortAnswer', 'position' => ['row' => 0], 'template' => ['label' => 'A']],
                                ['type' => 'ShortAnswer', 'position' => ['row' => 1], 'template' => ['label' => 'B']],
                                ['type' => 'ShortAnswer', 'position' => ['row' => 2], 'template' => ['label' => 'C']],
                            ]],
                        ],
                        's2' => [
                            'id' => 's2', 'type' => 'form',
                            'nextStep' => ['defaultNextStep' => ''],
                            'template' => ['widgets' => [
                                ['type' => 'EmailInput', 'position' => ['row' => 0], 'template' => ['label' => 'Email']],
                                ['type' => 'ShortAnswer', 'position' => ['row' => 1], 'template' => ['label' => 'Phone']],
                            ]],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'fillout.com/*' => Http::response(wrapFilloutPageProps($pageProps), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\FilloutImporter::class);
        $result = $importer->import(['url' => 'https://example.fillout.com/t/classic']);

        $types = array_column($result['properties'], 'type');
        expect(array_count_values($types)['nf-page-break'] ?? 0)->toBe(1);
    });

    it('drops page breaks when focused layout is inferred', function () {
        $pageProps = [
            'flow' => ['name' => 'Focused Form'],
            'flowSnapshot' => [
                'template' => [
                    'firstStep' => 's1',
                    'steps' => [
                        's1' => [
                            'id' => 's1', 'type' => 'form',
                            'nextStep' => ['defaultNextStep' => 's2'],
                            'template' => ['widgets' => [
                                ['type' => 'ShortAnswer', 'position' => ['row' => 0], 'template' => ['label' => 'Name']],
                            ]],
                        ],
                        's2' => [
                            'id' => 's2', 'type' => 'form',
                            'nextStep' => ['defaultNextStep' => ''],
                            'template' => ['widgets' => [
                                ['type' => 'EmailInput', 'position' => ['row' => 0], 'template' => ['label' => 'Email']],
                            ]],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'fillout.com/*' => Http::response(wrapFilloutPageProps($pageProps), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\FilloutImporter::class);
        $result = $importer->import(['url' => 'https://example.fillout.com/t/focused']);

        expect($result['presentation_style'] ?? null)->toBe('focused');
        $types = array_column($result['properties'], 'type');
        expect(array_count_values($types)['nf-page-break'] ?? 0)->toBe(0);
    });

    it('does not infer focused layout when steps have multiple fields', function () {
        $pageProps = [
            'flow' => ['name' => 'Wide Form'],
            'flowSnapshot' => [
                'template' => [
                    'firstStep' => 's1',
                    'steps' => [
                        's1' => [
                            'id' => 's1', 'type' => 'form',
                            'nextStep' => ['defaultNextStep' => 's2'],
                            'template' => ['widgets' => [
                                ['type' => 'ShortAnswer', 'position' => ['row' => 0], 'template' => ['label' => 'A']],
                                ['type' => 'ShortAnswer', 'position' => ['row' => 1], 'template' => ['label' => 'B']],
                                ['type' => 'ShortAnswer', 'position' => ['row' => 2], 'template' => ['label' => 'C']],
                            ]],
                        ],
                        's2' => [
                            'id' => 's2', 'type' => 'form',
                            'nextStep' => ['defaultNextStep' => ''],
                            'template' => ['widgets' => [
                                ['type' => 'EmailInput', 'position' => ['row' => 0], 'template' => ['label' => 'Email']],
                                ['type' => 'ShortAnswer', 'position' => ['row' => 1], 'template' => ['label' => 'Phone']],
                            ]],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'fillout.com/*' => Http::response(wrapFilloutPageProps($pageProps), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\FilloutImporter::class);
        $result = $importer->import(['url' => 'https://example.fillout.com/t/wide']);

        expect($result)->not->toHaveKey('presentation_style');
        expect($result)->not->toHaveKey('size');
    });
});

// ---------------------------------------------------------------------------
// Google Forms importer
// ---------------------------------------------------------------------------

describe('GoogleFormsImporter', function () {
    it('maps Google Forms fields via API', function () {
        $user = $this->actingAsUser();
        $provider = OAuthProvider::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'forms.googleapis.com/v1/forms/*' => Http::response(googleFormsFixture(), 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\GoogleFormsImporter::class);
        $result = $importer->import([
            'url' => 'https://docs.google.com/forms/d/1abc123/edit',
            'oauth_provider_id' => $provider->id,
        ]);

        expect($result['title'])->toBe('Google Test Form');
        expect($result['properties'])->toHaveCount(4);

        $types = array_column($result['properties'], 'type');
        expect($types)->toContain('text');
        expect($types)->toContain('select');
        expect($types)->toContain('date');
    });

    it('maps grid questions to matrix', function () {
        $user = $this->actingAsUser();
        $provider = OAuthProvider::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'test-token',
        ]);

        $fixture = googleFormsFixture();
        $fixture['items'][] = [
            'title' => 'Satisfaction',
            'questionGroupItem' => [
                'grid' => ['columns' => ['options' => [['value' => 'Good'], ['value' => 'Bad']]]],
                'questions' => [
                    ['required' => true, 'rowQuestion' => ['title' => 'Service']],
                    ['required' => true, 'rowQuestion' => ['title' => 'Price']],
                ],
            ],
        ];

        Http::fake([
            'forms.googleapis.com/v1/forms/*' => Http::response($fixture, 200),
        ]);

        $importer = app(\App\Service\FormImport\Importers\GoogleFormsImporter::class);
        $result = $importer->import([
            'url' => 'https://docs.google.com/forms/d/1abc123/edit',
            'oauth_provider_id' => $provider->id,
        ]);

        $matrix = collect($result['properties'])->firstWhere('type', 'matrix');
        expect($matrix)->not->toBeNull();
        expect($matrix['rows'])->toBe(['Service', 'Price']);
        expect($matrix['columns'])->toBe(['Good', 'Bad']);
    });

    it('throws on expired Google token', function () {
        $user = $this->actingAsUser();
        $provider = OAuthProvider::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'expired-token',
            'refresh_token' => '',
        ]);

        Http::fake([
            'forms.googleapis.com/v1/forms/*' => Http::response('Unauthorized', 401),
        ]);

        $importer = app(\App\Service\FormImport\Importers\GoogleFormsImporter::class);
        $importer->import([
            'url' => 'https://docs.google.com/forms/d/1abc123/edit',
            'oauth_provider_id' => $provider->id,
        ]);
    })->throws(\App\Service\FormImport\FormImportException::class);

    it('throws when oauth_provider_id is missing', function () {
        $importer = app(\App\Service\FormImport\Importers\GoogleFormsImporter::class);
        $importer->import([
            'url' => 'https://docs.google.com/forms/d/1abc123/edit',
        ]);
    })->throws(\App\Service\FormImport\FormImportException::class);

    it('rejects published form URLs during import', function () {
        $user = $this->actingAsUser();
        $provider = OAuthProvider::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'test-token',
        ]);

        $importer = app(\App\Service\FormImport\Importers\GoogleFormsImporter::class);
        $importer->import([
            'url' => 'https://docs.google.com/forms/d/e/published123/viewform',
            'oauth_provider_id' => $provider->id,
        ]);
    })->throws(\App\Service\FormImport\FormImportException::class);
});

// ---------------------------------------------------------------------------
// Google Forms controller flow
// ---------------------------------------------------------------------------

describe('Google Forms controller flow', function () {
    it('resolves Google token via oauth_provider_id', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        $provider = OAuthProvider::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'valid-google-token',
            'refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'forms.googleapis.com/v1/forms/*' => Http::response(googleFormsFixture(), 200),
        ]);

        $this->postJson(route('open.forms.import'), [
            'source' => 'google_forms',
            'import_data' => [
                'url' => 'https://docs.google.com/forms/d/1abc123/edit',
                'oauth_provider_id' => $provider->id,
            ],
            'workspace_id' => $workspace->id,
        ])
            ->assertSuccessful()
            ->assertJsonPath('form.title', 'Google Test Form');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'forms.googleapis.com')
                && $request->hasHeader('Authorization', 'Bearer valid-google-token');
        });
    });

    it('returns error when oauth_provider_id is missing', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        $this->postJson(route('open.forms.import'), [
            'source' => 'google_forms',
            'import_data' => ['url' => 'https://docs.google.com/forms/d/1abc123/edit'],
            'workspace_id' => $workspace->id,
        ])->assertStatus(422);
    });
});

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

function typeformFormData(): array
{
    return [
        'title' => 'Test Contact Form',
        'fields' => [
            ['type' => 'short_text', 'title' => 'Name', 'validations' => ['required' => true]],
            ['type' => 'email', 'title' => 'Email', 'validations' => ['required' => true]],
            ['type' => 'long_text', 'title' => 'Message', 'validations' => ['required' => false]],
            [
                'type' => 'dropdown',
                'title' => 'Category',
                'validations' => ['required' => false],
                'properties' => [
                    'choices' => [
                        ['label' => 'Support'],
                        ['label' => 'Sales'],
                        ['label' => 'Other'],
                    ],
                ],
            ],
        ],
    ];
}

function wrapTypeformHtml(array $formData): string
{
    $formJson = json_encode($formData, JSON_UNESCAPED_UNICODE);

    return '<html><head></head><body><div id="root"></div>'
        . '<script data-csp-hash="">'
        . "window.rendererData={rootDomNode:'root',form:" . $formJson
        . ",messages:{},trackingInfo:{}};"
        . '</script></body></html>';
}

function typeformHtmlFixture(): string
{
    return wrapTypeformHtml(typeformFormData());
}

function typeformCsatHtmlFixture(): string
{
    $form = json_decode(
        file_get_contents(__DIR__ . '/../../fixtures/typeform-csat.json'),
        true
    );

    return wrapTypeformHtml($form);
}

function tallyBlocks(): array
{
    return [
        ['uuid' => 'ft1', 'type' => 'FORM_TITLE', 'groupUuid' => 'ft1', 'groupType' => 'FORM_TITLE', 'payload' => ['title' => 'Title']],
        ['uuid' => 'f1', 'type' => 'INPUT_TEXT', 'groupUuid' => 'f1', 'groupType' => 'INPUT_TEXT', 'payload' => ['placeholder' => 'Name']],
        ['uuid' => 'f2', 'type' => 'INPUT_EMAIL', 'groupUuid' => 'f2', 'groupType' => 'INPUT_EMAIL', 'payload' => ['placeholder' => 'Email']],
    ];
}

function wrapTallyBlocks(string $title, array $blocks): string
{
    $nextData = json_encode([
        'props' => [
            'pageProps' => [
                'name' => $title,
                'blocks' => $blocks,
            ],
        ],
    ]);

    return '<html><body><script id="__NEXT_DATA__" type="application/json">' . $nextData . '</script></body></html>';
}

function tallyHtmlFixture(): string
{
    return wrapTallyBlocks('Tally Test Form', tallyBlocks());
}

function tallyLeadGenHtmlFixture(): string
{
    $json = file_get_contents(__DIR__ . '/../../fixtures/tally-lead-generation.json');

    return '<html><body><script id="__NEXT_DATA__" type="application/json">' . $json . '</script></body></html>';
}

function filloutHtmlFixture(): string
{
    $nextData = json_encode([
        'props' => [
            'pageProps' => [
                'flow' => ['name' => 'Fillout Test Form'],
                'flowSnapshot' => [
                    'template' => [
                        'steps' => [
                            [
                                'template' => [
                                    'widgets' => [
                                        ['type' => 'ShortAnswer', 'name' => 'Your Name', 'required' => true],
                                        ['type' => 'EmailInput', 'name' => 'Your Email', 'required' => true],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    return '<html><body><script id="__NEXT_DATA__" type="application/json">' . $nextData . '</script></body></html>';
}

function wrapFilloutPageProps(array $pageProps): string
{
    $nextData = json_encode(['props' => ['pageProps' => $pageProps]], JSON_UNESCAPED_UNICODE);

    return '<html><body><script id="__NEXT_DATA__" type="application/json">' . $nextData . '</script></body></html>';
}

function filloutRegistrationHtmlFixture(): string
{
    $json = file_get_contents(__DIR__ . '/../../fixtures/fillout-registration.json');

    return '<html><body><script id="__NEXT_DATA__" type="application/json">' . $json . '</script></body></html>';
}

function googleFormsFixture(): array
{
    return [
        'info' => ['title' => 'Google Test Form', 'documentTitle' => 'Google Test Form'],
        'items' => [
            [
                'title' => 'Your Name',
                'questionItem' => [
                    'question' => [
                        'required' => true,
                        'textQuestion' => ['paragraph' => false],
                    ],
                ],
            ],
            [
                'title' => 'Favorite Color',
                'questionItem' => [
                    'question' => [
                        'required' => false,
                        'choiceQuestion' => [
                            'type' => 'RADIO',
                            'options' => [
                                ['value' => 'Red'],
                                ['value' => 'Blue'],
                                ['value' => 'Green'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Birth Date',
                'questionItem' => [
                    'question' => [
                        'required' => false,
                        'dateQuestion' => ['includeTime' => false],
                    ],
                ],
            ],
            [
                'title' => 'Comments',
                'questionItem' => [
                    'question' => [
                        'required' => false,
                        'textQuestion' => ['paragraph' => true],
                    ],
                ],
            ],
        ],
    ];
}
