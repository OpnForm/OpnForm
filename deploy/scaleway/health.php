<?php
try {
    require '/app/vendor/autoload.php';
    $app = require '/app/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    Illuminate\Support\Facades\DB::select('SELECT 1');
    Illuminate\Support\Facades\Redis::connection(config('queue.connections.redis.connection'))->ping();
    Illuminate\Support\Facades\Redis::connection('cache')->ping();
    if (config('hosted-state.mode') === 'redis') {
        app(App\Service\Deployment\RedisStateTopology::class)->assertReady();
    }
    header('Cache-Control: no-store');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'revision' => trim(file_get_contents('/app-revision'))]);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"status":"error"}';
    exit(1);
}
