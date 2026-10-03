<?php

use App\Service\Forms\FormDataNormalizer;

uses(Tests\TestCase::class);

it('uses one normalization path for form titles, properties, and select options', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'title' => '  My form  ',
        'properties' => [
            [
                'name' => '  <strong>Choice</strong>  ',
                'type' => 'select',
                'help' => '<script>alert(1)</script><p>Pick one</p>',
                'select' => [
                    'options' => [
                        ['name' => 'First'],
                    ],
                ],
            ],
        ],
    ], backfillPropertyIds: true);

    expect($normalized['title'])->toBe('My form')
        ->and($normalized['properties'][0]['name'])->toBe('Choice')
        ->and($normalized['properties'][0]['help'])->not->toContain('<script>')
        ->and($normalized['properties'][0]['select']['options'][0]['id'])->toBe('First')
        ->and($normalized['properties'][0]['id'])->toBeString()->not->toBeEmpty();
});

it('canonicalizes legacy boolean representations before validating behavior', function () {
    $normalized = app(FormDataNormalizer::class)->normalizeProperties([
        [
            'id' => 'choice',
            'name' => 'Choice',
            'type' => 'select',
            'hidden' => '0',
            'allow_creation' => '1',
            'select' => ['options' => []],
        ],
    ]);

    expect($normalized[0]['hidden'])->toBeFalse()
        ->and($normalized[0]['allow_creation'])->toBeTrue();
});

it('clears invalid optional field settings without discarding the field', function () {
    $normalized = app(FormDataNormalizer::class)->normalizeProperties([
        [
            'id' => 'message',
            'name' => 'Message',
            'type' => 'text',
            'required' => 'false',
            'hidden' => 'not-a-boolean',
            'width' => 'oversized',
            'align' => 'diagonal',
            'max_char_limit' => 0,
            'image' => [
                'url' => 'not-a-url',
                'alt' => 'Unused',
            ],
        ],
    ]);

    expect($normalized[0]['required'])->toBeFalse()
        ->and($normalized[0]['width'])->toBe('full')
        ->and($normalized[0])->not->toHaveKeys(['hidden', 'align', 'max_char_limit', 'image'])
        ->and($normalized[0]['name'])->toBe('Message');
});

it('backfills a missing block id on every canonical normalization path', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [[
            'name' => 'Email address',
            'type' => 'email',
        ]],
    ]);

    expect($normalized['properties'][0]['id'])->toBeString()->not->toBeEmpty();
});

it('drops property entries that cannot represent a form block', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            'not-a-block',
            ['id' => 'email', 'name' => 'Email', 'type' => 'email'],
        ],
    ]);

    expect($normalized['properties'])->toBe([
        ['id' => 'email', 'name' => 'Email', 'type' => 'email'],
    ]);
});

it('normalizes supported field aliases on every authoring path', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'choice', 'name' => 'Choice', 'type' => 'radio'],
            ['id' => 'secret', 'name' => 'Secret', 'type' => 'password'],
        ],
    ]);

    expect($normalized['properties'][0])->toMatchArray([
        'type' => 'select',
        'without_dropdown' => true,
    ])->and($normalized['properties'][1])->toMatchArray([
        'type' => 'text',
        'secret_input' => true,
        'multi_lines' => false,
    ]);
});

it('migrates deterministic legacy field aliases and removes obsolete non-fields', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'long', 'name' => 'Details', 'type' => 'textarea'],
            ['id' => 'phone', 'name' => 'Phone', 'type' => 'phone'],
            ['id' => 'copy', 'name' => 'Copy', 'type' => 'html', 'html_content' => '<p>Hello</p>'],
            ['id' => 'submit', 'name' => 'Submit', 'type' => 'submit', 'button_text' => 'Send it'],
            ['id' => 'captcha', 'name' => 'Captcha', 'type' => 'captcha'],
        ],
    ]);

    expect($normalized['properties'])->toHaveCount(3)
        ->and($normalized['properties'][0])->toMatchArray(['type' => 'text', 'multi_lines' => true])
        ->and($normalized['properties'][1]['type'])->toBe('phone_number')
        ->and($normalized['properties'][2])->toMatchArray(['type' => 'nf-text', 'content' => '<p>Hello</p>'])
        ->and($normalized['use_captcha'])->toBeTrue()
        ->and($normalized['submit_button_text'])->toBe('Send it');
});

