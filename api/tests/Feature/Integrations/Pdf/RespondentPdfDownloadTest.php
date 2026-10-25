<?php

use App\Models\PdfTemplate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $owner = $this->createProUser();
    $workspace = $this->createUserWorkspace($owner);
    $this->pdfForm = $this->createForm($owner, $workspace, ['pdf_download_enabled' => true]);

    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Respondent PDF regression');
    $content = $pdf->Output('S');
    $path = "pdf-templates/{$this->pdfForm->id}/respondent.pdf";
    Storage::put($path, $content);
    $this->pdfTemplate = PdfTemplate::create([
        'form_id' => $this->pdfForm->id,
        'name' => 'Respondent PDF',
        'filename' => 'respondent.pdf',
        'original_filename' => 'respondent.pdf',
        'file_path' => $path,
        'file_size' => strlen($content),
        'page_count' => 1,
        'zone_mappings' => [],
        'filename_pattern' => PdfTemplate::DEFAULT_FILENAME_PATTERN,
    ]);
    $this->pdfForm->update(['pdf_template_id' => $this->pdfTemplate->id]);
});

function submitForRespondentPdf($test)
{
    return $test->postJson(
        route('forms.answer', $test->pdfForm->slug),
        $test->generateFormSubmissionData($test->pdfForm)
    )->assertSuccessful();
}

it('lets an anonymous respondent download the PDF returned by their completed submission', function () {
    $response = submitForRespondentPdf($this);
    $url = $response->json('pdf_download_url');
    $submission = $this->pdfForm->submissions()->sole();

    expect($url)->toBeString()->toContain($submission->public_id)->toContain('signature=');
    $this->assertGuest();
    $download = $this->get($url)->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    expect($download->streamedContent())->toStartWith('%PDF-');

    // Knowing the submission UUID cannot mint additional unbounded download links.
    $this->getJson(route('open.forms.pdf-templates.submission.signed-url', [
        'form' => $this->pdfForm,
        'pdfTemplate' => $this->pdfTemplate,
        'submission_id' => $submission->public_id,
    ]))->assertUnauthorized();
});

it('rejects switching the signed URL to a different submission', function () {
    $url = submitForRespondentPdf($this)->json('pdf_download_url');
    $original = $this->pdfForm->submissions()->sole();
    $other = $this->pdfForm->submissions()->create(['data' => []]);
    $otherId = \App\Service\Forms\SubmissionUrlService::getSubmissionIdentifier($other);

    $this->get(str_replace($original->public_id, $otherId, $url))->assertForbidden();
});

it('rejects the respondent download after its 24 hour lifetime', function () {
    $url = submitForRespondentPdf($this)->json('pdf_download_url');
    $this->travel(25)->hours();

    $this->get($url)->assertForbidden();
});

it('does not issue a download URL when PDF download is disabled', function () {
    $this->pdfForm->update(['pdf_download_enabled' => false]);

    submitForRespondentPdf($this)->assertJsonPath('pdf_download_url', null);
});

it('does not issue a URL for a PDF template belonging to another form', function () {
    $other = $this->createForm($this->pdfForm->creator, $this->pdfForm->workspace);
    $this->pdfTemplate->update(['form_id' => $other->id]);

    submitForRespondentPdf($this)->assertJsonPath('pdf_download_url', null);
});

it('does not issue a download URL for a partial draft', function () {
    $business = $this->createBusinessUser();
    $workspace = $this->createUserWorkspace($business);
    $this->pdfForm->update([
        'creator_id' => $business->id,
        'workspace_id' => $workspace->id,
        'enable_partial_submissions' => true,
    ]);
    $this->pdfForm->refresh();
    $data = $this->generateFormSubmissionData($this->pdfForm);
    $data['is_partial'] = true;

    $this->postJson(route('forms.answer', $this->pdfForm->slug), $data)
        ->assertSuccessful()
        ->assertJsonStructure(['submission_hash'])
        ->assertJsonMissingPath('pdf_download_url');
});
