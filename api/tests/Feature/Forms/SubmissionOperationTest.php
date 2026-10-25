<?php

use App\Events\Forms\FormSubmitted;
use App\Events\Models\FormIntegrationCreated;
use App\Events\Models\FormIntegrationSaved;
use App\Events\Models\FormSubmissionSaved;
use App\Jobs\Form\StoreFormSubmissionJob;
use App\Models\Forms\FormSubmission;
use App\Models\Forms\SubmissionOperation;
use App\Models\Integration\FormIntegration;
use App\Service\Forms\SubmissionOperationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->realEvents = Event::getFacadeRoot();
    Event::fake([FormSubmitted::class, FormIntegrationCreated::class, FormIntegrationSaved::class]);
    Queue::fake();
    Mail::fake();
    Notification::fake();
    Http::preventStrayRequests();
    Storage::fake();
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $this->field = '11111111-1111-4111-8111-111111111111';
    $this->form = $this->createForm($user, $workspace, ['properties' => [
        ['id' => $this->field, 'name' => 'Fictif', 'type' => 'text'],
    ]]);
});

it('replays the original serialized payload without a second row or event', function () {
    $payload = serialize(new StoreFormSubmissionJob($this->form, [
        $this->field => 'fixture', 'completion_time' => 42, 'tracking_parameters' => ['utm_source' => 'fixture'],
    ]));
    $first = unserialize($payload);
    $first->handle();
    $replay = unserialize($payload);
    $replay->handle();

    expect($this->form->submissions()->count())->toBe(1)
        ->and(SubmissionOperation::count())->toBe(1)
        ->and($replay->getSubmissionId())->toBe($first->getSubmissionId())
        ->and($replay->getProcessedData())->toBe($first->getProcessedData())
        ->and(SubmissionOperation::first()->outbox_state)->toBe('dispatched');
    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('can retry the same job instance without changing its payload fingerprint', function () {
    $data = [$this->field => 'fixture', 'completion_time' => 42, 'is_partial' => false];
    $job = new StoreFormSubmissionJob($this->form, $data);
    $job->handle();
    $job->handle();
    expect($job->submissionData)->toBe($data)->and($this->form->submissions()->count())->toBe(1);
});

it('refuses different content under an already committed operation key', function () {
    $job = new StoreFormSubmissionJob($this->form, [$this->field => 'first']);
    $payload = serialize($job);
    $job->handle();
    $changed = unserialize($payload);
    $changed->submissionData[$this->field] = 'different';
    expect(fn () => $changed->handle())->toThrow(DomainException::class);
    expect($this->form->submissions()->count())->toBe(1)
        ->and($this->form->submissions()->first()->data[$this->field])->toBe('first');
});

it('does not merge distinct legitimate submissions with equal content', function () {
    (new StoreFormSubmissionJob($this->form, [$this->field => 'same']))->handle();
    (new StoreFormSubmissionJob($this->form, [$this->field => 'same']))->handle();
    expect($this->form->submissions()->count())->toBe(2);
});

it('derives a stable operation from a legacy queue payload UUID', function () {
    $old = new StoreFormSubmissionJob($this->form, [$this->field => 'legacy']);
    $old->operationKey = null; // SerializesModels omits this default: field absent in old payload.
    $payload = serialize($old);
    $uuid = (string) Str::uuid();
    foreach ([1, 2] as $attempt) {
        $transport = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
        $transport->shouldReceive('uuid')->andReturn($uuid);
        $job = unserialize($payload);
        $job->setJob($transport);
        $job->handle();
    }
    expect($this->form->submissions()->count())->toBe(1)
        ->and(SubmissionOperation::first()->operation_key)->toBe('queue:'.$uuid);
});

it('quarantines an unidentified legacy payload before any side effect', function () {
    $old = new StoreFormSubmissionJob($this->form, [$this->field => 'legacy']);
    $old->operationKey = null;
    expect(fn () => unserialize(serialize($old))->handle())->toThrow(RuntimeException::class);
    expect($this->form->submissions()->count())->toBe(0)->and(SubmissionOperation::count())->toBe(0);
});

it('preserves partial completion and distinct admin edits without replay overwrite', function () {
    $partial = new StoreFormSubmissionJob($this->form, [$this->field => 'draft', 'is_partial' => true]);
    $oldPayload = serialize($partial);
    $partial->handle();
    expect(SubmissionOperation::first()->outbox_state)->toBe('none');
    $complete = new StoreFormSubmissionJob($this->form, [
        $this->field => 'complete', 'submission_id' => $partial->getSubmissionId(),
    ]);
    $complete->handle();
    unserialize($oldPayload)->handle();
    $admin = new StoreFormSubmissionJob($this->form, [
        $this->field => 'admin edit', 'submission_id' => $partial->getSubmissionId(),
    ]);
    $admin->allowCompletedUpdate = true;
    $admin->handle();
    expect($this->form->submissions()->count())->toBe(1)
        ->and($this->form->submissions()->first()->status)->toBe(FormSubmission::STATUS_COMPLETED)
        ->and($this->form->submissions()->first()->data[$this->field])->toBe('admin edit');
    Event::assertDispatchedTimes(FormSubmitted::class, 2);
});

it('recovers a committed row and pending outbox after failure before dispatch', function () {
    $service = Mockery::mock(SubmissionOperationService::class)->makePartial();
    $service->shouldReceive('dispatchOutbox')->once()->andThrow(new RuntimeException('injected loss before ACK'));
    app()->instance(SubmissionOperationService::class, $service);
    $payload = serialize(new StoreFormSubmissionJob($this->form, [$this->field => 'committed']));
    expect(fn () => unserialize($payload)->handle())->toThrow(RuntimeException::class);
    expect($this->form->submissions()->count())->toBe(1)
        ->and(SubmissionOperation::first()->outbox_state)->toBe('pending');
    app()->instance(SubmissionOperationService::class, new SubmissionOperationService());
    unserialize($payload)->handle();
    expect($this->form->submissions()->count())->toBe(1)
        ->and(SubmissionOperation::first()->outbox_state)->toBe('dispatched');
    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('retains source upload and stable signature paths across a SQL rollback', function () {
    $signature = '22222222-2222-4222-8222-222222222222';
    $file = '33333333-3333-4333-8333-333333333333';
    $this->form->update(['properties' => [
        ['id' => $signature, 'name' => 'Signature', 'type' => 'signature'],
        ['id' => $file, 'name' => 'File', 'type' => 'files'],
    ]]);
    $uuid = (string) Str::uuid();
    Storage::put('tmp/'.$uuid, 'fixture bytes');
    $payload = serialize(new StoreFormSubmissionJob($this->form, [
        $signature => 'data:image/png;base64,'.base64_encode('fixture signature'),
        $file => ['fixture_'.$uuid.'.txt'],
    ]));
    $this->realEvents->listen(FormSubmissionSaved::class, fn () => throw new RuntimeException('injected before commit'));
    expect(fn () => unserialize($payload)->handle())->toThrow(RuntimeException::class);
    expect(SubmissionOperation::count())->toBe(0)->and($this->form->submissions()->count())->toBe(0);
    Storage::assertExists('tmp/'.$uuid);
    $this->realEvents->forget(FormSubmissionSaved::class);
    unserialize($payload)->handle();
    expect($this->form->submissions()->count())->toBe(1)
        ->and(count(Storage::files('forms/'.$this->form->id.'/submissions')))->toBe(2);
    Storage::assertMissing('tmp/'.$uuid);
});

it('keeps a failed event dispatch ambiguous and never blindly dispatches again', function () {
    Event::swap($this->realEvents);
    $count = 0;
    Event::listen(FormSubmitted::class, function () use (&$count) {
        $count++;
        throw new RuntimeException('unknown dispatch outcome');
    });
    $payload = serialize(new StoreFormSubmissionJob($this->form, [$this->field => 'fixture']));
    unserialize($payload)->handle();
    unserialize($payload)->handle();
    $this->artisan('forms:dispatch-submission-outbox')->assertSuccessful();
    expect($count)->toBe(1)->and(SubmissionOperation::first()->outbox_state)->toBe('ambiguous');
});

it('invokes an integration once and quarantines an ambiguous effect outcome', function () {
    (new StoreFormSubmissionJob($this->form, [$this->field => 'fixture']))->handle();
    $operation = SubmissionOperation::first();
    $integration = FormIntegration::create([
        'form_id' => $this->form->id, 'integration_id' => 'webhook', 'status' => 'active', 'data' => [],
    ]);
    $service = app(SubmissionOperationService::class);
    $calls = 0;
    $invoke = function () use (&$calls) {
        $calls++;
        throw new RuntimeException('unknown remote ACK');
    };
    expect($service->invokeEffect($operation->id, $integration->id, $invoke))->toBeTrue();
    expect($service->invokeEffect($operation->id, $integration->id, $invoke))->toBeFalse();
    expect($calls)->toBe(1)->and(DB::table('submission_effects')->first()->state)->toBe('ambiguous');
});

it('labels returned handlers as invoked without claiming external delivery', function () {
    (new StoreFormSubmissionJob($this->form, [$this->field => 'fixture']))->handle();
    $operation = SubmissionOperation::first();
    $integration = FormIntegration::create([
        'form_id' => $this->form->id, 'integration_id' => 'webhook', 'status' => 'active', 'data' => [],
    ]);
    $service = app(SubmissionOperationService::class);
    $calls = 0;
    $invoke = function () use (&$calls) {
        $calls++;
    };
    $service->invokeEffect($operation->id, $integration->id, $invoke);
    $service->invokeEffect($operation->id, $integration->id, $invoke);
    expect($calls)->toBe(1)->and(DB::table('submission_effects')->first()->state)->toBe('invoked');
});

it('ignores a late partial before creating signature files', function () {
    $first = new StoreFormSubmissionJob($this->form, [$this->field => 'complete']);
    $first->handle();
    $signature = '22222222-2222-4222-8222-222222222222';
    $this->form->update(['properties' => [
        ['id' => $this->field, 'name' => 'Text', 'type' => 'text'],
        ['id' => $signature, 'name' => 'Signature', 'type' => 'signature'],
    ]]);
    (new StoreFormSubmissionJob($this->form, [
        'submission_id' => $first->getSubmissionId(), 'is_partial' => true,
        $signature => 'data:image/png;base64,'.base64_encode('ignored'),
    ]))->handle();
    expect(Storage::allFiles())->toBe([])
        ->and($this->form->submissions()->first()->data[$this->field])->toBe('complete');
});

it('refuses an integration from another form before invoking it', function () {
    (new StoreFormSubmissionJob($this->form, [$this->field => 'fixture']))->handle();
    $other = $this->form->replicate();
    $other->slug = 'other-'.Str::uuid();
    $other->save();
    $integration = FormIntegration::create([
        'form_id' => $other->id, 'integration_id' => 'webhook', 'status' => 'active', 'data' => [],
    ]);
    $called = false;
    $result = app(SubmissionOperationService::class)->invokeEffect(
        SubmissionOperation::first()->id,
        $integration->id,
        function () use (&$called) {
            $called = true;
        }
    );
    expect($result)->toBeFalse()->and($called)->toBeFalse()->and(DB::table('submission_effects')->count())->toBe(0);
});


it('fingerprints incoming uploaded bytes independently of temporary paths', function () {
    $service = app(SubmissionOperationService::class);
    $first = Illuminate\Http\UploadedFile::fake()->createWithContent('fixture.txt', 'same bytes');
    $retry = Illuminate\Http\UploadedFile::fake()->createWithContent('fixture.txt', 'same bytes');
    $changed = Illuminate\Http\UploadedFile::fake()->createWithContent('fixture.txt', 'changed bytes');
    expect($service->fingerprint(['file' => $first]))->toBe($service->fingerprint(['file' => $retry]))
        ->not->toBe($service->fingerprint(['file' => $changed]));
});


it('rolls back the operation when upload copying returns false', function () {
    $file = '33333333-3333-4333-8333-333333333333';
    $uuid = (string) Str::uuid();
    $this->form->update(['properties' => [['id' => $file, 'name' => 'File', 'type' => 'files']]]);
    Storage::shouldReceive('exists')->andReturnUsing(fn ($path) => $path === 'tmp/'.$uuid);
    Storage::shouldReceive('copy')->once()->andReturn(false);
    Storage::shouldNotReceive('delete');
    $job = new StoreFormSubmissionJob($this->form, [$file => ['fixture_'.$uuid.'.txt']]);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    expect(SubmissionOperation::count())->toBe(0)->and($this->form->submissions()->count())->toBe(0);
});


it('uses the body UUID across legacy SQS receipt changes without SDK requests', function () {
    $client = new Aws\Sqs\SqsClient([
        'version' => '2012-11-05', 'region' => 'eu-west-3', 'credentials' => false,
        'handler' => fn () => throw new LogicException('No AWS request is allowed in this fixture'),
    ]);
    $uuid = (string) Str::uuid();
    $old = new StoreFormSubmissionJob($this->form, [$this->field => 'legacy SQS fixture']);
    $old->operationKey = null;
    $serialized = serialize($old);
    foreach ([1, 2] as $attempt) {
        $transport = new Illuminate\Queue\Jobs\SqsJob(app(), $client, [
            'Body' => json_encode(['uuid' => $uuid]), 'MessageId' => 'fixture-message',
            'ReceiptHandle' => 'different-receipt-'.$attempt,
            'Attributes' => ['ApproximateReceiveCount' => (string) $attempt],
        ], 'sqs', 'https://sqs.eu-west-3.amazonaws.com/000000000000/fictional-only');
        $job = unserialize($serialized);
        $job->setJob($transport);
        $job->handle();
        expect($transport->uuid())->toBe($uuid)->and($transport->attempts())->toBe($attempt);
    }
    expect($this->form->submissions()->count())->toBe(1)
        ->and(SubmissionOperation::first()->operation_key)->toBe('queue:'.$uuid);
    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('does not substitute SQS MessageId for a missing Laravel body UUID', function () {
    $client = new Aws\Sqs\SqsClient([
        'version' => '2012-11-05', 'region' => 'eu-west-3', 'credentials' => false,
        'handler' => fn () => throw new LogicException('No AWS request is allowed in this fixture'),
    ]);
    $job = new StoreFormSubmissionJob($this->form, [$this->field => 'unidentified SQS fixture']);
    $job->operationKey = null;
    $job->setJob(new Illuminate\Queue\Jobs\SqsJob(app(), $client, [
        'Body' => '{}', 'MessageId' => (string) Str::uuid(), 'ReceiptHandle' => 'fictional-only',
        'Attributes' => ['ApproximateReceiveCount' => '1'],
    ], 'sqs', 'https://sqs.eu-west-3.amazonaws.com/000000000000/fictional-only'));
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    expect(SubmissionOperation::count())->toBe(0)->and($this->form->submissions()->count())->toBe(0);
});
