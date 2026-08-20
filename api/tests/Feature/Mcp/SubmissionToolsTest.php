<?php

use App\Jobs\Form\ExportFormSubmissionsJob;
use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\ExportSubmissionsTool;
use App\Mcp\Tools\GetSubmissionExportTool;
use App\Mcp\Tools\GetSubmissionStatsTool;
use App\Mcp\Tools\GetSubmissionTool;
use App\Mcp\Tools\ListSubmissionsTool;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use App\Service\Billing\Feature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

function submissionFixture(array $completedValues = ['Alice', 'Bob'], ?User $user = null): array
{
    $user ??= User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['enable_partial_submissions' => true]);
    $fieldId = $form->properties[0]['id'];
    $submissions = collect($completedValues)->map(fn (string $value) => $form->submissions()->create([
        'data' => [$fieldId => $value],
        'status' => FormSubmission::STATUS_COMPLETED,
        'completion_time' => 30,
    ]));

    return compact('user', 'workspace', 'form', 'fieldId', 'submissions');
}

it('advertises OAuth-protected submission tools and no submission mutation tools', function () {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ];
    $headers = ['Accept' => 'application/json, text/event-stream'];

    $response = $this->postJson('/mcp', $payload, $headers)->assertOk();
    $tools = collect($response->json('result.tools'))->keyBy('name');

    expect($tools)->toHaveKeys(['list_submissions', 'get_submission_stats', 'export_submissions'])
        ->and($tools['list_submissions']['securitySchemes'])->toBe([
            [
                'type' => 'oauth2',
                'scopes' => ['mcp:use'],
            ],
        ]);

    $response->assertDontSee(['update_submission', 'delete_submission', 'create_submission']);

    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use'], 'oauth');

    $response = $this->postJson('/mcp', $payload, array_merge($headers, [
        'Authorization' => 'Bearer submission-scope-token',
    ]))->assertOk()
        ->assertSee(['list_submissions', 'get_submission_stats', 'export_submissions'])
        ->assertDontSee(['update_submission', 'delete_submission', 'create_submission']);

    $tools = collect($response->json('result.tools'))->keyBy('name');

    expect($tools['list_submissions']['annotations']['readOnlyHint'])->toBeTrue()
        ->and($tools['export_submissions']['annotations']['readOnlyHint'] ?? false)->toBeFalse();
});

it('searches response values and filters submission status without leaking other forms', function () {
    $fixture = submissionFixture(['Alice Example', 'Bob Example']);
    $partial = $fixture['form']->submissions()->create([
        'data' => [$fixture['fieldId'] => 'Alice Partial'],
        'status' => FormSubmission::STATUS_PARTIAL,
    ]);
    $other = submissionFixture(['Alice Secret']);

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ListSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
        'search' => 'Alice',
        'status' => 'completed',
    ])->assertOk()
        ->assertSee('Alice Example')
        ->assertDontSee(['Bob Example', 'Alice Partial', 'Alice Secret']);

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ListSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
        'status' => 'partial',
    ])->assertOk()->assertSee((string) $partial->id)->assertSee('Alice Partial');

    expect($other['form']->id)->not->toBe($fixture['form']->id);
});

it('lets readonly workspace members read submissions and rejects cross-form IDs', function () {
    $fixture = submissionFixture();
    $readonly = User::factory()->create();
    $fixture['workspace']->users()->attach($readonly, ['role' => User::ROLE_READONLY]);
    $submission = $fixture['submissions']->first();

    OpnFormServer::actingAs($readonly, 'oauth')->tool(GetSubmissionTool::class, [
        'form_id' => $fixture['form']->id,
        'submission_id' => $submission->id,
    ])->assertOk()->assertSee('Alice');

    $other = submissionFixture(['Outside']);
    OpnFormServer::actingAs($readonly, 'oauth')->tool(GetSubmissionTool::class, [
        'form_id' => $fixture['form']->id,
        'submission_id' => $other['submissions']->first()->id,
    ])->assertHasErrors(['not found or not accessible']);
});

it('returns bounded existing form analytics with filtered field summaries', function () {
    $fixture = submissionFixture(['Alice', 'Bob'], $this->createProUser());
    $fixture['form']->submissions()->create([
        'data' => [$fixture['fieldId'] => 'In progress'],
        'status' => FormSubmission::STATUS_PARTIAL,
        'completion_time' => 10,
    ]);

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
        'status' => 'completed',
    ])->assertOk()
        ->assertSee(['completed_submissions', 'partial_submissions', 'field_summary', 'processed_submissions'])
        ->assertSee('Alice')
        ->assertDontSee('In progress');
});

