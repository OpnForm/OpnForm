<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Support\Str;

it('sanitizes rich_text and maps files in FormSubmissionResource', function () {
    config()->set('app.url', 'https://forms.example.test');

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Add fields: rich_text, files, and a plain text field
    $richId = 'rt_' . Str::uuid()->toString();
    $filesId = 'fl_' . Str::uuid()->toString();
    $textId  = 'tx_' . Str::uuid()->toString();

    $extra = [
        ['id' => $richId, 'name' => 'Rich', 'type' => 'rich_text'],
        ['id' => $filesId, 'name' => 'Files', 'type' => 'files'],
        ['id' => $textId,  'name' => 'Text', 'type' => 'text'],
    ];
    $form->properties = array_merge($form->properties, $extra);
    $form->save();

    $rtPayload = "<p onclick=alert(1)><script>alert(1)</script><a href=javascript:alert(1)>bad</a><strong>ok</strong></p>";
    $files = ['example_1.png', 'doc_2.pdf'];
    $textPayload = "<b>do not execute</b>";

    // Create submission directly
    /** @var FormSubmission $submission */
    $submission = $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [
            $richId => $rtPayload,
            $filesId => $files,
            $textId => $textPayload,
        ],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    // GET submissions (resource collection)
    $response = $this->getJson(route('open.forms.submissions.index', [$form->id]))
        ->assertOk();

    $first = $response->json('data.0');
    expect($first)->not->toBeNull();

    $data = $first['data'];
    // Rich text is cleaned but preserves allowed formatting
    expect($data[$richId])->not->toContain('<script');
    expect($data[$richId])->not->toContain('onclick');
    expect($data[$richId])->not->toContain('javascript:');
    expect($data[$richId])->toContain('<strong>ok</strong>');

    // Files are mapped to signed URLs with file_name
    expect($data[$filesId])->toBeArray();
    expect($data[$filesId])->toHaveCount(2);
    expect($data[$filesId][0]['file_name'])->toBe($files[0]);
    expect($data[$filesId][0]['file_url'])
        ->toStartWith('https://forms.example.test/open/forms/')
        ->toContain('signature=');

    // Plain text remains raw (escaping is at render time)
    expect($data[$textId])->toBe($textPayload);
});

it('normalizes an encoded legacy file name before exposing its signed URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $filesId = 'fl_' . Str::uuid()->toString();
    $form->properties = array_merge($form->properties, [
        ['id' => $filesId, 'name' => 'Files', 'type' => 'files'],
    ]);
    $form->save();

    $fileName = 'receipt_550e8400-e29b-41d4-a716-446655440000.png';
    $submission = $form->submissions()->create([
        'data' => [$filesId => [\App\Service\Storage\FilenameUrlEncoder::encode($fileName)]],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $response = $this->getJson(route('open.forms.submissions.fetch', [$form->id, $submission->id]))
        ->assertOk();

    $file = $response->json('data')[$filesId][0];
    expect($file['file_name'])->toBe($fileName)
        ->and($file['file_url'])->toContain(\App\Service\Storage\FilenameUrlEncoder::encode($fileName));
});
