<?php

use App\Models\PdfTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($this->owner);
    $this->sourceForm = $this->createForm($this->owner, $this->workspace);
    $this->targetForm = $this->createForm($this->owner, $this->workspace, [
        'properties' => [['id' => 'target-name', 'type' => 'text', 'name' => 'Destination name']],
        'computed_variables' => [['id' => 'target-total', 'name' => 'Total', 'formula' => '1']],
    ]);
    $this->sourceFieldId = $this->sourceForm->properties[0]['id'];
    $zone = ['id' => 'field-zone', 'page_id' => 'page-one', 'page' => 1, 'x' => 5, 'y' => 5, 'width' => 40, 'height' => 10, 'font_size' => 12];
    $this->sourceTemplate = PdfTemplate::create([
        'form_id' => $this->sourceForm->id,
        'name' => 'Original',
        'filename' => 'original.pdf',
        'original_filename' => 'uploaded.pdf',
        'file_path' => "pdf-templates/{$this->sourceForm->id}/original.pdf",
        'file_size' => 100,
        'page_count' => 1,
        'page_manifest' => [['id' => 'page-one', 'type' => 'source', 'source_page' => 1]],
        'zone_mappings' => [
            [...$zone, 'field_id' => $this->sourceFieldId, 'field_name' => 'Source name'],
            [...$zone, 'id' => 'label', 'static_text' => '<b>Name</b>'],
            [...$zone, 'id' => 'image', 'static_image' => 'https://example.test/logo.png'],
            [...$zone, 'id' => 'special', 'field_id' => 'submission_id'],
        ],
        'filename_pattern' => 'custom-filename',
        'remove_branding' => true,
    ]);
    Storage::put($this->sourceTemplate->file_path, 'original PDF bytes');
    $this->endpoint = route('open.forms.pdf-templates.duplicate', [$this->sourceForm, $this->sourceTemplate]);
});

it('duplicates all template settings into an independent file in the same form', function () {
    $response = $this->postJson($this->endpoint, ['target_form_id' => $this->sourceForm->id, 'name' => 'Copy']);
    $response->assertCreated();
    $copy = PdfTemplate::findOrFail($response->json('data.id'));
    expect($copy->id)->not->toBe($this->sourceTemplate->id);
    expect($copy->file_path)->not->toBe($this->sourceTemplate->file_path);
    expect($copy->zone_mappings)->toBe($this->sourceTemplate->zone_mappings);
    expect($copy->page_manifest)->toBe($this->sourceTemplate->page_manifest);
    expect($copy->filename_pattern)->toBe('custom-filename');
    expect($copy->remove_branding)->toBeTrue();
    expect($copy->original_filename)->toBe('uploaded.pdf');
    expect(Storage::get($copy->file_path))->toBe('original PDF bytes');

    Storage::put($copy->file_path, 'edited copy');
    expect(Storage::get($this->sourceTemplate->file_path))->toBe('original PDF bytes');
    $this->deleteJson(route('open.forms.pdf-templates.destroy', [$this->sourceForm, $copy]))->assertOk();
    expect(Storage::exists($this->sourceTemplate->file_path))->toBeTrue();
    expect($this->sourceTemplate->fresh()->name)->toBe('Original');
});

it('remaps fields to another form while preserving static zones and special fields', function () {
    $this->postJson($this->endpoint, [
        'target_form_id' => $this->targetForm->id,
        'name' => 'Destination copy',
        'field_mappings' => [$this->sourceFieldId => 'target-name'],
    ])->assertCreated()->assertJsonPath('data.form_id', $this->targetForm->id)
        ->assertJsonPath('data.zone_mappings.0.field_id', 'target-name')
        ->assertJsonPath('data.zone_mappings.0.field_name', 'Destination name')
        ->assertJsonPath('data.zone_mappings.1.static_text', '<b>Name</b>')
        ->assertJsonPath('data.zone_mappings.2.static_image', 'https://example.test/logo.png')
        ->assertJsonPath('data.zone_mappings.3.field_id', 'submission_id');
    expect($this->sourceTemplate->fresh()->zone_mappings[0]['field_id'])->toBe($this->sourceFieldId);
});