it('leaves a malformed field type for localized validation without throwing during alias normalization', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [[
            'id' => 'invalid',
            'name' => 'Invalid',
            'type' => ['text'],
        ]],
    ]);
    $validator = validator($normalized, [
        'properties' => ['array', new \App\Rules\FormPropertiesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('properties.0.type'))->toContain('must be a string');
});

it('preserves a valid string zero block id', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [[
            'id' => '0',
            'name' => 'Zero',
            'type' => 'text',
        ]],
    ]);

    expect($normalized['properties'][0]['id'])->toBe('0');
});

it('preserves strikethrough help text while removing unsafe markup', function () {
    $normalized = app(FormDataNormalizer::class)->normalizeProperties([
        [
            'type' => 'select',
            'help' => '<p><s onclick="alert(1)">Unavailable option</s></p><script>alert(1)</script>',
        ],
    ]);

    expect($normalized[0]['help'])
        ->toContain('<s>Unavailable option</s>')
        ->not->toContain('onclick')
        ->not->toContain('<script>');
});

it('removes recoverably invalid logic instead of returning a validation burden', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            [
                'id' => 'target',
                'name' => 'Target',
                'type' => 'text',
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [],
                    ],
                    'actions' => ['hide-block'],
                ],
            ],
        ],
        'computed_variables' => [],
    ]);

    expect($normalized['properties'][1])->not->toHaveKey('logic');
});

it('removes over-nested logic without traversing beyond the validation limit', function () {
    $condition = [
        'identifier' => 'source',
        'value' => [
            'operator' => 'equals',
            'property_meta' => ['id' => 'source', 'type' => 'text'],
            'value' => 'yes',
        ],
    ];

    foreach (range(1, 20) as $unused) {
        $condition = ['operatorIdentifier' => 'and', 'children' => [$condition]];
    }

    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            [
                'id' => 'target',
                'name' => 'Target',
                'type' => 'text',
                'logic' => [
                    'conditions' => $condition,
                    'actions' => ['show-block'],
                ],
            ],
        ],
        'computed_variables' => [],
    ]);

    expect($normalized['properties'][1])->not->toHaveKey('logic');
});

it('repairs certain logic migrations before deciding whether to remove the rule', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'accepted', 'name' => 'Accepted', 'type' => 'checkbox'],
            [
                'id' => 'details',
                'name' => 'Details',
                'type' => 'text',
                'hidden' => true,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [[
                            'identifier' => 'stale-identifier',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => ['id' => 'accepted', 'type' => 'text'],
                                'value' => true,
                            ],
                        ]],
                    ],
                    'actions' => ['show-block'],
                ],
            ],
        ],
        'computed_variables' => [],
    ]);

    $condition = $normalized['properties'][1]['logic']['conditions']['children'][0];
    expect($condition['identifier'])->toBe('accepted')
        ->and($condition['value']['property_meta']['type'])->toBe('checkbox')
        ->and($condition['value']['operator'])->toBe('is_checked')
        ->and($condition['value'])->not->toHaveKey('value');
});

it('removes only unusable logic actions when valid actions remain', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            [
                'id' => 'target',
                'name' => 'Target',
                'type' => 'text',
                'logic' => [
                    'conditions' => [
                        'identifier' => 'source',
                        'value' => [
                            'operator' => 'equals',
                            'property_meta' => ['id' => 'source', 'type' => 'text'],
                            'value' => 'yes',
                        ],
                    ],
                    'actions' => ['hide-block', 'show-block', 'hide-block'],
                ],
            ],
        ],
        'computed_variables' => [],
    ]);

    expect($normalized['properties'][1]['logic']['actions'])->toBe(['hide-block']);
});

