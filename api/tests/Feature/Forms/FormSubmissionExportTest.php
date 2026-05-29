<?php

use App\Models\User;
use App\Models\Forms\FormSubmission;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function parseCsvRows(string $content): array
{
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

it('can export form submissions with selected columns', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'name_field',
                'name' => 'Name',
                'type' => 'text',
                'required' => true,
            ],
            [
                'id' => 'email_field',
                'name' => 'Email',
                'type' => 'email',
                'required' => true,
            ]
        ]
    ]);

    // Create some submissions
    $submissions = [
        ['name_field' => 'John Doe', 'email_field' => 'john@example.com'],
        ['name_field' => 'Jane Smith', 'email_field' => 'jane@example.com']
    ];

    foreach ($submissions as $submission) {
        $formData = $this->generateFormSubmissionData($form, $submission);
        $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful();
    }

    // Test export with selected columns
    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
        'columns' => [
            'name_field' => true,
            'email_field' => true,
            'created_at' => true
        ]
    ]));

    $response->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename=' . $form->slug . '-submission-data.csv');
});

it('exports only the selected submission ids', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $textField = collect($form->properties)->firstWhere('type', 'text');

    $firstSubmissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'John Selected',
    ]);
    $this->postJson(route('forms.answer', $form->slug), $firstSubmissionData)
        ->assertSuccessful();

    $firstSubmissionId = $form->refresh()->submissions()->orderByDesc('id')->first()->id;

    $secondSubmissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'Jane Not Selected',
    ]);
    $this->postJson(route('forms.answer', $form->slug), $secondSubmissionData)
        ->assertSuccessful();

    $secondSubmissionId = $form->refresh()->submissions()->orderByDesc('id')->first()->id;

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            $textField['id'] => true,
        ],
        'submissionIds' => [$firstSubmissionId],
    ]);

    $response->assertSuccessful();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect(str_contains($content, 'John Selected'))->toBeTrue();
    expect(str_contains($content, 'Jane Not Selected'))->toBeFalse();
    expect(str_contains($content, (string) $secondSubmissionId))->toBeFalse();
});

it('rejects submission ids that do not belong to the form', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $otherForm = $this->createForm($user, $workspace);

    $textField = collect($otherForm->properties)->firstWhere('type', 'text');
    $submissionData = $this->generateFormSubmissionData($otherForm, [
        $textField['id'] => 'Other Form Submission',
    ]);
    $this->postJson(route('forms.answer', $otherForm->slug), $submissionData)
        ->assertSuccessful();

    $otherSubmissionId = $otherForm->refresh()->submissions()->orderByDesc('id')->first()->id;

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            $textField['id'] => true,
        ],
        'submissionIds' => [$otherSubmissionId],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['submissionIds.0']);
});

it('accepts status as a valid export column', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $textField = collect($form->properties)->firstWhere('type', 'text');
    $submissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'John Doe',
    ]);
    $this->postJson(route('forms.answer', $form->slug), $submissionData)
        ->assertSuccessful();

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            $textField['id'] => true,
            'status' => true,
        ],
    ]);

    $response->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename=' . $form->slug . '-submission-data.csv');
});

it('cannot export form submissions with invalid columns', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'name_field',
                'name' => 'Name',
                'type' => 'text',
                'required' => true,
            ]
        ]
    ]);

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
        'columns' => [
            'invalid_field' => true,
            'name_field' => true
        ]
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['columns']);
});

it('cannot export form submissions from another user form', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);

    $form = createForm($user, $workspace);
    $textField = collect($form->properties)->firstWhere('type', 'text');

    $this->actingAsProUser();

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            $textField['id'] => true,
        ],
    ]);

    $response->assertJson([
        'message' => 'This action is unauthorized.'
    ]);
});

it('includes status column when partial submissions are enabled', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
    ]);

    // Create a partial submission (In Progress)
    $textField = collect($form->properties)->firstWhere('type', 'text');
    $partialSubmissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'John Partial',
    ]);
    $partialSubmissionData['is_partial'] = true;
    $partialResponse = $this->postJson(route('forms.answer', $form->slug), $partialSubmissionData);
    $partialResponse->assertSuccessful();

    // Create a completed submission
    $emailField = collect($form->properties)->firstWhere('type', 'email');
    $completedSubmissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'Jane Complete',
        $emailField['id'] => 'jane@example.com'
    ]);
    $completedResponse = $this->postJson(route('forms.answer', $form->slug), $completedSubmissionData);
    $completedResponse->assertSuccessful();

    // Verify counts before export
    $form->refresh();
    $total = $form->submissions()->count();
    $partialCount = $form->submissions()->where('status', \App\Models\Forms\FormSubmission::STATUS_PARTIAL)->count();

    // Export with selected columns (use real field ids)
    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
        'columns' => [
            $textField['id'] => true,
            $emailField['id'] => true,
        ]
    ]));

    $response->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename=' . $form->slug . '-submission-data.csv');

    // Verify the exported CSV contains status column and correct values
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect(str_contains($content, 'status'))->toBeTrue();
    expect(str_contains($content, 'In Progress'))->toBeTrue();
    expect(str_contains($content, 'Completed'))->toBeTrue();
});

