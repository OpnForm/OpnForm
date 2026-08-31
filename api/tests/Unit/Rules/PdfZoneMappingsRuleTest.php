<?php

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use App\Rules\PdfZoneMappingsRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

function createPdfZoneForm(array $attributes = []): Form
{
    $user = User::factory()->create();
    $workspace = Workspace::create(['name' => 'PDF Zone Workspace', 'icon' => '📝']);
    $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

    return Form::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withProperties($attributes['properties'] ?? [
            ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
            ['id' => 'agree', 'name' => 'Agree', 'type' => 'checkbox'],
        ])
        ->create(array_diff_key($attributes, ['properties' => true]));
}

function validPdfZone(array $overrides = []): array
{
    return array_merge([
        'id' => 'zone_1',
        'page_id' => 'page-1',
        'x' => 10,
        'y' => 10,
        'width' => 30,
        'height' => 5,
        'field_id' => 'name',
        'font_size' => 12,
        'font_color' => '#000000',
    ], $overrides);
}

describe('PdfZoneMappingsRule', function () {
    it('accepts form field ids', function () {
        $form = createPdfZoneForm();

        $validator = Validator::make(
            ['zone_mappings' => [validPdfZone(['field_id' => 'name'])]],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('accepts special field ids', function () {
        $form = createPdfZoneForm();

        $validator = Validator::make(
            ['zone_mappings' => [validPdfZone(['field_id' => 'submission_id'])]],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('accepts computed variable ids', function () {
        $form = createPdfZoneForm([
            'computed_variables' => [
                [
                    'id' => 'cv_yes_no',
                    'name' => 'Yes No',
                    'formula' => 'IF({agree}, "yes", "no")',
                ],
            ],
        ]);

        $validator = Validator::make(
            ['zone_mappings' => [validPdfZone(['field_id' => 'cv_yes_no'])]],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects unknown field ids', function () {
        $form = createPdfZoneForm([
            'computed_variables' => [
                [
                    'id' => 'cv_yes_no',
                    'name' => 'Yes No',
                    'formula' => 'IF({agree}, "yes", "no")',
                ],
            ],
        ]);

        $validator = Validator::make(
            ['zone_mappings' => [validPdfZone(['field_id' => 'missing_field'])]],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('zone_mappings'))->toContain("field_id 'missing_field' does not exist in form");
    });

    it('rejects unknown field ids when the form has no standard fields', function () {
        $form = createPdfZoneForm(['properties' => []]);

        $validator = Validator::make(
            ['zone_mappings' => [validPdfZone(['field_id' => 'missing_field'])]],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('zone_mappings'))->toContain("field_id 'missing_field' does not exist in form");
    });

    it('rejects non-string field ids', function () {
        $form = createPdfZoneForm();

        $validator = Validator::make(
            ['zone_mappings' => [validPdfZone(['field_id' => ['name']])]],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('zone_mappings'))->toContain('field_id must be a non-empty string');
    });

    it('still accepts static text and image zones', function () {
        $form = createPdfZoneForm();

        $zones = [
            [
                'id' => 'zone_text',
                'page_id' => 'page-1',
                'x' => 10,
                'y' => 10,
                'width' => 30,
                'height' => 5,
                'static_text' => 'Hello',
            ],
            [
                'id' => 'zone_image',
                'page_id' => 'page-1',
                'x' => 10,
                'y' => 20,
                'width' => 30,
                'height' => 20,
                'static_image' => 'https://example.com/image.png',
            ],
        ];

        $validator = Validator::make(
            ['zone_mappings' => $zones],
            ['zone_mappings' => [new PdfZoneMappingsRule($form)]]
        );

        expect($validator->passes())->toBeTrue();
    });
});
