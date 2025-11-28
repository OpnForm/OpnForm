<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Support\Str;

it('sanitizes rich_text and maps files in FormSubmissionResource', function () {
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
    expect($data[$filesId][0]['file_url'])->toBeString();

    // Plain text remains raw (escaping is at render time)
    expect($data[$textId])->toBe($textPayload);
});
