<?php
// One scheduler per database, including during overlapping releases.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
if (! $pdo->query('SELECT pg_try_advisory_lock(1869639270, 1)')->fetchColumn()) {
    fwrite(STDERR, "Another OpnForm scheduler owns the database lock\n");
    exit(75);
}
$stopping = false;
pcntl_async_signals(true);
pcntl_signal(SIGTERM, static function () use (&$stopping) { $stopping = true; });
pcntl_signal(SIGINT, static function () use (&$stopping) { $stopping = true; });
$lastMinute = null;
try {
    while (! $stopping) {
        // Fail closed if the connection holding the advisory lock is lost.
        $pdo->query('SELECT 1');
        touch('/tmp/opnform-scheduler-heartbeat');
        $minute = intdiv(time(), 60);
        if ($minute !== $lastMinute) {
            $lastMinute = $minute;
            $child = proc_open(['php', '/app/artisan', 'schedule:run', '--no-interaction'], [STDIN, STDOUT, STDERR], $pipes);
            if (! is_resource($child)) {
                throw new RuntimeException('Unable to start schedule tick');
            }
            // Drain the current tick on SIGTERM before releasing singleton lock.
            while (($status = proc_get_status($child))['running']) {
                touch('/tmp/opnform-scheduler-heartbeat');
                usleep(250000);
            }
            $exitCode = $status['exitcode'];
            proc_close($child);
            if ($exitCode !== 0) {
                throw new RuntimeException('Schedule tick failed');
            }
        }
        usleep(250000);
    }
} finally {
    @unlink('/tmp/opnform-scheduler-heartbeat');
    $pdo->query('SELECT pg_advisory_unlock(1869639270, 1)');
}
