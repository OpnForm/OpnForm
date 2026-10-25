<?php

use App\Service\Forms\FormValidationIssueMapper;

it('maps validation messages to stable issue codes and metadata', function () {
    $issues = (new FormValidationIssueMapper())->fromErrors([
        'properties.2.logic.conditions.children.0.value.property_meta.id' => [
            'The referenced field or computed variable [deleted_field] no longer exists.',
        ],
        'computed_variables.1.formula' => [
            'Circular dependency detected: Total → Tax → Total',
        ],
    ]);

    expect($issues)->toHaveCount(2)
        ->and($issues[0])->toMatchArray([
            'code' => 'unknown_reference',
            'path' => 'properties.2.logic.conditions.children.0.value.property_meta.id',
            'meta' => ['reference_id' => 'deleted_field'],
        ])
        ->and($issues[1]['code'])->toBe('cyclic_dependency');
});

it('omits aggregate messages and caps the response', function () {
    $errors = [
        'properties' => ['One or more properties have validation errors.'],
    ];

    foreach (range(1, FormValidationIssueMapper::MAX_ISSUES + 20) as $index) {
        $errors["properties.{$index}.name"] = ['The field name is required.'];
    }

    $issues = (new FormValidationIssueMapper())->fromErrors($errors);

    $mapper = new FormValidationIssueMapper();

    expect($issues)->toHaveCount(FormValidationIssueMapper::MAX_ISSUES)
        ->and($mapper->count($errors))->toBe(FormValidationIssueMapper::MAX_ISSUES + 20)
        ->and($mapper->summary($mapper->count($errors)))
        ->toBe('The form contains 120 issues that must be fixed before saving.')
        ->and(collect($issues)->pluck('message'))->not->toContain('One or more properties have validation errors.');
});

it('preserves paths in MCP-safe validation messages', function () {
    $mapper = new FormValidationIssueMapper();
    $errors = [
        'properties.2.logic.conditions.children.0.value.property_meta.id' => [
            'The referenced field or computed variable [deleted_field] no longer exists.',
        ],
        'properties' => ['One or more properties have validation errors.'],
    ];

    expect($mapper->pathErrors($errors))->toBe([
        'properties.2.logic.conditions.children.0.value.property_meta.id' => [
            'properties.2.logic.conditions.children.0.value.property_meta.id: The referenced field or computed variable [deleted_field] no longer exists.',
        ],
    ]);
});
