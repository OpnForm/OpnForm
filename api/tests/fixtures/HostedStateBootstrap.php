<?php

use Illuminate\Contracts\Console\Kernel;

function bootHostedStateFixture(bool $reset): Illuminate\Foundation\Application
{
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $app->instance('env', 'production');
    config([
        'app.env' => 'production', 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'jwt.secret' => 'fictional-state-fixture-signing-key-at-least32chars', 'jwt.algo' => 'HS256',
        'jwt.ttl' => 10, 'jwt.refresh_ttl' => 20,
        'hosted-state.mode' => 'redis', 'hosted-state.namespace' => 'fixture-opnform',
        'hosted-state.scheduler_store' => 'redis',
        'hosted-state.public_key_file' => '/fixture-identity/public.pem',
        'hosted-state.receipt_file' => null,
        'cache.default' => 'redis', 'cache.prefix' => 'fixture_cache:',
        'cache.stores.redis' => ['driver' => 'redis', 'connection' => 'state', 'prefix' => 'fixture_state:'],
        'cache.stores.auth' => ['driver' => 'redis', 'connection' => 'auth', 'prefix' => 'fixture_auth:'],
        'cache.stores.volatile' => ['driver' => 'redis', 'connection' => 'cache', 'prefix' => 'fixture_cache:'],
        'session.driver' => 'redis', 'session.store' => 'auth', 'session.connection' => 'auth',
        'session.lifetime' => 2, 'jwt.blacklist_enabled' => true,
        'jwt.providers.storage' => App\Service\Deployment\UnifiedRedisJwtStorage::class,
        'app.front_url' => 'https://front.fixture.test',
        'database.redis.options.prefix' => 'fixture_database:',
        'database.redis.default' => ['host' => '/fixture-state/state.sock', 'port' => 0, 'database' => 0],
        'database.redis.horizon' => ['host' => '/fixture-state/state.sock', 'port' => 0, 'database' => 0],
        'database.redis.cache' => ['host' => '/fixture-state/state.sock', 'port' => 0, 'database' => 1],
        'database.redis.state' => ['host' => '/fixture-state/state.sock', 'port' => 0, 'database' => 3],
        'database.redis.auth' => ['host' => '/fixture-state/state.sock', 'port' => 0, 'database' => 2],
    ]);
    if ($reset) {
        $state = app('redis')->connection('state');
        $state->command('config', ['SET', 'appendonly', 'yes']);
        $state->command('config', ['SET', 'appendfsync', 'always']);
        $state->command('config', ['SET', 'maxmemory-policy', 'noeviction']);
        $state->command('config', ['SET', 'maxmemory', '32mb']);
        $queue = app('redis')->connection('default');
        $queue->command('config', ['SET', 'maxmemory-policy', 'noeviction']);
        foreach (['state', 'auth', 'cache', 'default'] as $connection) {
            app('redis')->connection($connection)->flushdb();
        }
    }

    return $app;
}
