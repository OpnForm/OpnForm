<?php

use App\Jobs\Form\ExportFormSubmissionsJob;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use App\Service\Forms\ExternalSubmissionFileLinkPolicy;
use App\Service\Forms\FormExportService;
use App\Service\Forms\SubmissionAttribution;
use App\Service\Storage\FileUploadPathService;
use Carbon\Carbon;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

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
            ],
        ],
    ]);

    // Create some submissions
    $submissions = [
        ['name_field' => 'John Doe', 'email_field' => 'john@example.com'],
        ['name_field' => 'Jane Smith', 'email_field' => 'jane@example.com'],
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
            'created_at' => true,
        ],
    ]));

    $response->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename='.$form->slug.'-submission-data.csv');
});

it('exports a valid Excel workbook with text preserved', function (bool $async) {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $textField = collect($form->properties)->firstWhere('type', 'text');
    $answer = "=Example, café\nSecond line";

    $this->postJson(route('forms.answer', $form->slug), $this->generateFormSubmissionData($form, [
        $textField['id'] => $answer,
    ]))->assertSuccessful();

    if ($async) {
        Storage::fake();
        $exportService = Mockery::mock(FormExportService::class, [app(ExternalSubmissionFileLinkPolicy::class)])->makePartial();
        $exportService->shouldReceive('shouldExportAsync')->once()->andReturnTrue();
        $this->app->instance(FormExportService::class, $exportService);
    }

    $response = $this->postJson(route('open.forms.submissions.export', ['form' => $form]), [
        'columns' => [$textField['id'] => true],
        'format' => 'xlsx',
    ])->assertSuccessful();

    if ($async) {
        $response->assertJsonPath('is_async', true);
        $this->getJson(route('open.forms.submissions.export.status', [
            'form' => $form,
            'jobId' => $response->json('job_id'),
        ]))->assertJsonPath('status', 'completed');
        $files = Storage::allFiles(FormExportService::EXPORT_FILE_PATH);
        expect($files)->toHaveCount(1);
        $path = Storage::path($files[0]);
    } else {
        $response->assertHeader('content-disposition', 'attachment; filename='.$form->slug.'-submission-data.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
    }

    expect($path)->toEndWith('.xlsx');
    $sheet = (new Xlsx())->load($path)->getActiveSheet();
    expect((string) $sheet->getCell('B1')->getValue())->toBe($textField['name'])
        ->and((string) $sheet->getCell('B2')->getValue())->toBe($answer)
        ->and($sheet->getCell('B2')->getDataType())->not->toBe(DataType::TYPE_FORMULA);
})->with(['direct download' => false, 'background export' => true]);

it('exports numeric form fields as Excel numbers without losing text identifiers or precision', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
            ['id' => 'identifier', 'name' => 'Identifier', 'type' => 'text'],
            ['id' => 'precise_number', 'name' => 'Precise number', 'type' => 'number'],
        ],
    ]);

    $this->postJson(route('forms.answer', $form->slug), [
        'amount' => '42.5',
        'identifier' => '00123',
        'precise_number' => '12345678901234567',
    ])->assertSuccessful();

    $response = $this->postJson(route('open.forms.submissions.export', ['form' => $form]), [
        'columns' => ['amount' => true, 'identifier' => true, 'precise_number' => true],
        'format' => 'xlsx',
    ])->assertSuccessful();

    $path = $response->baseResponse->getFile()->getPathname();
    $sheet = (new Xlsx())->load($path)->getActiveSheet();
    expect($sheet->getCell('B2')->getValue())->toBe(42.5)
        ->and($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_NUMERIC)
        ->and((string) $sheet->getCell('C2')->getValue())->toBe('00123')
        ->and((string) $sheet->getCell('D2')->getValue())->toBe('12345678901234567')
        ->and($sheet->getCell('D2')->getDataType())->not->toBe(DataType::TYPE_NUMERIC);
});

