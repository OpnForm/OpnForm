<?php

use App\Models\PdfTemplate;
use App\Service\Pdf\PdfCacheService;
use App\Service\Pdf\PdfGeneratorService;
use Illuminate\Support\Facades\Storage;

it('invalidates generated PDFs when answer data changes within the same second', function () {
    Storage::fake('local');
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $submission = $form->submissions()->create(['data' => ['field' => 'old']]);
    $template = new PdfTemplate(['form_id' => $form->id]);
    $template->id = 1; $template->updated_at = now();
    $generator = Mockery::mock(PdfGeneratorService::class);
    $generator->shouldReceive('generateFromTemplate')->twice()->andReturnUsing(function () use ($submission) {
        $path = 'tmp/pdf-output/' . $submission->data['field'] . '.pdf';
        Storage::put($path, $submission->data['field']);
        return $path;
    });
    $service = new PdfCacheService();
    expect($service->getOrGenerateFromTemplate($form, $submission, $template, $generator))->toContain('old.pdf');
    expect($service->getOrGenerateFromTemplate($form, $submission, $template, $generator))->toContain('old.pdf');
    $submission->data = ['field' => 'new'];
    expect($service->getOrGenerateFromTemplate($form, $submission, $template, $generator))->toContain('new.pdf');
});
