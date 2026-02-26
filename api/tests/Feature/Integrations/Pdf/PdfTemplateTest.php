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
    it('can create a template with 1 blank page', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $response = $this->postJson(route('open.forms.pdf-templates.store-from-scratch', $form));

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
        expect($template->name)->toBe('Untitled Template');
        expect($template->page_count)->toBe(1);
        expect($template->zone_mappings)->toBe([]);
        expect(Storage::exists($template->file_path))->toBeTrue();
    });

    it('accepts optional name when creating from scratch', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $response = $this->postJson(route('open.forms.pdf-templates.store-from-scratch', $form));

        $response->assertStatus(201);

        $template = PdfTemplate::where('form_id', $form->id)->first();
        expect($template->name)->toBe('Untitled Template');
    });

    it('requires authentication to create from scratch', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $response = $this->postJson(route('open.forms.pdf-templates.store-from-scratch', $form));

        $response->assertStatus(401);
    });

    it('requires authorization to create from scratch', function () {
        $owner = $this->createUser();
        $workspace = $this->createUserWorkspace($owner);
        $form = $this->createForm($owner, $workspace);

        $this->actingAsUser();

        $response = $this->postJson(route('open.forms.pdf-templates.store-from-scratch', $form));

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

        $this->postJson(route('open.forms.pdf-templates.store-from-scratch', $form));

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