it('offers CSV for text Excel cannot store and preserves the full CSV value', function (string $answer, string $message) {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $textField = collect($form->properties)->firstWhere('type', 'text');
    $form->submissions()->create(['data' => [$textField['id'] => $answer]]);

    $columns = [$textField['id'] => true];
    $route = route('open.forms.submissions.export', ['form' => $form]);

    $this->postJson($route, ['columns' => $columns, 'format' => 'xlsx'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.format.0', $message);

    $response = $this->postJson($route, ['columns' => $columns, 'format' => 'csv'])->assertSuccessful();
    ob_start();
    $response->sendContent();
    expect(parseCsvRows(ob_get_clean())[1][1])->toBe($answer);

    Storage::fake();
    $exportService = app(FormExportService::class);
    $jobId = $exportService->initializeAsyncExport($form, $user->id);
    expect(fn () => ExportFormSubmissionsJob::dispatchSync($form, $columns, [], $jobId, $user->id, 'xlsx'))
        ->toThrow(ValidationException::class, $message);
    expect(Cache::get($exportService->getCacheKey($jobId)))
        ->status->toBe('failed')
        ->error_message->toBe($message);
    expect(Storage::allFiles(FormExportService::EXPORT_FILE_PATH))->toBeEmpty();
})->with([
    'text exceeding the cell limit' => [
        str_repeat('a', 32_768),
        'A cell exceeds Excel’s 32,767 character limit. Choose CSV to export the full text.',
    ],
    'text incompatible with workbook XML' => [
        "Text with \u{FFFE}",
        'A cell contains a character that Excel cannot store. Choose CSV to preserve the original text.',
    ],
]);

it('exports selected attribution columns and leaves unselected attribution out', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $textField = collect($form->properties)->firstWhere('type', 'text');

    $submissionData = $this->generateFormSubmissionData($form, [
        $textField['id'] => 'Attributed submission',
    ]);
    $submissionData['tracking_parameters'] = [
        'utm_source' => 'newsletter',
        'gclid' => 'not-exported',
    ];

    $this->postJson(route('forms.answer', $form->slug), $submissionData)
        ->assertSuccessful();

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
    ]), [
        'columns' => [
            $textField['id'] => true,
            SubmissionAttribution::columnId('utm_source') => true,
        ],
    ]);

    $response->assertSuccessful();

    ob_start();
    $response->sendContent();
    $rows = parseCsvRows(ob_get_clean());

    expect($rows[0])->toContain('utm_source')
        ->not->toContain('gclid')
        ->and($rows[1])->toContain('newsletter')
        ->not->toContain('not-exported');
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
        ->assertHeader('content-disposition', 'attachment; filename='.$form->slug.'-submission-data.csv');
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
            ],
        ],
    ]);

    $response = $this->postJson(route('open.forms.submissions.export', [
        'form' => $form,
        'columns' => [
            'invalid_field' => true,
            'name_field' => true,
        ],
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
        'message' => 'This action is unauthorized.',
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
        $emailField['id'] => 'jane@example.com',
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
        ],
    ]));

    $response->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename='.$form->slug.'-submission-data.csv');

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
        ],
    ]));

    $response->assertSuccessful();

    // Verify the exported CSV does not contain status column
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    expect(str_contains($content, 'status'))->toBeFalse();
});

it('exports file urls with the workspace policy expiration and a valid signature', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'settings' => [
            'external_file_links' => [
                'expires_in_hours' => 168,
            ],
        ],
    ]);
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
    expect((int) $queryParameters['expires'])->toBeGreaterThan(now()->addHours(168)->subMinute()->timestamp);

    $this->get($exportedFileUrl)->assertOk();

    $this->travel(6)->minutes();

    $this->get($exportedFileUrl)->assertOk();

    $this->travelBack();
});

it('uses the workspace policy for asynchronous CSV download links', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'settings' => [
            'external_file_links' => [
                'expires_in_hours' => 72,
            ],
        ],
    ]);
    $form = $this->createForm($user, $workspace);
    $form->load('workspace');

    Storage::fake();
    $now = Carbon::parse('2026-07-17 17:00:00');
    Carbon::setTestNow($now);

    try {
        $exportService = app(FormExportService::class);
        $expiresAt = $exportService->fileLinkExpiresAt($form);
        $fileUrl = $exportService->generateAndUploadFile([
            ['id' => 'submission-1'],
        ], 'weekend-submissions.csv', $expiresAt);
    } finally {
        Carbon::setTestNow();
    }

    parse_str((string) parse_url($fileUrl, PHP_URL_QUERY), $queryParameters);

    expect((int) ($queryParameters['expiration'] ?? 0))->toBe($now->copy()->addHours(72)->timestamp);
    Storage::assertExists(FormExportService::EXPORT_FILE_PATH.'weekend-submissions.csv');
});

