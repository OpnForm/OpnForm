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