it('preserves the form summary plan entitlement', function () {
    $fixture = submissionFixture();

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertHasErrors(['Pro plan is required']);
});

it('requires both analytics and summary feature entitlements', function (string $grantedFeature) {
    $fixture = submissionFixture();
    $fixture['workspace']->update([
        'plan_overrides' => ['features' => [$grantedFeature]],
    ]);

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertHasErrors(['Pro plan is required']);
})->with([
    'summary only' => Feature::FORM_SUMMARY,
    'analytics only' => Feature::FORM_ANALYTICS,
]);

it('accepts open-ended submission date filters', function () {
    $dateFrom = now()->subDay()->toDateString();
    $fixture = submissionFixture(['Alice'], $this->createProUser());

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ListSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
        'date_from' => $dateFrom,
    ])->assertOk()->assertSee('Alice');

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
        'date_from' => $dateFrom,
    ])->assertOk()->assertSee('field_summary');
});

it('rate limits repeated submission statistics requests', function () {
    config()->set('opnform.form_summary_rate_limit_per_minute', 1);
    $fixture = submissionFixture(['Alice'], $this->createProUser());

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertOk();

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertHasErrors(['Too many submission statistics requests']);
});

it('shares the form summary rate limit between MCP and REST', function () {
    config()->set('opnform.form_summary_rate_limit_per_minute', 1);
    $fixture = submissionFixture(['Alice'], $this->createProUser());

    $this->actingAsUser($fixture['user']);
    $this->getJson(route('open.workspaces.form.summary', [$fixture['workspace'], $fixture['form']]))
        ->assertOk()
        ->assertHeader('X-RateLimit-Remaining', '0');

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionStatsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertHasErrors(['Too many submission statistics requests']);
});

it('queues private CSV exports and scopes status polling to the requesting account', function () {
    Queue::fake();
    $fixture = submissionFixture();
    $selectedId = $fixture['submissions']->first()->id;

    $response = OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ExportSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
        'submission_ids' => [$selectedId],
    ]);
    $response->assertOk()->assertSee(['job_id', 'queued', 'get_submission_export']);

    Queue::assertPushed(ExportFormSubmissionsJob::class, fn (ExportFormSubmissionsJob $job) => $job->userId === $fixture['user']->id
        && $job->form->is($fixture['form'])
        && $job->submissionIds === [$selectedId]);

    $jobId = Queue::pushed(ExportFormSubmissionsJob::class)->first()->jobId;
    $job = Cache::get('form_export_job:'.$jobId);
    expect($job['status'])->toBe('queued');

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(GetSubmissionExportTool::class, [
        'form_id' => $fixture['form']->id,
        'job_id' => $jobId,
    ])->assertOk()->assertSee(['queued', 'progress']);

    $otherMember = User::factory()->create();
    $fixture['workspace']->users()->attach($otherMember, ['role' => User::ROLE_USER]);
    OpnFormServer::actingAs($otherMember, 'oauth')->tool(GetSubmissionExportTool::class, [
        'form_id' => $fixture['form']->id,
        'job_id' => $jobId,
    ])->assertHasErrors(['not accessible']);
});

it('rejects exports containing submission IDs from another form', function () {
    Queue::fake();
    $fixture = submissionFixture();
    $other = submissionFixture(['Secret']);

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ExportSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
        'submission_ids' => [$other['submissions']->first()->id],
    ])->assertHasErrors(['not found in this form']);

    Queue::assertNotPushed(ExportFormSubmissionsJob::class);
});

it('rate limits repeated heavy export requests without affecting submission reads', function () {
    Queue::fake();
    config()->set('opnform.mcp.rate_limit.submission_exports_per_minute', 1);
    config()->set('opnform.mcp.rate_limit.submission_exports_per_hour', 10);
    $fixture = submissionFixture();

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ExportSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertOk();

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ExportSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertHasErrors(['Too many submission exports']);

    OpnFormServer::actingAs($fixture['user'], 'oauth')->tool(ListSubmissionsTool::class, [
        'form_id' => $fixture['form']->id,
    ])->assertOk()->assertSee('Alice');
});
