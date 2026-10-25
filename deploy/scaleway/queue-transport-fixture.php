<?php

// No application submission/integration job is dispatched by this fixture.
require '/app/vendor/autoload.php';

class OpnformSyntheticQueueProbe implements Illuminate\Contracts\Queue\ShouldQueue
{
    use Illuminate\Bus\Queueable;
    use Illuminate\Queue\InteractsWithQueue;

    public function __construct(public bool $crashOnce) {}

    public function handle(): void
    {
        Illuminate\Support\Facades\Redis::rpush('fixture-events', json_encode([
            'uuid' => $this->job->uuid(),
            'attempt' => $this->attempts(),
            'crash_fixture' => $this->crashOnce,
        ]));
        if ($this->crashOnce && $this->attempts() === 1) {
            // Tini is PID1; this kills the actual child worker before queue ACK.
            posix_kill(getmypid(), SIGKILL);
        }
    }
}

if (getenv('OPNFORM_QUEUE_FIXTURE') !== 'synthetic-only') {
    throw new RuntimeException('Synthetic fixture marker required');
}
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Testing/SQLite-memory required');
}
$queue = getenv('OPNFORM_FIXTURE_QUEUE');
if (! preg_match('/^opnform-fixture-[a-f0-9]{12}$/', $queue)) {
    throw new RuntimeException('Dedicated synthetic queue required');
}
$mode = getenv('OPNFORM_FIXTURE_MODE');
if ($mode === 'worker') {
    exit(Illuminate\Support\Facades\Artisan::call('queue:work', [
        'connection' => 'redis', '--queue' => $queue, '--once' => true,
        '--sleep' => 1, '--tries' => 3, '--timeout' => 10,
    ]));
}
if (! in_array($mode, ['delay', 'crash'], true)) {
    throw new RuntimeException('Invalid fixture mode');
}
$job = (new OpnformSyntheticQueueProbe($mode === 'crash'))->onConnection('redis')->onQueue($queue);
if ($mode === 'delay') {
    $job->delay(now()->addSeconds(3));
}
app(Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job);
echo json_encode(['dispatched' => true, 'mode' => $mode]), PHP_EOL;
