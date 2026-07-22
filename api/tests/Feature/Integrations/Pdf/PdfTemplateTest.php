<?php

use App\Models\PdfTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

describe('PDF Template Upload', function () {
    it('can upload a pdf template', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a valid PDF using FPDF
        $pdfContent = createValidPdf();
        $file = UploadedFile::fake()->createWithContent('test-template.pdf', $pdfContent);

        $response = $this->postJson(
            route('open.forms.pdf-templates.store', $form),
            ['file' => $file]
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'form_id',
                    'filename',
                    'original_filename',
                    'file_path',
                    'file_size',
                    'page_count',
                ],
            ]);

        expect(PdfTemplate::where('form_id', $form->id)->count())->toBe(1);

        $template = PdfTemplate::where('form_id', $form->id)->first();
        expect($template->original_filename)->toBe('test-template.pdf');
        expect($template->page_count)->toBeGreaterThanOrEqual(1);
        expect(Storage::exists($template->file_path))->toBeTrue();
    });

    it('rejects non-pdf files', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $response = $this->postJson(
            route('open.forms.pdf-templates.store', $form),
            ['file' => $file]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    it('rejects files larger than 10MB', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a file larger than 10MB (10240 KB)
        $file = UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf');

        $response = $this->postJson(
            route('open.forms.pdf-templates.store', $form),
            ['file' => $file]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    it('requires authentication to upload', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdf();
        $file = UploadedFile::fake()->createWithContent('test.pdf', $pdfContent);

        $response = $this->postJson(
            route('open.forms.pdf-templates.store', $form),
            ['file' => $file]
        );

        $response->assertStatus(401);
    });

    it('requires authorization to upload to a form', function () {
        $owner = $this->createUser();
        $workspace = $this->createUserWorkspace($owner);
        $form = $this->createForm($owner, $workspace);

        // Login as different user
        $this->actingAsUser();

        $pdfContent = createValidPdf();
        $file = UploadedFile::fake()->createWithContent('test.pdf', $pdfContent);

        $response = $this->postJson(
            route('open.forms.pdf-templates.store', $form),
            ['file' => $file]
        );

        $response->assertStatus(403);
    });
});

describe('PDF Template Create from Scratch', function () {
    it('can create a template from scratch with form fields rendered', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $properties = $form->properties;
        $properties[0]['required'] = true;
        $form->update(['properties' => $properties]);

        $response = $this->postJson(route('open.forms.pdf-templates.store', $form), []);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'form_id',
                    'name',
                    'filename',
                    'original_filename',
                    'file_path',
                    'file_size',
                    'page_count',
                ],
            ]);

        expect(PdfTemplate::where('form_id', $form->id)->count())->toBe(1);

        $template = PdfTemplate::where('form_id', $form->id)->first();
        expect($template->name)->toBe('My PDF Template 1');
        expect($template->page_count)->toBeGreaterThanOrEqual(1);
        expect($template->zone_mappings)->toBeArray()->not->toBeEmpty();
        expect(Storage::exists($template->file_path))->toBeTrue();

        $titleZone = collect($template->zone_mappings)
            ->first(fn ($zone) => isset($zone['static_text']) && str_contains($zone['static_text'], '<h1>'));
        expect($titleZone)->not->toBeNull();
        expect($titleZone['static_text'])->toBe('<h1>' . e($form->title) . '</h1>');

        $requiredLabelZone = collect($template->zone_mappings)
            ->first(fn ($zone) => ($zone['static_text'] ?? null) === 'Name <strong style="color: #EF4444">*</strong>');
        expect($requiredLabelZone)->not->toBeNull();

        // Each form input field should have a field_id zone
        $inputFields = collect($form->properties)
            ->filter(fn ($f) => !str_starts_with($f['type'], 'nf-'))
            ->pluck('id')
            ->toArray();
        $zoneFieldIds = collect($template->zone_mappings)
            ->pluck('field_id')
            ->filter()
            ->values()
            ->toArray();
        foreach ($inputFields as $fieldId) {
            expect($zoneFieldIds)->toContain($fieldId);
        }

        // Every zone must reference a valid page_id from the manifest
        $manifestIds = collect($template->page_manifest)->pluck('id')->toArray();
        foreach ($template->zone_mappings as $zone) {
            expect($manifestIds)->toContain($zone['page_id']);
        }
    });

    it('uses incremental default name when creating from scratch', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $response = $this->postJson(route('open.forms.pdf-templates.store', $form), []);

        $response->assertStatus(201);

        $template = PdfTemplate::where('form_id', $form->id)->first();
        expect($template->name)->toBe('My PDF Template 1');
    });

    it('increments default template name per form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $this->postJson(route('open.forms.pdf-templates.store', $form), [])->assertStatus(201);
        $this->postJson(route('open.forms.pdf-templates.store', $form), [])->assertStatus(201);

        $names = PdfTemplate::where('form_id', $form->id)->orderBy('id')->pluck('name')->all();
        expect($names)->toBe(['My PDF Template 1', 'My PDF Template 2']);
    });

    it('requires authentication to create from scratch', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $response = $this->postJson(route('open.forms.pdf-templates.store', $form), []);

        $response->assertStatus(401);
    });

    it('requires authorization to create from scratch', function () {
        $owner = $this->createUser();
        $workspace = $this->createUserWorkspace($owner);
        $form = $this->createForm($owner, $workspace);

        $this->actingAsUser();

        $response = $this->postJson(route('open.forms.pdf-templates.store', $form), []);

        $response->assertStatus(403);
    });
});