it('preserves multiline values without corrupting CSV rows', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $textFieldId = collect($form->properties)->firstWhere('type', 'text')['id'];
    $emailFieldId = collect($form->properties)->firstWhere('type', 'email')['id'];

    $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [
            $textFieldId => 'Single line content',
            $emailFieldId => 'first@example.com',
        ],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $multilineValue = "First paragraph.\nSecond paragraph, with comma and \"quoted\" text.";
    $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [
            $textFieldId => $multilineValue,
            $emailFieldId => 'second@example.com',
        ],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            $textFieldId => true,
            $emailFieldId => true,
        ],
    ]);

    $response->assertSuccessful();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    $rows = parseCsvRows($content);

    expect(count($rows))->toBe(3); // Header + 2 data rows

    $headerColumnCount = count($rows[0]);
    foreach ($rows as $row) {
        expect(count($row))->toBe($headerColumnCount);
    }

    expect($content)->toContain('First paragraph.');
    expect($content)->toContain('Second paragraph, with comma and ""quoted"" text.');
    expect($content)->toMatch('/First paragraph\.(\r\n|\n)Second paragraph, with comma and ""quoted"" text\./');
});

it('does not include status column when partial submissions are disabled', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => false,
    ]);

    // Create a submission
    $textField = collect($form->properties)->firstWhere('type', 'text');
    $submissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'John Doe',
    ]);
    $this->postJson(route('forms.answer', $form->slug), $submissionData);

    // Export with selected columns (use a real field id)
    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
        'columns' => [
            $textField['id'] => true,
        ]
    ]));

    $response->assertSuccessful();

    // Verify the exported CSV does not contain status column
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    expect(str_contains($content, 'status'))->toBeFalse();
});

it('exports file urls with a durable expiration and a valid signature', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'file_field',
                'name' => 'Upload',
                'type' => 'files',
                'required' => false,
            ],
        ],
    ]);

    Storage::fake();
    $fileName = 'test-signature.png';
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'signed file content');

    $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [
            'file_field' => [$fileName],
        ],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            'file_field' => true,
        ],
    ]);

    $response->assertSuccessful();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    $rows = parseCsvRows($content);
    $fileColumnIndex = array_search('Upload', $rows[0], true);
    $exportedFileUrl = $rows[1][$fileColumnIndex];

    parse_str(parse_url($exportedFileUrl, PHP_URL_QUERY), $queryParameters);

    expect($queryParameters)->toHaveKeys(['expires', 'signature']);
    expect((int) $queryParameters['expires'])->toBeGreaterThan(now()->addMinutes(FormSubmissionFormatter::SIGNED_FILE_URL_EXPIRATION_MINUTES - 1)->timestamp);

    $this->get($exportedFileUrl)->assertOk();

    $this->travel(6)->minutes();

    $this->get($exportedFileUrl)->assertOk();

    $this->travelBack();
});

it('allows export status polling when the general api rate limit is exhausted', function () {
    $this->withMiddleware(ThrottleRequests::class);

    $router = app('router');
    $apiMiddleware = $router->getMiddlewareGroups()['api'];
    array_unshift($apiMiddleware, 'throttle:100,1');
    $router->middlewareGroup('api', $apiMiddleware);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $jobId = (string) Str::uuid();
    Cache::put('form_export_job:' . $jobId, [
        'status' => 'processing',
        'progress' => 50,
        'form_id' => $form->id,
        'user_id' => $user->id,
        'created_at' => now()->toISOString(),
        'updated_at' => now()->toISOString(),
    ], now()->addHour());

    for ($i = 0; $i < 100; $i++) {
        $this->getJson(route('open.forms.submissions.index', ['form' => $form]))
            ->assertSuccessful();
    }

    $this->getJson(route('open.forms.submissions.index', ['form' => $form]))
        ->assertStatus(429);

    $this->getJson(route('open.forms.submissions.export.status', [
        'form' => $form,
        'jobId' => $jobId,
    ]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'processing');
});