it('reads export pages in date order while excluding new rows and avoiding gaps after deletions', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $createSubmission = function (?string $createdAt) use ($form) {
        $submission = $form->submissions()->create(['data' => []]);
        $submission->forceFill(['created_at' => $createdAt])->save();

        return $submission;
    };

    $older = $createSubmission('2026-11-19 23:00:00');
    $nullDate = $createSubmission(null);
    $tie = $createSubmission('2026-11-20 23:00:00');
    $sameDate = $createSubmission('2026-11-20 23:00:00');

    $exportService = app(FormExportService::class);
    $query = $exportService->submissionQuery($form, []);
    $ids = [];
    foreach ($exportService->iterateSubmissions($query, 2) as $submission) {
        $ids[] = $submission->id;
        if (count($ids) === 2) {
            $sameDate->delete();
            $createSubmission('2026-11-18 23:00:00');
        }
    }

    expect($ids)->toBe([$sameDate->id, $tie->id, $older->id, $nullDate->id]);
});

it('stores same-second exports for different selections as separate files', function (string $format) {
    Storage::fake();
    $this->freezeTime();

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $textField = collect($form->properties)->firstWhere('type', 'text');
    $columns = [$textField['id'] => true];
    $exportService = app(FormExportService::class);
    $paths = [];

    foreach (['First selection', 'Second selection'] as $answer) {
        $submission = $form->submissions()->create(['data' => [$textField['id'] => $answer]]);
        $jobId = $exportService->initializeAsyncExport($form, $user->id);
        ExportFormSubmissionsJob::dispatchSync($form, $columns, [$submission->id], $jobId, $user->id, $format);

        $status = Cache::get($exportService->getCacheKey($jobId));
        expect($status['status'])->toBe('completed');
        $paths[$answer] = Storage::allFiles(FormExportService::EXPORT_FILE_PATH.$jobId)[0];
    }

    expect(basename($paths['First selection']))->toBe(basename($paths['Second selection']));

    foreach ($paths as $answer => $path) {
        $exportedAnswer = $format === 'xlsx'
            ? (string) (new Xlsx())->load(Storage::path($path))->getActiveSheet()->getCell('B2')->getValue()
            : parseCsvRows(Storage::get($path))[1][1];

        expect($exportedAnswer)->toBe($answer);
    }
})->with(['csv', 'xlsx']);

it('includes a UTF-8 BOM in asynchronous CSV exports', function () {
    Storage::fake();
    $exportService = app(FormExportService::class);
    $fileName = 'unicode-submissions.csv';

    $exportService->generateAndUploadFile([
        ['name' => 'பெயர்'],
    ], $fileName, now()->addHour());

    $csv = Storage::get(FormExportService::EXPORT_FILE_PATH.$fileName);

    expect($csv)
        ->toStartWith("\xEF\xBB\xBF")
        ->toContain('பெயர்');
});

