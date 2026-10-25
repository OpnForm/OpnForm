<?php
// Executed only inside the production image with disposable CI dependencies.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;

if (! str_starts_with(config('database.redis.default.host'), 'opnform-fixture-')) {
    throw new RuntimeException('This test requires disposable runtime dependencies');
}
$check = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Laravel Redis runtime contract failed');
    }
};
$check(Cache::getStore() instanceof Illuminate\Cache\RedisStore);
Cache::put('runtime-counter', 1, 120);
$check(Cache::increment('runtime-counter') === 2);
$lock = Cache::lock('runtime-lock', 10);
$check($lock->get());
$check(! Cache::lock('runtime-lock', 10)->get());
$lock->release();

$session = (new Illuminate\Session\SessionManager(app()))->driver();
$session->setId(str_repeat('a', 40));
$session->start();
$session->put('runtime-user', 42);
$session->save();
$reopened = (new Illuminate\Session\SessionManager(app()))->driver();
$reopened->setId($session->getId());
$reopened->start();
$check($reopened->get('runtime-user') === 42);
$store = Cache::store('auth')->getStore();
$expiry = app('redis')->connection('auth')->pttl($store->getPrefix().$session->getId());
$check($expiry > 0 && $expiry <= config('session.lifetime') * 60000);

$state = app('redis')->connection('state');
$queue = app('redis')->connection('default');
$pending = $queue->llen('queues:default');
$check($pending > 0); // The real HTTP submission is waiting for its worker.
$memory = $state->info('memory');
$state->command('config', ['SET', 'maxmemory', (string) ($memory['used_memory'] + 100000)]);
try {
    $oom = false;
    try {
        Cache::store('volatile')->put('oversized-runtime-test', str_repeat('x', 3000000), 120);
    } catch (RedisException $exception) {
        $oom = str_contains($exception->getMessage(), 'OOM');
    }
    $check($oom);
    $check(Cache::get('runtime-counter') === 2);
    $check(Cache::store('auth')->get($session->getId()) !== null);
    $check($queue->llen('queues:default') === $pending);
    $check($state->info('stats')['evicted_keys'] === 0);
} finally {
    $state->command('config', ['SET', 'maxmemory', '32mb']);
}
echo "Laravel Redis runtime contracts passed\n";
