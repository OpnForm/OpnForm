<?php

use Illuminate\Support\Facades\Artisan;

it('audits form definitions without modifying them', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $validForm = $this->createForm($user, $workspace);
    $validForm->forceFill([
        'properties' => [['id' => 'valid', 'name' => 'Valid', 'type' => 'text']],
        'computed_variables' => [],
    ])->save();
    $repairableForm = $this->createForm($user, $workspace);
    $repairableProperties = [
        ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
        ['id' => 'target', 'name' => 'Target', 'type' => 'text'],
    ];
    $repairableProperties[1]['logic'] = [
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [[
                'identifier' => 'removed',
                'value' => [
                    'operator' => 'equals',
                    'property_meta' => ['id' => 'removed', 'type' => 'text'],
                    'value' => 'yes',
                ],
            ]],
        ],
        'actions' => ['hide-block'],
    ];
    $repairableForm->forceFill([
        'properties' => $repairableProperties,
        'computed_variables' => [],
    ])->save();
    $invalidForm = $this->createForm($user, $workspace);
    $invalidForm->forceFill([
        'properties' => [[
            'id' => 'choice',
            'name' => 'Choice',
            'type' => 'select',
            'select' => ['options' => []],
        ]],
        'computed_variables' => [],
    ])->save();
    $repairableUpdatedAt = $repairableForm->updated_at->toISOString();
    $invalidUpdatedAt = $invalidForm->updated_at->toISOString();

    expect(Artisan::call('forms:audit-definitions', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report)
        ->toMatchArray([
            'forms_checked' => 3,
            'valid_forms' => 2,
            'auto_repaired_forms' => 1,
            'invalid_forms_count' => 1,
            'issues_by_code' => ['invalid_definition' => 1],
        ])
        ->and($report['invalid_forms'][0]['form_id'])->toBe($invalidForm->id);

    expect($validForm->fresh())->not->toBeNull()
        ->and($repairableForm->fresh()->updated_at->toISOString())->toBe($repairableUpdatedAt)
        ->and($invalidForm->fresh()->updated_at->toISOString())->toBe($invalidUpdatedAt);
});