it('drops unusable select options while preserving valid response values', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [[
            'id' => 'choice',
            'name' => 'Choice',
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => null, 'name' => null],
                    ['name' => '  Keep me  '],
                    ['name' => '   '],
                    'invalid-option',
                ],
            ],
        ]],
    ]);

    expect($normalized['properties'][0]['select']['options'])->toBe([
        ['name' => 'Keep me', 'id' => 'Keep me'],
    ]);
});

it('canonicalizes missing options for a select that allows respondent-created values', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [[
            'id' => 'tags',
            'name' => 'Tags',
            'type' => 'multi_select',
            'allow_creation' => true,
            'multi_select' => 'invalid',
        ]],
    ]);

    expect($normalized['properties'][0]['multi_select'])->toBe(['options' => []]);
});

it('falls back to safe text option presentation when optional image settings are unusable', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [[
            'id' => 'choice',
            'name' => 'Choice',
            'type' => 'select',
            'option_display_mode' => 'image_only',
            'option_image_size' => 'huge',
            'select' => [
                'options' => [
                    ['id' => 'first', 'name' => 'First', 'image' => 'not-a-url'],
                ],
            ],
        ]],
    ]);

    expect($normalized['properties'][0]['option_display_mode'])->toBe('text_only')
        ->and($normalized['properties'][0])->not->toHaveKey('option_image_size')
        ->and($normalized['properties'][0]['select']['options'][0]['name'])->toBe('First')
        ->and($normalized['properties'][0]['select']['options'][0])->not->toHaveKey('image');

    $validator = validator($normalized, [
        'properties' => ['array', new \App\Rules\FormPropertiesRule()],
    ]);
    expect($validator->passes())->toBeTrue();
});

it('removes invalid computed variables and their invalid dependents in cascade', function () {
    $normalizer = app(FormDataNormalizer::class);
    $base = [
        'properties' => [
            ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
            [
                'id' => 'result',
                'name' => 'Result',
                'type' => 'text',
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [[
                            'identifier' => 'cv_broken',
                            'value' => [
                                'operator' => 'greater_than',
                                'property_meta' => ['id' => 'cv_broken', 'type' => 'computed'],
                                'value' => 10,
                            ],
                        ]],
                    ],
                    'actions' => ['show-block'],
                ],
            ],
        ],
        'computed_variables' => [
            [
                'id' => 'cv_broken',
                'name' => 'Broken',
                'formula' => '{missing}',
                'result_type' => 'number',
            ],
            [
                'id' => 'cv_dependent',
                'name' => 'Dependent',
                'formula' => '{cv_broken} + 1',
                'result_type' => 'number',
            ],
            [
                'id' => 'cv_valid',
                'name' => '  Valid  ',
                'formula' => '  {amount} + 1  ',
                'result_type' => 'number',
            ],
        ],
    ];

    $normalized = $normalizer->normalize($base);

    expect($normalized['computed_variables'])->toBe([
        [
            'id' => 'cv_valid',
            'name' => 'Valid',
            'formula' => '{amount} + 1',
            'result_type' => 'number',
        ],
    ])->and($normalized['properties'][1])->not->toHaveKey('logic');
});

it('clears a malformed computed variables collection', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'properties' => [
            ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
        ],
        'computed_variables' => 'invalid',
    ]);

    expect($normalized['computed_variables'])->toBe([]);
});

it('is idempotent after applying deterministic repairs', function () {
    $normalizer = app(FormDataNormalizer::class);
    $once = $normalizer->normalize([
        'properties' => [[
            'name' => 'Choice',
            'type' => 'select',
            'required' => 'true',
            'option_display_mode' => 'image_only',
            'select' => [
                'options' => [
                    ['name' => 'First', 'image' => 'invalid'],
                ],
            ],
            'logic' => ['conditions' => null, 'actions' => []],
        ]],
        'computed_variables' => 'invalid',
    ]);

    expect($normalizer->normalize($once))->toBe($once);
});
