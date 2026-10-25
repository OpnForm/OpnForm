<?php

use App\Models\User;
use App\Service\Deployment\HostedStateGuard;
use App\Service\OAuth\McpOAuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

require_once __DIR__.'/../../fixtures/HostedStateBootstrap.php';

beforeEach(function () {
    if (getenv('OPNFORM_HOSTED_STATE_FIXTURES') !== 'true') {
        $this->markTestSkipped('Covered serially by the isolated Scaleway Redis fixture job');
    }
    $this->application = bootHostedStateFixture(true);
    config(['hosted-state.fixture_receipts' => []]);
});

afterEach(function () {
    if (! isset($this->application)) {
        return;
    }
    foreach (config('hosted-state.fixture_receipts', []) as $file) {
        chmod($file, 0600);
        unlink($file);
    }
    $this->application->flush();
    \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState($this);
});

function withStateConfigEnvironment(array $values, callable $callback): mixed
{
    $saved = [];
    foreach ($values as $key => $value) {
        $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($value === null ? $key : $key.'='.$value);
        if ($value !== null) {
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
    try {
        return $callback();
    } finally {
        foreach ($saved as $key => [$env, $server, $process]) {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($env !== null) {
                $_ENV[$key] = $env;
            }
            if ($server !== null) {
                $_SERVER[$key] = $server;
            }
            putenv($process === false ? $key : $key.'='.$process);
        }
    }
}

function recoveryReceipt(array $changes = [], bool $data = true, bool $marker = true): array
{
    $expires = time() + 120;
    $session = serialize(['fixture_user' => 42]);
    $revocation = ['valid_until' => $expires];
    if ($data) {
        Cache::store('auth')->put('fixture-session', $session, 120);
        app('tymon.jwt.provider.storage')->add('fixture-revoked-jti', $revocation, 2);
    }
    $receipt = array_replace_recursive([
        'schema' => 'opnform-state-recovery/v1', 'phase' => 'initial-recovery',
        'redis_run_id' => app('redis')->connection('state')->info('server')['run_id'],
        'source' => 'dynamodb', 'cutover_approved' => true, 'migration_id' => 'fictional-migration',
        'source_fenced_at' => time() - 1, 'verified_at' => time(), 'valid_until' => time() + 60,
        'revision' => trim(file_get_contents('/app-revision')),
        'source_manifest_sha256' => str_repeat('a', 64),
        'configuration_sha256' => app(HostedStateGuard::class)->configurationFingerprint(),
        'source_unexpired_count' => 2, 'imported_count' => 2, 'unknown_count' => 0,
        'evidence' => array_fill_keys(['identity', 'source_fence', 'sessions', 'ttl', 'revocations', 'atomicity', 'durability', 'rollback'], str_repeat('b', 64)),
        'probes' => [
            ['kind' => 'session', 'key' => 'fixture-session', 'value_sha256' => hash('sha256', serialize($session)), 'expires_at' => $expires],
            ['kind' => 'jwt-revocation', 'key' => 'fixture-revoked-jti', 'value_sha256' => hash('sha256', serialize($revocation)), 'expires_at' => $expires],
        ],
    ], $changes);
    $payload = json_encode($receipt, JSON_THROW_ON_ERROR);
    openssl_sign($payload, $signature, file_get_contents('/fixture-identity/private.pem'), OPENSSL_ALGO_SHA256);
    $file = tempnam(sys_get_temp_dir(), 'opnform-state-receipt-');
    file_put_contents($file, json_encode(['payload' => base64_encode($payload), 'signature' => base64_encode($signature)]));
    chmod($file, 0444);
    config(['hosted-state.fixture_receipts' => [...config('hosted-state.fixture_receipts', []), $file]]);
    config(['hosted-state.receipt_file' => $file]);
    if ($marker) {
        [$epochKey] = app(\App\Service\Deployment\DynamoStateImporter::class)->keys();
        app('redis')->connection('state')->hset(
            $epochKey,
            'manifest',
            $receipt['source_manifest_sha256'],
            'phase',
            'admitted',
            'dirty',
            '0',
            'accepted_receipt',
            hash('sha256', $payload)
        );
        Cache::forever('opnform:state-recovery:fictional-migration', [
            'payload_sha256' => hash('sha256', $payload), 'imported_count' => $receipt['imported_count'],
        ]);
    }

    return $receipt;
}

it('keeps historical config defaults when Redis mode is not selected', function () {
    withStateConfigEnvironment(array_fill_keys(['OPNFORM_STATE_MODE', 'SESSION_DRIVER', 'SESSION_STORE', 'SESSION_CONNECTION', 'CACHE_DRIVER'], null), function () {
        $session = require config_path('session.php');
        $cache = require config_path('cache.php');
        expect($session['driver'])->toBe('file')->and($session['store'])->toBeNull()
            ->and($session['connection'])->toBeNull()->and($cache['default'])->toBe('file');
    });
});

it('selects durable state Redis for actual Laravel sessions without falling through to the queue', function () {
    withStateConfigEnvironment(['OPNFORM_STATE_MODE' => 'redis', 'SESSION_DRIVER' => null, 'SESSION_STORE' => null, 'SESSION_CONNECTION' => null, 'CACHE_DRIVER' => null], function () {
        config(['session' => require config_path('session.php'), 'cache' => require config_path('cache.php')]);
        $session = (new SessionManager(app()))->driver();
        $session->setId(str_repeat('a', 40));
        $session->start();
        $session->put('fictional-user', 42);
        $session->save();
        $reopened = (new SessionManager(app()))->driver();
        $reopened->setId($session->getId());
        $reopened->start();
        expect($reopened->get('fictional-user'))->toBe(42);
        $store = $session->getHandler()->getCache()->getStore();
        $key = $store->getPrefix().$session->getId();
        expect(config('session.connection'))->toBe('auth')->and(config('session.store'))->toBe('auth')
            ->and($store->connection())->toBe(app('redis')->connection('auth'))
            ->and(app('redis')->connection('auth')->exists($key))->toBe(1)
            ->and(app('redis')->connection('default')->exists($key))->toBe(0);
    });
});

it('preserves the historical production DynamoDB admission policy', function () {
    config(['hosted-state.mode' => 'historical-dynamodb', 'cache.default' => 'dynamodb', 'session.driver' => 'dynamodb', 'session.store' => null]);
    app(HostedStateGuard::class)->assertReady();
    config(['session.store' => 'redis']);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('rejects conflicting stores or queue connection overrides', function ($key, $value) {
    config([$key => $value]);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
})->with([
    ['session.connection', 'default'], ['session.store', 'dynamodb'],
    ['cache.stores.redis.connection', 'default'], ['hosted-state.scheduler_store', 'dynamodb'],
    ['cache.stores.redis.lock_connection', 'default'], ['jwt.blacklist_enabled', false],
    ['hosted-state.mode', 'unknown'],
]);

it('admits fresh production Redis without a DynamoDB receipt and rejects unsafe persistence', function () {
    config(['hosted-state.mode' => 'redis-fresh']);
    app(HostedStateGuard::class)->assertReady();
    app('redis')->connection('state')->command('config', ['SET', 'maxmemory-policy', 'allkeys-lru']);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('uses protected session and JWT bindings for the cold Redis strategy', function () {
    withStateConfigEnvironment(['OPNFORM_STATE_MODE' => 'redis-fresh', 'SESSION_DRIVER' => null, 'SESSION_STORE' => null, 'SESSION_CONNECTION' => null, 'CACHE_DRIVER' => null], function () {
        $session = require config_path('session.php');
        $cache = require config_path('cache.php');
        $jwt = require config_path('jwt.php');
        expect($session['driver'])->toBe('redis')->and($session['store'])->toBe('auth')
            ->and($session['connection'])->toBe('auth')->and($cache['default'])->toBe('redis')
            ->and($cache['stores']['redis']['connection'])->toBe('state')
            ->and($jwt['providers']['storage'])->toBe(\App\Service\Deployment\UnifiedRedisJwtStorage::class);
    });
});

it('requires a receipt even when Redis mode is explicitly selected', function () {
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('rejects empty Redis even with a signed acceptance receipt', function () {
    recoveryReceipt(data: false, marker: false);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('rejects a checkpoint without the retained state witnesses', function () {
    recoveryReceipt(data: false);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('accepts a signed bounded initial recovery with real retained values and native TTLs', function () {
    recoveryReceipt();
    app(HostedStateGuard::class)->assertReady();
    expect(app('tymon.jwt.provider.storage')->get('fixture-revoked-jti'))->toBeArray();
});

it('rejects lost state or extended TTL after the signed verification', function ($change) {
    recoveryReceipt();
    if (in_array($change, ['ttl', 'shortened'], true)) {
        Cache::store('auth')->put('fixture-session', Cache::store('auth')->get('fixture-session'), $change === 'ttl' ? 600 : 30);
    } else {
        Cache::store('auth')->forget('fixture-session');
    }
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
})->with(['ttl', 'shortened', 'lost']);

it('rejects stale approval, missing evidence and incomplete copies', function ($change) {
    recoveryReceipt($change);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
})->with([
    [['cutover_approved' => false]], [['source_fenced_at' => null]], [['migration_id' => []]], [['valid_until' => time() - 1]],
    [['evidence' => ['revocations' => null]]], [['imported_count' => 1]],
    [['configuration_sha256' => str_repeat('0', 64)]], [['redis_run_id' => str_repeat('0', 40)]],
    [['evidence' => ['durability' => null]]], [['revision' => str_repeat('0', 40)]],
]);

it('rejects an altered signed receipt', function () {
    recoveryReceipt();
    $path = config('hosted-state.receipt_file');
    chmod($path, 0600);
    $envelope = json_decode(file_get_contents($path), true);
    $payload = json_decode(base64_decode($envelope['payload']), true);
    $payload['cutover_approved'] = false;
    $envelope['payload'] = base64_encode(json_encode($payload));
    file_put_contents($path, json_encode($envelope));
    chmod($path, 0444);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('uses the JWT provider tagged namespace rather than admitting a raw DynamoDB jti copy', function () {
    $storage = app('tymon.jwt.provider.storage');
    Cache::put('raw-source-jti', ['valid_until' => time() + 120], 120);
    expect($storage->get('raw-source-jti'))->toBeNull();
    $storage->add('raw-source-jti', ['valid_until' => time() + 120], 2);
    expect($storage->get('raw-source-jti'))->toBeArray();
    $payload = app(\Tymon\JWTAuth\Factory::class)->sub(42)->make();
    $blacklist = app('tymon.jwt.blacklist');
    expect($blacklist->has($payload))->toBeFalse();
    $blacklist->add($payload);
    expect($blacklist->has($payload))->toBeTrue();
});

it('keeps actual MCP authorization consumption under Redis locks and rejects reuse', function () {
    $service = app(McpOAuthSessionService::class);
    $url = $service->beginAuthorization(Request::create('/oauth/authorize?client_id=fixture&response_type=code'));
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $token = $query['request'];
    $key = 'mcp:oauth:authorization-request:'.hash('sha256', $token);
    $lock = Cache::lock('mcp:oauth:consume:'.hash('sha256', $key), 5);
    expect($lock->get())->toBeTrue();
    $user = new User();
    $user->id = 42;
    expect(fn () => $service->issueLoginTicket($token, $user))->toThrow(BadRequestHttpException::class);
    $lock->release();
    $ticketUrl = $service->issueLoginTicket($token, $user);
    expect(fn () => $service->issueLoginTicket($token, $user))->toThrow(BadRequestHttpException::class);
    parse_str(parse_url($ticketUrl, PHP_URL_QUERY), $ticketQuery);
    $request = Request::create($ticketUrl);
    $request->setLaravelSession((new SessionManager(app()))->driver());
    Auth::shouldReceive('guard')->with('web')->once()->andReturn($guard = Mockery::mock());
    $guard->shouldReceive('loginUsingId')->with(42)->once()->andReturnTrue();
    $service->consumeLoginTicket($ticketQuery['mcp_login_ticket'], $request);
    expect(fn () => $service->consumeLoginTicket($ticketQuery['mcp_login_ticket'], $request))->toThrow(AccessDeniedHttpException::class);
});

it('refuses volatile Redis even with matching retained state and signed approval', function () {
    recoveryReceipt();
    app('redis')->connection('state')->command('config', ['SET', 'appendonly', 'no']);
    expect(fn () => app(HostedStateGuard::class)->assertReady())->toThrow(RuntimeException::class);
});

it('checks durable topology rather than trusting a connection label', function () {
    expect(app(\App\Service\Deployment\RedisStateTopology::class)->assertReady())->toHaveCount(4);
    app('redis')->connection('state')->command('config', ['SET', 'maxmemory-policy', 'allkeys-lru']);
    expect(fn () => app(\App\Service\Deployment\RedisStateTopology::class)->assertReady())->toThrow(RuntimeException::class);
});

function stateImportSnapshot(array $rows = []): array
{
    $expires = time() + 180;
    if ($rows === []) {
        $rows = [
            ['session', 'fixture-session', serialize(['fictional-user' => 42])],
            ['jwt-revocation', 'fixture-revoked-jti', ['valid_until' => time() - 1]],
            ['context', 'fixture-once-context', ['once' => true]],
            ['quota', 'fixture-quota', 3],
        ];
    }

    return [
        'schema' => 'opnform-dynamodb-state-snapshot/v1',
        'namespace' => config('hosted-state.namespace'), 'source_fenced_at' => time() - 2,
        'source_prefix' => 'source_opnform_cache:',
        'target_configuration_sha256' => app(HostedStateGuard::class)->configurationFingerprint(),
        'attributes' => ['key' => 'key', 'value' => 'value', 'expiration' => 'expires_at'],
        'package' => [
            'laravel' => \Composer\InstalledVersions::getVersion('laravel/framework'),
            'php_serialize' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'composer_lock_sha256' => hash_file('sha256', base_path('composer.lock')),
        ],
        'rows' => array_map(fn ($row) => ['kind' => $row[0], 'item' => [
            'key' => ['S' => 'source_opnform_cache:'.$row[1]],
            'value' => is_numeric($row[2]) ? ['N' => (string) $row[2]] : ['S' => serialize($row[2])],
            'expires_at' => ['N' => (string) $expires],
        ]], $rows),
    ];
}

function importedStateReceipt(array $snapshot, array $extra = []): array
{
    [$epochKey] = app(\App\Service\Deployment\DynamoStateImporter::class)->keys();
    $epoch = app('redis')->connection('state')->hgetall($epochKey);
    $expires = (int) $snapshot['rows'][0]['item']['expires_at']['N'];
    $probes = [
        ['kind' => 'session', 'key' => 'fixture-session', 'expires_at' => $expires,
            'value_sha256' => hash('sha256', serialize(Cache::store('auth')->get('fixture-session')))],
        ['kind' => 'jwt-revocation', 'key' => 'fixture-revoked-jti', 'expires_at' => $expires,
            'value_sha256' => hash('sha256', serialize(app('tymon.jwt.provider.storage')->get('fixture-revoked-jti')))],
    ];

    return recoveryReceipt(array_replace_recursive([
        'source_manifest_sha256' => $epoch['manifest'], 'source_fenced_at' => $snapshot['source_fenced_at'],
        'source_unexpired_count' => (int) $epoch['imported'], 'imported_count' => (int) $epoch['imported'],
        'valid_until' => min(time() + 30, $expires - 5), 'probes' => $probes,
    ], $extra), data: false, marker: false);
}

function runStateWorker(array $snapshot, bool $killAfterCommit): array
{
    $path = tempnam(sys_get_temp_dir(), 'opnform-state-snapshot-');
    $marker = $path.'-marker';
    $log = $path.'-worker.log';
    file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
    $process = proc_open([PHP_BINARY, base_path('tests/fixtures/HostedStateImportWorker.php'), $path,
        $marker, $killAfterCommit ? 'pause' : 'resume'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
    fclose($pipes[0]);
    try {
        $didKill = false;
        $end = microtime(true) + 10;
        do {
            $status = proc_get_status($process);
            if ($killAfterCommit && is_file($marker)) {
                expect(posix_kill($status['pid'], SIGKILL))->toBeTrue();
                do {
                    usleep(10000);
                    $status = proc_get_status($process);
                } while ($status['running'] && microtime(true) < $end);
                expect($status['running'])->toBeFalse()->and($status['signaled'])->toBeTrue()->and($status['termsig'])->toBe(SIGKILL);
                $didKill = true;
                break;
            }
            if (! $status['running']) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $end);
        if ($status['running']) {
            throw new RuntimeException('Owned worker did not reach bounded completion');
        }

        if ($killAfterCommit && ! $didKill) {
            throw new RuntimeException('Worker exited before acknowledged commit/SIGKILL: '.file_get_contents($log));
        }

        return ['exit' => $status['exitcode'], 'log' => file_get_contents($log), 'killed' => $didKill];
    } finally {
        $status = proc_get_status($process);
        if ($status['running']) {
            posix_kill($status['pid'], SIGKILL);
        }
        proc_close($process);
        foreach ([$path, $marker, $log] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

it('state importer transfers sessions, quota and signed JWT revocation through actual providers', function () {
    app('request')->headers->set('User-Agent', 'StateFixtureBrowser');
    $user = new User();
    $user->id = 42;
    $token = app('tymon.jwt')->fromSubject($user);
    $payload = app('tymon.jwt')->setToken($token)->getPayload();
    $sessionId = str_repeat('b', 40);
    $snapshot = stateImportSnapshot([
        ['session', $sessionId, serialize(['fictional-user' => 42])],
        ['jwt-revocation', $payload->get('jti'), ['valid_until' => time() - 1]],
        ['quota', 'fixture-quota', 3],
    ]);
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    $result = $importer->import($snapshot);
    expect((int) $result['imported'])->toBe(3);
    $session = (new SessionManager(app()))->driver();
    $session->setId($sessionId);
    $session->start();
    expect($session->get('fictional-user'))->toBe(42);
    expect(Cache::increment('fixture-quota'))->toBe(4);
    expect(fn () => app('tymon.jwt')->setToken($token)->getPayload())
        ->toThrow(\Tymon\JWTAuth\Exceptions\TokenBlacklistedException::class);
    expect(app('redis')->connection('cache')->dbsize())->toBe(0)
        ->and(app('redis')->connection('default')->dbsize())->toBe(0);
});

it('state importer skips expired rows and keeps exact absolute expiration on replay', function () {
    $snapshot = stateImportSnapshot();
    $snapshot['rows'][2]['item']['expires_at']['N'] = (string) (time() - 1);
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    $first = $importer->import($snapshot);
    $key = Cache::store('auth')->getStore()->getPrefix().'fixture-session';
    $expiration = app('redis')->connection('auth')->pexpiretime($key);
    $again = $importer->import($snapshot);
    expect($first['imported'])->toBe('3')->and($first['expired'])->toBe('1')
        ->and($again['imported'])->toBe('3')->and(Cache::get('fixture-once-context'))->toBeNull()
        ->and(app('redis')->connection('auth')->pexpiretime($key))->toBe($expiration);
});

it('state importer rejects incompatible package, prefix and opaque serialized objects before initialization', function ($change) {
    $snapshot = stateImportSnapshot();
    if ($change === 'package') {
        $snapshot['package']['laravel'] = 'unknown-version';
    } elseif ($change === 'prefix') {
        $snapshot['rows'][0]['item']['key']['S'] = 'other_product:key';
    } else {
        $snapshot['rows'][2]['item']['value'] = ['S' => serialize(new stdClass())];
    }
    expect(fn () => app(\App\Service\Deployment\DynamoStateImporter::class)->begin($snapshot))->toThrow(RuntimeException::class);
    expect(app('redis')->connection('state')->dbsize())->toBe(0);
})->with(['package', 'prefix', 'object']);

it('state importer does not initialize or overwrite an already written namespace', function () {
    Cache::put('new-target-write', 'keep', 120);
    expect(fn () => app(\App\Service\Deployment\DynamoStateImporter::class)->begin(stateImportSnapshot()))->toThrow(RuntimeException::class);
    expect(Cache::get('new-target-write'))->toBe('keep');
});

it('state importer stops on new writes and never clears a dirty fence on retry', function () {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    Cache::store('auth')->put('fixture-session', 'newer', 120);
    expect(fn () => $importer->import($snapshot))->toThrow(RuntimeException::class);
    Cache::store('auth')->forget('fixture-session');
    expect(fn () => $importer->import($snapshot))->toThrow(RuntimeException::class);
    expect(Cache::store('auth')->get('fixture-session'))->toBeNull();
});

it('state importer resumes an actual SIGKILL without resetting committed TTLs', function () {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    runStateWorker($snapshot, true);
    $key = Cache::store('auth')->getStore()->getPrefix().'fixture-session';
    $expiration = app('redis')->connection('auth')->pexpiretime($key);
    $resume = runStateWorker($snapshot, false);
    expect($resume['exit'])->toBe(0);
    expect(app('redis')->connection('auth')->pexpiretime($key))->toBe($expiration);
    expect($importer->seal($snapshot)['phase'])->toBe('sealed');
});

it('state importer never resurrects consumed or overwritten data after SIGKILL', function ($action) {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    runStateWorker($snapshot, true);
    if ($action === 'consume') {
        Cache::store('auth')->pull('fixture-session');
    } else {
        Cache::store('auth')->put('fixture-session', 'new-after-crash', 120);
    }
    expect(runStateWorker($snapshot, false)['exit'])->toBe(78);
    expect(Cache::store('auth')->get('fixture-session'))->toBe($action === 'consume' ? null : 'new-after-crash');
    expect(fn () => $importer->seal($snapshot))->toThrow(RuntimeException::class);
})->with(['consume', 'new-write']);

it('state importer refuses automatic resume after loss of the journal', function () {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    $importer->import($snapshot);
    Cache::store('auth')->pull('fixture-session');
    app('redis')->connection('state')->flushdb();
    expect(fn () => $importer->import($snapshot))->toThrow(RuntimeException::class);
    expect(Cache::store('auth')->get('fixture-session'))->toBeNull();
});

it('receipt lifecycle admits a sealed import then renews by signed CAS without replaying consumed state', function () {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    $importer->import($snapshot);
    $importer->seal($snapshot);
    importedStateReceipt($snapshot);
    $lifecycle = app(\App\Service\Deployment\HostedStateReceiptLifecycle::class);
    $lifecycle->publish();
    app(HostedStateGuard::class)->assertReady();
    $marker = Cache::get('opnform:state-recovery:fictional-migration');
    Cache::pull('fixture-once-context');
    importedStateReceipt($snapshot, ['phase' => 'recovery-renewal', 'previous_receipt_sha256' => $marker['payload_sha256']]);
    $lifecycle->publish();
    app(HostedStateGuard::class)->assertReady();
    expect(Cache::get('fixture-once-context'))->toBeNull();
    expect(fn () => $importer->import($snapshot))->toThrow(RuntimeException::class);
    expect(fn () => $lifecycle->publish())->toThrow(RuntimeException::class);
});

it('receipt lifecycle refuses publication from incomplete or dirty import', function () {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    $importer->import($snapshot);
    importedStateReceipt($snapshot);
    expect(fn () => app(\App\Service\Deployment\HostedStateReceiptLifecycle::class)->publish())->toThrow(RuntimeException::class);
});


it('state importer refuses resume when only the committed ledger is lost', function () {
    $snapshot = stateImportSnapshot();
    $importer = app(\App\Service\Deployment\DynamoStateImporter::class);
    $importer->begin($snapshot);
    expect(runStateWorker($snapshot, true)['killed'])->toBeTrue();
    [, $ledger] = $importer->keys();
    app('redis')->connection('state')->del($ledger);
    Cache::store('auth')->pull('fixture-session');
    expect(runStateWorker($snapshot, false)['exit'])->toBe(78);
    expect(Cache::store('auth')->get('fixture-session'))->toBeNull();
    expect(fn () => $importer->seal($snapshot))->toThrow(RuntimeException::class);
});

it('durable topology refuses queue eviction and logical DB collisions', function ($failure) {
    if ($failure === 'queue-eviction') {
        app('redis')->connection('default')->command('config', ['SET', 'maxmemory-policy', 'allkeys-lru']);
    } elseif ($failure === 'state-aof-disabled') {
        app('redis')->connection('state')->command('config', ['SET', 'appendonly', 'no']);
    } else {
        config(['database.redis.default' => config('database.redis.state')]);
        app('redis')->purge('default');
        app()->forgetInstance('redis');
    }
    expect(fn () => app(\App\Service\Deployment\RedisStateTopology::class)->assertReady())->toThrow(RuntimeException::class);
})->with(['queue-eviction', 'state-aof-disabled', 'shared-instance']);


it('state importer rejects invalid or overflowing absolute expiration before writes', function ($expiration) {
    $snapshot = stateImportSnapshot();
    $snapshot['rows'][0]['item']['expires_at']['N'] = $expiration;
    expect(fn () => app(\App\Service\Deployment\DynamoStateImporter::class)->begin($snapshot))->toThrow(RuntimeException::class);
    expect(app('redis')->connection('state')->dbsize())->toBe(0);
})->with(['9223372036854775807', '-1', '1e10']);