it('allows export status polling when the general api rate limit is exhausted', function () {
    $this->withMiddleware(ThrottleRequests::class);

    $router = app('router');
    $apiMiddleware = $router->getMiddlewareGroups()['api'];
    array_unshift($apiMiddleware, 'throttle:internal-api');
    $router->middlewareGroup('api', $apiMiddleware);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $jobId = (string) Str::uuid();
    Cache::put('form_export_job:'.$jobId, [
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

it('serves export status at the documented path with the documented fields', function (string $authentication) {
    $user = $this->createProUser();
    if ($authentication === 'token') {
        $this->withToken($user->createToken('Export status', ['forms-read'])->plainTextToken);
    } else {
        $this->actingAsProUser($user);
    }
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $jobId = app(FormExportService::class)->initializeAsyncExport($form, $user->id);

    // Keep in sync with docs/api-reference/submissions/export-status.mdx
    $response = $this->getJson("/open/forms/{$form->id}/submissions/export/status/{$jobId}")
        ->assertSuccessful()
        ->assertJsonPath('job_id', $jobId)
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('progress', 0);

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'job_id',
        'status',
        'progress',
        'processed_submissions',
        'total_submissions',
        'file_url',
        'error_message',
        'expires_at',
        'created_at',
        'updated_at',
    ]);
})->with(['session', 'token']);

it('rejects export status tokens without read access', function (array $abilities) {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $jobId = app(FormExportService::class)->initializeAsyncExport($form, $user->id);

    $this->withToken($user->createToken('Export status', $abilities)->plainTextToken)
        ->getJson("/open/forms/{$form->id}/submissions/export/status/{$jobId}")
        ->assertForbidden();
})->with([
    'write only' => [['forms-write']],
    'integration management only' => [['manage-integrations']],
    'no abilities' => [[]],
]);

it('rejects export jobs belonging to another form', function (string $authentication) {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $otherUser = $this->createProUser();
    $otherForm = $this->createForm($otherUser, $this->createUserWorkspace($otherUser));
    $jobId = app(FormExportService::class)->initializeAsyncExport($otherForm, $otherUser->id);

    if ($authentication === 'token') {
        $this->withToken($user->createToken('Export status', ['forms-read'])->plainTextToken);
    } else {
        $this->actingAsProUser($user);
    }

    $this->getJson("/open/forms/{$form->id}/submissions/export/status/{$jobId}")
        ->assertNotFound();
    $this->getJson("/open/forms/{$otherForm->id}/submissions/export/status/{$jobId}")
        ->assertForbidden();
})->with(['session', 'token']);

it('returns not found for unknown export jobs', function () {
    $user = $this->createProUser();
    $form = $this->createForm($user, $this->createUserWorkspace($user));
    $jobId = (string) Str::uuid();

    $this->withToken($user->createToken('Export status', ['forms-read'])->plainTextToken)
        ->getJson("/open/forms/{$form->id}/submissions/export/status/{$jobId}")
        ->assertNotFound();
});

it('exports IP metadata through the same request used by the submissions table', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['enable_ip_tracking' => true]);
    $form->submissions()->create([
        'data' => [], 'meta' => ['ip_address' => '192.0.2.15'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $response = $this->postJson(route('open.forms.submissions.export', ['form' => $form]), [
        'columns' => ['ip_address' => true, 'created_at' => true],
    ])->assertSuccessful();
    ob_start();
    $response->sendContent();
    $rows = parseCsvRows(ob_get_clean());
    expect($rows[0])->toContain('ip_address')->and($rows[1])->toContain('192.0.2.15');
});

it('does not expose stored IP metadata without the tracking setting and feature', function (bool $tracking, bool $business) {
    $user = $business ? $this->actingAsBusinessUser() : $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['enable_ip_tracking' => $tracking]);
    $submission = $form->submissions()->create([
        'data' => [], 'meta' => ['ip_address' => '192.0.2.15'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $row = app(FormExportService::class)->formatSubmissionForExport($form, $submission, ['ip_address' => true]);
    expect($row)->not->toHaveKey('ip_address')->not->toContain('192.0.2.15');
})->with([[false, true], [true, false]]);

it('matches requested field IDs exactly when another label contains the ID', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            ['id' => 'prenom', 'name' => 'Prénom', 'type' => 'text'],
            ['id' => 'nom', 'name' => 'Nom', 'type' => 'text'],
        ],
        'removed_properties' => [['id' => 'old_nom', 'name' => 'Old Nom', 'type' => 'text']],
    ]);
    $submission = $form->submissions()->create([
        'data' => ['prenom' => 'Alice', 'nom' => 'Dupont', 'old_nom' => 'Archived'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $row = app(FormExportService::class)->formatSubmissionForExport($form, $submission, [
        'prenom' => true, 'nom' => true, 'old_nom' => true,
    ]);
    expect($row)->toHaveCount(4)
        ->and($row['Prénom (prenom)'])->toBe('Alice')
        ->and($row['Nom (nom)'])->toBe('Dupont');
    expect(array_values($row))->toContain('Archived');
});

it('preserves a form field whose ID matches the IP metadata column', function (bool $tracking) {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => $tracking,
        'properties' => [['id' => 'ip_address', 'name' => 'Server IP', 'type' => 'text']],
    ]);
    $submission = $form->submissions()->create([
        'data' => ['ip_address' => '203.0.113.10'],
        'meta' => ['ip_address' => '192.0.2.15'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $row = app(FormExportService::class)->formatSubmissionForExport($form, $submission, ['ip_address' => true]);

    expect($row['Server IP (ip_address)'] ?? null)->toBe('203.0.113.10');
    if ($tracking) {
        expect($row['ip_address'])->toBe('192.0.2.15');
    } else {
        expect($row)->not->toHaveKey('ip_address');
    }
})->with([true, false]);