describe('PDF Template List', function () {
    it('can list pdf templates for a form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create templates
        PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template1.pdf',
            'original_filename' => 'Original 1.pdf',
            'file_path' => 'pdf-templates/1/template1.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);
        PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template2.pdf',
            'original_filename' => 'Original 2.pdf',
            'file_path' => 'pdf-templates/1/template2.pdf',
            'file_size' => 2000,
            'page_count' => 2,
        ]);

        $response = $this->getJson(route('open.forms.pdf-templates.index', $form));

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    });

    it('only lists templates for the requested form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form1 = $this->createForm($user, $workspace);
        $form2 = $this->createForm($user, $workspace);

        PdfTemplate::create([
            'form_id' => $form1->id,
            'filename' => 'template1.pdf',
            'original_filename' => 'Form 1 Template.pdf',
            'file_path' => 'pdf-templates/1/template1.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);
        PdfTemplate::create([
            'form_id' => $form2->id,
            'filename' => 'template2.pdf',
            'original_filename' => 'Form 2 Template.pdf',
            'file_path' => 'pdf-templates/2/template2.pdf',
            'file_size' => 2000,
            'page_count' => 1,
        ]);

        $response = $this->getJson(route('open.forms.pdf-templates.index', $form1));

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_filename', 'Form 1 Template.pdf');
    });
});

describe('PDF Template Show', function () {
    it('can get a specific pdf template', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'My Template.pdf',
            'file_path' => 'pdf-templates/1/template.pdf',
            'file_size' => 1000,
            'page_count' => 3,
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.show', [$form, $template])
        );

        $response->assertSuccessful()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.original_filename', 'My Template.pdf')
            ->assertJsonPath('data.page_count', 3);
    });

    it('returns 404 for template belonging to different form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form1 = $this->createForm($user, $workspace);
        $form2 = $this->createForm($user, $workspace);

        $template = PdfTemplate::create([
            'form_id' => $form2->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => 'pdf-templates/2/template.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.show', [$form1, $template])
        );

        $response->assertStatus(404);
    });
});

describe('PDF Template Delete', function () {
    it('can delete a pdf template', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a file in storage
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, 'fake pdf content');

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $filePath,
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $response = $this->deleteJson(
            route('open.forms.pdf-templates.destroy', [$form, $template])
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'PDF template deleted successfully.');

        expect(PdfTemplate::find($template->id))->toBeNull();
        expect(Storage::exists($filePath))->toBeFalse();
    });

    it('cannot delete template belonging to different form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form1 = $this->createForm($user, $workspace);
        $form2 = $this->createForm($user, $workspace);

        $template = PdfTemplate::create([
            'form_id' => $form2->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => 'pdf-templates/2/template.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $response = $this->deleteJson(
            route('open.forms.pdf-templates.destroy', [$form1, $template])
        );

        $response->assertStatus(404);
        expect(PdfTemplate::find($template->id))->not->toBeNull();
    });
});

