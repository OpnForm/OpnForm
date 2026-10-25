<?php

// Diagnostic only: bootstrap in a disposable network-none test image, SQLite memory.
use App\Events\Forms\FormSubmitted;
use App\Jobs\Form\StoreFormSubmissionJob;
use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Only SQLite in-memory testing is allowed');
}

Event::fake([FormSubmitted::class]);
Queue::fake();
Mail::fake();
Notification::fake();
Http::preventStrayRequests();
Storage::fake();
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'job-retry@example.test']);
$workspace = Workspace::factory()->create();
$field = '11111111-1111-4111-8111-111111111111';
$form = Form::factory()->forWorkspace($workspace)->createdBy($user)->withProperties([
    ['id' => $field, 'name' => 'Fictif', 'type' => 'text'],
])->create();

// Replay the ORIGINAL serialized payload, as a queue would after an unknown ACK.
$payload = serialize(new StoreFormSubmissionJob($form, [$field => 'FICTIF UNIQUEMENT']));
unserialize($payload)->handle();
$afterFirst = $form->submissions()->count();
unserialize($payload)->handle();
$afterReplay = $form->submissions()->count();
$events = Event::dispatched(FormSubmitted::class)->count();
$safe = $afterFirst === 1 && $afterReplay === 1 && $events === 1;
echo json_encode([
    'dataset' => 'synthetic-only',
    'status' => $safe ? 'replay-safe-observed' : 'BLOCKER-REDELIVERY-NOT-IDEMPOTENT',
    'after_first_delivery_rows' => $afterFirst,
    'after_original_payload_replay_rows' => $afterReplay,
    'form_submitted_events_faked' => $events,
    'external_integrations_executed' => false,
    'real_queue_transport_exercised' => false,
    'client_data' => false,
    'limit' => 'Serialized job replay with real application/SQLite; does not execute SQS/Redis or integrations',
], JSON_PRETTY_PRINT), PHP_EOL;
exit($safe ? 0 : 2);