it('supports computed variable destinations and explicitly skipped fields', function () {
    $this->postJson($this->endpoint, [
        'target_form_id' => $this->targetForm->id, 'name' => 'Variable copy',
        'field_mappings' => [$this->sourceFieldId => 'target-total'],
    ])->assertCreated()->assertJsonPath('data.zone_mappings.0.field_id', 'target-total');

    $response = $this->postJson($this->endpoint, [
        'target_form_id' => $this->targetForm->id, 'name' => 'Static copy',
        'field_mappings' => [$this->sourceFieldId => null],
    ])->assertCreated();
    expect($response->json('data.zone_mappings'))->toHaveCount(3);
    expect($response->json('data.zone_mappings.0.static_text'))->toBe('<b>Name</b>');
});

it('requires explicit mappings and rejects fields outside the destination', function (array $mappings) {
    $this->postJson($this->endpoint, [
        'target_form_id' => $this->targetForm->id, 'name' => 'Copy', 'field_mappings' => $mappings,
    ])->assertUnprocessable()->assertJsonValidationErrors('field_mappings');
    expect(PdfTemplate::count())->toBe(1);
    expect(Storage::allFiles())->toHaveCount(1);
})->with([
    'missing mappings' => [[]],
    'unknown source field' => [['unknown-source' => 'target-name']],
]);

it('rejects invalid mapped destination fields including layout blocks', function () {
    $this->targetForm->update(['properties' => [['id' => 'heading', 'type' => 'nf-text', 'name' => 'Heading']]]);
    foreach ([$this->sourceFieldId, 'heading', 'not-a-field', 'submission_id'] as $fieldId) {
        $this->postJson($this->endpoint, [
            'target_form_id' => $this->targetForm->id, 'name' => 'Copy',
            'field_mappings' => [$this->sourceFieldId => $fieldId],
        ])->assertUnprocessable()->assertJsonValidationErrors('field_mappings');
    }
    expect(PdfTemplate::count())->toBe(1);
});

it('rejects copying to another workspace even when the user owns both', function () {
    $otherWorkspace = $this->createUserWorkspace($this->owner);
    $otherForm = $this->createForm($this->owner, $otherWorkspace);
    $this->postJson($this->endpoint, ['target_form_id' => $otherForm->id, 'name' => 'Copy'])->assertNotFound();
    expect(PdfTemplate::count())->toBe(1);
});

it('requires authentication and edit access', function () {
    $this->actingAsGuest();
    $this->postJson($this->endpoint, ['target_form_id' => $this->sourceForm->id, 'name' => 'Copy'])->assertUnauthorized();
    $this->actingAsUser();
    $this->postJson($this->endpoint, ['target_form_id' => $this->sourceForm->id, 'name' => 'Copy'])->assertForbidden();
});

it('denies readonly workspace members', function () {
    $reader = $this->createUser();
    $this->workspace->users()->attach($reader, ['role' => User::ROLE_READONLY]);
    $this->actingAsGuest();
    $this->actingAsUser($reader);
    $this->postJson($this->endpoint, ['target_form_id' => $this->sourceForm->id, 'name' => 'Copy'])->assertForbidden();
});

it('checks the source template belongs to the source form', function () {
    $endpoint = route('open.forms.pdf-templates.duplicate', [$this->targetForm, $this->sourceTemplate]);
    $this->postJson($endpoint, ['target_form_id' => $this->targetForm->id, 'name' => 'Copy'])->assertNotFound();
});

it('does not create a template when the source file is missing', function () {
    Storage::delete($this->sourceTemplate->file_path);
    $this->postJson($this->endpoint, ['target_form_id' => $this->sourceForm->id, 'name' => 'Copy'])->assertNotFound();
    expect(PdfTemplate::count())->toBe(1);
});

it('cleans up the copied file when creating the database record fails', function () {
    PdfTemplate::creating(function ($template) {
        if ($template->name === 'Failed copy') {
            throw new RuntimeException('Simulated database failure');
        }
    });
    $this->postJson($this->endpoint, ['target_form_id' => $this->sourceForm->id, 'name' => 'Failed copy'])->assertStatus(500);
    expect(PdfTemplate::count())->toBe(1);
    expect(Storage::allFiles())->toBe([$this->sourceTemplate->file_path]);
});

it('validates the copy name and target form', function () {
    $this->postJson($this->endpoint, [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'target_form_id']);
    $this->postJson($this->endpoint, ['target_form_id' => 999999999, 'name' => 'Copy'])->assertNotFound();
    expect(PdfTemplate::count())->toBe(1);
});