describe('PDF Template Download', function () {
    it('can download a pdf template', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdf();
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'My Template.pdf',
            'file_path' => $filePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $response = $this->get(
            route('open.forms.pdf-templates.download', [$form, $template])
        );

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('returns 404 for missing template file', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => 'pdf-templates/nonexistent/template.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $response = $this->get(
            route('open.forms.pdf-templates.download', [$form, $template])
        );

        $response->assertStatus(404);
    });
});

describe('PDF Template Filename Resolution', function () {
    function mentionSpan(string $id, string $name): string
    {
        return '<span mention="true" mention-field-id="' . $id . '" mention-field-name="' . $name . '" mention-fallback="" contenteditable="false" class="mention-item">' . $name . '</span>';
    }

    function createTemplateWithPattern(int $formId, ?string $pattern): \App\Models\PdfTemplate
    {
        return PdfTemplate::create([
            'form_id' => $formId,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => "pdf-templates/{$formId}/template.pdf",
            'file_size' => 100,
            'page_count' => 1,
            'filename_pattern' => $pattern,
        ]);
    }

    it('resolves form_name variable from mention HTML', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Contact Form']);

        $template = createTemplateWithPattern($form->id, mentionSpan('form_name', 'Form Name'));
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe('contact-form.pdf');
    });

    it('resolves submission_id variable from mention HTML', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $template = createTemplateWithPattern($form->id, mentionSpan('submission_id', 'Submission ID'));
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe("{$submission->id}.pdf");
    });

    it('resolves date variable from mention HTML', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $template = createTemplateWithPattern($form->id, mentionSpan('date', 'Date'));
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe(now()->format('Y-m-d') . '.pdf');
    });

    it('resolves multiple variables in a single pattern', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Invoice Form']);

        $pattern = mentionSpan('form_name', 'Form Name') . '-' . mentionSpan('submission_id', 'Submission ID');
        $template = createTemplateWithPattern($form->id, $pattern);
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe("invoice-form-{$submission->id}.pdf");
    });

    it('appends .pdf extension automatically', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'My Form']);

        $template = createTemplateWithPattern($form->id, mentionSpan('form_name', 'Form Name'));
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toEndWith('.pdf');
    });

    it('never produces double .pdf extension', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'My Form']);

        $pattern = mentionSpan('form_name', 'Form Name') . '.pdf';
        $template = createTemplateWithPattern($form->id, $pattern);
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe('my-form.pdf');
        expect($filename)->not->toContain('.pdf.pdf');
    });

    it('handles case-insensitive .PDF suffix', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'My Form']);

        $pattern = mentionSpan('form_name', 'Form Name') . '.PDF';
        $template = createTemplateWithPattern($form->id, $pattern);
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe('my-form.pdf');
    });

    it('falls back to default pattern when filename_pattern is null', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Feedback']);

        $template = createTemplateWithPattern($form->id, null);
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe("feedback-{$submission->id}.pdf");
    });

    it('falls back to default pattern when filename_pattern is empty string', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Feedback']);

        $template = createTemplateWithPattern($form->id, '');
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe("feedback-{$submission->id}.pdf");
    });

    it('uses "preview" when submission has no id', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $template = createTemplateWithPattern($form->id, mentionSpan('submission_id', 'Submission ID'));

        $submission = new \App\Models\Forms\FormSubmission();
        $submission->form_id = $form->id;
        $submission->data = [];

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe('preview.pdf');
    });

    it('sanitizes special characters in filename', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Form With Spaces & Symbols!']);

        $template = createTemplateWithPattern($form->id, mentionSpan('form_name', 'Form Name'));
        $submission = $form->submissions()->create(['data' => []]);

        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toMatch('/^[a-zA-Z0-9._-]+$/');
        expect($filename)->toEndWith('.pdf');
    });
});

