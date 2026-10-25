<?php

// Synthetic-only real application fixture. No queue, HTTP or mail transport is used.
use App\Events\Forms\FormSubmitted;
use App\Events\Models\FormSubmissionSaved;
use App\Jobs\Form\StoreFormSubmissionJob;
use App\Models\Forms\Form;
use App\Models\Forms\SubmissionOperation;
use App\Service\Forms\SubmissionOperationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, get_class($e).': '.$e->getMessage().'\n'.$e->getTraceAsString().'\n'); exit(1); });
if (getenv('OPNFORM_SYNTHETIC_OPERATION_FIXTURE') !== 'network-none-local-only'
    || ! app()->environment('local') || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.host') !== '/fixture-pg'
    || config('database.connections.pgsql.database') !== 'operation_fixture') {
    throw new RuntimeException('Refusing anything except disposable Unix-socket fixture database');
}
$mode = $argv[1] ?? '';
if (! in_array($mode, ['dispatch', 'worker', 'queue-state'], true)) { Queue::fake(); }
config()->set('queue.connections.redis.retry_after', 4);
Mail::fake();
Notification::fake();
Http::preventStrayRequests();
Event::forget(FormSubmitted::class);
Event::listen(FormSubmitted::class, function ($event) {
    DB::table('fixture_events')->insert(['operation_id' => $event->operationId]);
    if (getenv('FIXTURE_CRASH') === 'event') { posix_kill(getmypid(), SIGKILL); }
    if (getenv('FIXTURE_EVENT_FAILURE') === '1') {
        throw new RuntimeException('Fictitious unknown event dispatch outcome');
    }
});
$field = '11111111-1111-4111-8111-111111111111';
$mode = $argv[1] ?? '';
$key = $argv[2] ?? '11111111-2222-4333-8444-555555555555';
$assert = function (bool $ok, string $name): void {
    if (! $ok) {
        throw new RuntimeException('Fixture assertion failed: '.$name);
    }
    echo json_encode(['check' => $name, 'passed' => true]), PHP_EOL;
};
if ($mode === 'seed') {
    Artisan::call('migrate', ['--force' => true]);
    DB::statement('CREATE TABLE fixture_attempts (id bigserial PRIMARY KEY, uuid varchar(80), attempt integer NOT NULL)');
    DB::statement('CREATE TABLE fixture_events (id bigserial PRIMARY KEY, operation_id bigint NOT NULL)');
    DB::statement('CREATE TABLE fixture_effect_calls (id bigserial PRIMARY KEY, operation_id bigint NOT NULL)');
    $user = DB::table('users')->insertGetId(['name' => 'SYNTHETIC ONLY', 'email' => 'operation@example.test', 'created_at' => now(), 'updated_at' => now()]);
    $workspace = DB::table('workspaces')->insertGetId(['name' => 'SYNTHETIC ONLY', 'created_at' => now(), 'updated_at' => now()]);
    $id = DB::table('forms')->insertGetId(['workspace_id' => $workspace, 'creator_id' => $user,
        'title' => 'SYNTHETIC ONLY', 'slug' => 'operation-fixture', 'properties' => json_encode([
            ['id' => $field, 'name' => 'Fixture', 'type' => 'text'],
        ]), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('form_integrations')->insert(['form_id' => $id, 'integration_id' => 'webhook', 'status' => 'active', 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    $assert(true, 'real PostgreSQL migrations and synthetic seed');
} elseif ($mode === 'dispatch') {
    $form = Form::where('slug', 'operation-fixture')->firstOrFail();
    $job = new StoreFormSubmissionJob($form, [$field => 'SYNTHETIC QUEUED']);
    $job->operationKey = getenv('FIXTURE_LEGACY') === '1' ? null : $key;
    Queue::connection('redis')->push($job, '', 'operation-fixture');
} elseif ($mode === 'worker') {
    Event::listen(Illuminate\Queue\Events\JobProcessing::class, function ($event) {
        DB::table('fixture_attempts')->insert(['uuid' => $event->job->uuid(), 'attempt' => $event->job->attempts()]);
    });
    if (getenv('FIXTURE_CRASH') === 'queue-after-outbox') {
        app()->instance(SubmissionOperationService::class, new class extends SubmissionOperationService {
            public function dispatchOutbox(int $id): bool {
                $result = parent::dispatchOutbox($id);
                posix_kill(getmypid(), SIGKILL);
                return $result;
            }
        });
    }
    exit(Artisan::call('queue:work', ['connection' => 'redis', '--queue' => 'operation-fixture',
        '--once' => true, '--sleep' => 1, '--tries' => 3, '--timeout' => 10]));
} elseif ($mode === 'queue-state') {
    $attempts = DB::table('fixture_attempts')->orderBy('id')->get(['uuid', 'attempt'])->toArray();
    echo json_encode(['attempts' => $attempts,
        'reserved' => Illuminate\Support\Facades\Redis::zcard('queues:operation-fixture:reserved'),
        'ready' => Illuminate\Support\Facades\Redis::llen('queues:operation-fixture')]), PHP_EOL;
} elseif ($mode === 'job') {
    $form = Form::where('slug', 'operation-fixture')->firstOrFail();
    $data = [$field => getenv('FIXTURE_VALUE') ?: 'SYNTHETIC '.$key];
    if (getenv('FIXTURE_LEGACY') === '1') {
        $data = [$field => 'SYNTHETIC LEGACY'];
    }
    if (getenv('FIXTURE_SUBMISSION_ID')) {
        $data['submission_id'] = (int) getenv('FIXTURE_SUBMISSION_ID');
    }
    if (getenv('FIXTURE_PARTIAL') === '1') {
        $data['is_partial'] = true;
    }
    $job = new StoreFormSubmissionJob($form, $data);
    $job->operationKey = $key;
    if (getenv('FIXTURE_ADMIN') === '1') {
        $job->allowCompletedUpdate = true;
    }
    if (getenv('FIXTURE_LEGACY') === '1') {
        $job->operationKey = null;
    }
    $job = unserialize(serialize($job)); // Real SerializesModels path, original queue payload shape.
    if (getenv('FIXTURE_LEGACY') === '1') {
        $transport = new Illuminate\Queue\Jobs\SyncJob(app(), json_encode(['uuid' => $key]), 'sync', 'fixture');
        $job->setJob($transport);
    }
    if (getenv('FIXTURE_CRASH') === 'before-commit') {
        Event::listen(FormSubmissionSaved::class, function () { posix_kill(getmypid(), SIGKILL); });
    } elseif (getenv('FIXTURE_CRASH') === 'after-commit') {
        app()->instance(SubmissionOperationService::class, new class extends SubmissionOperationService {
            public function dispatchOutbox(int $id): bool { posix_kill(getmypid(), SIGKILL); return false; }
        });
    }
    $job->handle();
    echo json_encode(['submission_id' => $job->getSubmissionId()]), PHP_EOL;
} elseif ($mode === 'effect') {
    $op = SubmissionOperation::where('operation_key', $key)->firstOrFail();
    $integration = DB::table('form_integrations')->where('form_id', $op->form_id)->first();
    app(SubmissionOperationService::class)->invokeEffect($op->id, $integration->id, function () use ($op) {
        DB::table('fixture_effect_calls')->insert(['operation_id' => $op->id]);
        if (getenv('FIXTURE_EFFECT_FAILURE') === '1') { throw new RuntimeException('Synthetic remote ACK unknown'); }
        if (getenv('FIXTURE_CRASH') === 'effect') { posix_kill(getmypid(), SIGKILL); }
        usleep(100000);
    });
} elseif ($mode === 'sweep') {
    if (getenv('FIXTURE_AGE_CLAIMS') === '1') {
        DB::table('submission_operations')->where('outbox_state', 'dispatching')->update(['updated_at' => now()->subHours(2)]);
        DB::table('submission_effects')->where('state', 'invoking')->update(['updated_at' => now()->subHours(2)]);
    }
    Artisan::call('forms:dispatch-submission-outbox');
    echo Artisan::output();
} elseif ($mode === 'assert') {
    $op = SubmissionOperation::where('operation_key', $key)->first();
    $expected = json_decode(getenv('FIXTURE_EXPECT'), true, flags: JSON_THROW_ON_ERROR);
    $actual = [
        'operations' => $op ? 1 : 0,
        'state' => $op?->outbox_state,
        'value' => $op ? (json_decode(DB::table('form_submissions')->where('id', $op->submission_id)->value('data'), true)[$field] ?? null) : null,
        'rows' => $op ? DB::table('form_submissions')->where('id', $op->submission_id)->count() : 0,
        'total_rows' => DB::table('form_submissions')->count(),
        'events' => $op ? DB::table('fixture_events')->where('operation_id', $op->id)->count() : 0,
        'effects' => $op ? DB::table('fixture_effect_calls')->where('operation_id', $op->id)->count() : 0,
        'effect_state' => $op ? DB::table('submission_effects')->where('operation_id', $op->id)->value('state') : null,
    ];
    foreach ($expected as $name => $value) { $assert($actual[$name] === $value, $key.' '.$name.'='.json_encode($value)); }
} else {
    throw new RuntimeException('Unknown fixture mode');
}