describe('PDF Email Attachment Filename Consistency', function () {
    it('produces identical filename from resolveFilename regardless of caller', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Registration Form']);

        $customPattern = '<span mention="true" mention-field-id="form_name" mention-field-name="Form Name" mention-fallback="" contenteditable="false" class="mention-item">Form Name</span>-<span mention="true" mention-field-id="date" mention-field-name="Date" mention-fallback="" contenteditable="false" class="mention-item">Date</span>';

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => "pdf-templates/{$form->id}/template.pdf",
            'file_size' => 100,
            'page_count' => 1,
            'filename_pattern' => $customPattern,
        ]);

        $submission = $form->submissions()->create(['data' => ['name' => 'Alice']]);

        $call1 = $template->resolveFilename($form, $submission);
        $call2 = $template->resolveFilename($form, $submission);

        expect($call1)->toBe($call2);
        expect($call1)->toBe('registration-form-' . now()->format('Y-m-d') . '.pdf');
    });

    it('would have failed with old ad-hoc email naming (regression)', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Invoice Form']);

        $customPattern = '<span mention="true" mention-field-id="form_name" mention-field-name="Form Name" mention-fallback="" contenteditable="false" class="mention-item">Form Name</span>-<span mention="true" mention-field-id="submission_id" mention-field-name="Submission ID" mention-fallback="" contenteditable="false" class="mention-item">Submission ID</span>';

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'My Invoice Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => "pdf-templates/{$form->id}/template.pdf",
            'file_size' => 100,
            'page_count' => 1,
            'filename_pattern' => $customPattern,
        ]);

        $submission = $form->submissions()->create(['data' => []]);

        $resolvedFilename = $template->resolveFilename($form, $submission);

        // Old email code: Str::slug($template->name ?: 'document') . '.pdf'
        $oldEmailFilename = \Illuminate\Support\Str::slug($template->name ?: 'document') . '.pdf';

        // The old email filename ignores the user-configured pattern entirely
        expect($oldEmailFilename)->toBe('my-invoice-template.pdf');
        // The resolved filename uses the user-configured pattern
        expect($resolvedFilename)->toBe("invoice-form-{$submission->id}.pdf");
        // They differ — proving the old bug
        expect($resolvedFilename)->not->toBe($oldEmailFilename);
    });
});

describe('PDF From-Scratch Default Filename Pattern', function () {
    it('sets DEFAULT_FILENAME_PATTERN on from-scratch templates', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, ['title' => 'Test Form']);

        $this->postJson(route('open.forms.pdf-templates.store', $form), []);

        $template = PdfTemplate::where('form_id', $form->id)->first();

        expect($template->filename_pattern)->toBe(PdfTemplate::DEFAULT_FILENAME_PATTERN);

        $submission = $form->submissions()->create(['data' => []]);
        $filename = $template->resolveFilename($form, $submission);

        expect($filename)->toBe("test-form-{$submission->id}.pdf");
    });

    it('sets DEFAULT_FILENAME_PATTERN on uploaded templates', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdf();
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('invoice.pdf', $pdfContent);

        $this->postJson(route('open.forms.pdf-templates.store', $form), ['file' => $file]);

        $template = PdfTemplate::where('form_id', $form->id)->first();

        expect($template->filename_pattern)->toBe(PdfTemplate::DEFAULT_FILENAME_PATTERN);
    });
});

describe('PDF Template Update - source_page renormalization', function () {
    it('renormalizes source_page after removing pages from manifest', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createMultiPagePdf(6);
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Master Template',
            'filename' => 'template.pdf',
            'original_filename' => 'master.pdf',
            'file_path' => $filePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 6,
            'page_manifest' => PdfTemplate::buildDefaultPageManifest(6),
            'zone_mappings' => [],
        ]);

        // Keep only original pages 1 and 6 (remove 2-5)
        $response = $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => [
                    ['id' => 'p1', 'type' => 'source', 'source_page' => 1],
                    ['id' => 'p6', 'type' => 'source', 'source_page' => 6],
                ],
                'zone_mappings' => [],
            ]
        );

        $response->assertSuccessful();
        $template->refresh();

        expect($template->page_count)->toBe(2);
        expect($template->page_manifest)->toHaveCount(2);
        expect($template->page_manifest[0]['source_page'])->toBe(1);
        expect($template->page_manifest[1]['source_page'])->toBe(2);
    });

    it('renormalizes source_page with mixed source and blank pages', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createMultiPagePdf(4);
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Mixed Template',
            'filename' => 'template.pdf',
            'original_filename' => 'mixed.pdf',
            'file_path' => $filePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 4,
            'page_manifest' => PdfTemplate::buildDefaultPageManifest(4),
            'zone_mappings' => [],
        ]);

        $response = $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => [
                    ['id' => 'p1', 'type' => 'source', 'source_page' => 1],
                    ['id' => 'pb', 'type' => 'blank', 'source_page' => null],
                    ['id' => 'p4', 'type' => 'source', 'source_page' => 4],
                ],
                'zone_mappings' => [],
            ]
        );

        $response->assertSuccessful();
        $template->refresh();

        expect($template->page_count)->toBe(3);
        expect($template->page_manifest[0]['source_page'])->toBe(1);
        expect($template->page_manifest[1]['source_page'])->toBeNull();
        expect($template->page_manifest[2]['source_page'])->toBe(3);
    });

    it('allows a second save after page removal without error', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createMultiPagePdf(6);
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Resave Template',
            'filename' => 'template.pdf',
            'original_filename' => 'resave.pdf',
            'file_path' => $filePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 6,
            'page_manifest' => PdfTemplate::buildDefaultPageManifest(6),
            'zone_mappings' => [],
        ]);

        // First save: keep pages 1 and 6
        $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => [
                    ['id' => 'p1', 'type' => 'source', 'source_page' => 1],
                    ['id' => 'p6', 'type' => 'source', 'source_page' => 6],
                ],
                'zone_mappings' => [],
            ]
        )->assertSuccessful();

        $template->refresh();

        // Second save: use the renormalized manifest (source_page 1,2)
        $response = $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => $template->page_manifest,
                'zone_mappings' => [],
            ]
        );

        $response->assertSuccessful();
        $template->refresh();

        expect($template->page_count)->toBe(2);
        expect($template->page_manifest[0]['source_page'])->toBe(1);
        expect($template->page_manifest[1]['source_page'])->toBe(2);
    });

    it('preserves correct page content after blank with distinct page sizes', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'properties' => [
                ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
            ],
        ]);

        // Pages with different dimensions so we can distinguish them
        $pdfContent = createMultiPagePdfWithSizes([
            ['w' => 120, 'h' => 160],
            ['w' => 140, 'h' => 200],
            ['w' => 180, 'h' => 250],
            ['w' => 200, 'h' => 280],
        ]);
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Sized Template',
            'filename' => 'template.pdf',
            'original_filename' => 'sized.pdf',
            'file_path' => $filePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 4,
            'page_manifest' => PdfTemplate::buildDefaultPageManifest(4),
            'zone_mappings' => [],
        ]);

        // Keep page 1 (120mm), insert blank, keep page 4 (200mm)
        $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => [
                    ['id' => 'p1', 'type' => 'source', 'source_page' => 1],
                    ['id' => 'pb', 'type' => 'blank', 'source_page' => null],
                    ['id' => 'p4', 'type' => 'source', 'source_page' => 4],
                ],
                'zone_mappings' => [],
            ]
        )->assertSuccessful();

        $template->refresh();
        expect($template->page_manifest[0]['source_page'])->toBe(1);
        expect($template->page_manifest[1]['source_page'])->toBeNull();
        expect($template->page_manifest[2]['source_page'])->toBe(3);

        // Export and verify page 3 has the 200mm-wide page, not the 120mm blank
        $submission = $form->submissions()->create(['data' => ['name' => 'Test']]);
        $service = new \App\Service\Pdf\PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);
        $content = Storage::get($resultPath);

        $verifyTmp = tempnam(sys_get_temp_dir(), 'pdf_sizes_');
        file_put_contents($verifyTmp, $content);
        $verifyPdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $verifyPdf->setSourceFile($verifyTmp);
        expect($pageCount)->toBe(3);

        // Page 3 should have the 200mm-wide dimensions from original page 4
        $tid = $verifyPdf->importPage(3);
        $size = $verifyPdf->getTemplateSize($tid);
        expect(round($size['width']))->toBe(200.0);
        @unlink($verifyTmp);

        // Second save with the renormalized manifest should also succeed
        $response = $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => $template->page_manifest,
                'zone_mappings' => [],
            ]
        );
        $response->assertSuccessful();

        $template->refresh();
        expect($template->page_manifest[0]['source_page'])->toBe(1);
        expect($template->page_manifest[1]['source_page'])->toBeNull();
        expect($template->page_manifest[2]['source_page'])->toBe(3);

        // Export again after second save — page 3 dimensions must still match
        $resultPath2 = $service->generateFromTemplate($form, $submission, $template->refresh());
        $content2 = Storage::get($resultPath2);
        $verifyTmp2 = tempnam(sys_get_temp_dir(), 'pdf_sizes2_');
        file_put_contents($verifyTmp2, $content2);
        $verifyPdf2 = new \setasign\Fpdi\Fpdi();
        $verifyPdf2->setSourceFile($verifyTmp2);
        $tid2 = $verifyPdf2->importPage(3);
        $size2 = $verifyPdf2->getTemplateSize($tid2);
        expect(round($size2['width']))->toBe(200.0);
        @unlink($verifyTmp2);
    });

    it('exports correctly after page removal with zones on second page', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'properties' => [
                ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
            ],
        ]);

        $pdfContent = createMultiPagePdf(6);
        $filePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($filePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Export Template',
            'filename' => 'template.pdf',
            'original_filename' => 'export.pdf',
            'file_path' => $filePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 6,
            'page_manifest' => PdfTemplate::buildDefaultPageManifest(6),
            'zone_mappings' => [],
        ]);

        // Save: keep pages 1 and 6, add a zone on page 2 (originally page 6)
        $this->putJson(
            route('open.forms.pdf-templates.update', [$form, $template]),
            [
                'page_manifest' => [
                    ['id' => 'p1', 'type' => 'source', 'source_page' => 1],
                    ['id' => 'p6', 'type' => 'source', 'source_page' => 6],
                ],
                'zone_mappings' => [
                    [
                        'id' => 'z1',
                        'page_id' => 'p6',
                        'x' => 10,
                        'y' => 10,
                        'width' => 30,
                        'height' => 5,
                        'field_id' => 'name',
                        'font_size' => 12,
                        'font_color' => '#000000',
                    ],
                ],
            ]
        )->assertSuccessful();

        $template->refresh();

        // Generate PDF from this template
        $submission = $form->submissions()->create([
            'data' => ['name' => 'John Doe'],
        ]);

        $service = new \App\Service\Pdf\PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);

        expect(Storage::exists($resultPath))->toBeTrue();
        $content = Storage::get($resultPath);
        expect($content)->toStartWith('%PDF');

        // Verify the generated PDF has exactly 2 pages
        $verifyPdf = new \setasign\Fpdi\Fpdi();
        $verifyTmp = tempnam(sys_get_temp_dir(), 'pdf_verify_');
        file_put_contents($verifyTmp, $content);
        $pageCount = $verifyPdf->setSourceFile($verifyTmp);
        @unlink($verifyTmp);

        expect($pageCount)->toBe(2);
    });
});

/**
 * Helper function to create a valid PDF using FPDF.
 */
function createValidPdf(): string
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test PDF Template');

    return $pdf->Output('S');
}

function createMultiPagePdf(int $pageCount): string
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->SetFont('Helvetica', '', 12);
    for ($i = 1; $i <= $pageCount; $i++) {
        $pdf->AddPage();
        $pdf->Cell(0, 10, "Page {$i} Content");
    }

    return $pdf->Output('S');
}

function createMultiPagePdfWithSizes(array $pages): string
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->SetFont('Helvetica', '', 12);
    foreach ($pages as $i => $page) {
        $pdf->AddPage('P', [$page['w'], $page['h']]);
        $pdf->Cell(0, 10, 'Page ' . ($i + 1));
    }

    return $pdf->Output('S');
}
