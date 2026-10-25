<?php

namespace Tests\Unit\Deployment\UnifiedRedis;

use RuntimeException;
use RedisException;
use App\Models\User;
use App\Service\Deployment\HostedStateGuard;
use App\Service\Deployment\DynamoStateImporter;
use App\Service\Deployment\RedisStateTopology;
use App\Service\Deployment\HostedStateReceiptLifecycle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Session\SessionManager;

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
    foreach (config('hosted-state.fixture_receipts', []) as $path) {
        chmod($path, 0600);
        unlink($path);
    }
    $this->application->flush();
    \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState($this);
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

function recoveryReceipt(array $changes = [], bool $data = true, bool $marker = true): array
{
    $expires = time() + 120;
    $session = serialize(['fixture_user' => 42]);
    $revocation = ['valid_until' => $expires];
    if ($data) {
        Cache::put('fixture-session', $session, 120);
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


it('binds four real logical databases to one process with nonoverlapping prefixes', function () {
    $ids = app(RedisStateTopology::class)->assertReady();
    expect(array_unique(array_values($ids)))->toHaveCount(1);
    Cache::put('same', 'state', 120);
    Cache::store('auth')->put('same', 'auth', 120);
    Cache::store('volatile')->put('same', 'cache', 120);
    Queue::connection('redis')->push('FictionalFixtureHandler', []);
    expect(Cache::get('same'))->toBe('state')->and(Cache::store('auth')->get('same'))->toBe('auth')->and(Cache::store('volatile')->get('same'))->toBe('cache');
    expect(app('redis')->connection('default')->llen('queues:default'))->toBe(1);
    expect(app('redis')->connection('auth')->llen('queues:default'))->toBe(0);
});
it('refuses effective DB or prefix collisions', function ($kind) {
    if ($kind === 'db') {
        config(['database.redis.auth.database' => 3]);
        app('redis')->purge('auth');
        app()->forgetInstance('redis');
    } else {
        config(['cache.stores.auth.prefix' => 'fixture_state:']);
        app('cache')->forgetDriver('auth');
    }
    expect(fn () => app(RedisStateTopology::class)->assertReady())->toThrow(RuntimeException::class);
})->with(['db','prefix']);
it('imports actual sessions JWT blacklist states and quotas into their own DBs', function () {
    $user = new User();
    $user->id = 42;
    $token = app('tymon.jwt')->fromSubject($user);
    $payload = app('tymon.jwt')->setToken($token)->getPayload();
    $id = str_repeat('b', 40);
    $s = stateImportSnapshot([['session',$id,serialize(['fictional-user' => 42])],['jwt-revocation',$payload->get('jti'),['valid_until' => time() - 1]],['context','once','one-use'],['quota','quota',3]]);
    $i = app(DynamoStateImporter::class);
    $i->begin($s);
    $i->import($s);
    $session = (new SessionManager(app()))->driver();
    $session->setId($id);
    $session->start();
    expect($session->get('fictional-user'))->toBe(42);
    expect(Cache::get($id))->toBeNull()->and(Cache::store('auth')->get('once'))->toBeNull();
    expect(fn () => app('tymon.jwt')->setToken($token)->getPayload())->toThrow(\Tymon\JWTAuth\Exceptions\TokenBlacklistedException::class);
    expect(Cache::increment('quota'))->toBe(4)->and(Cache::pull('once'))->toBe('one-use')->and(Cache::pull('once'))->toBeNull();
});
it('preserves exact auth expiry and expired-state suppression across idempotent import', function () {
    $s = stateImportSnapshot();
    $s['rows'][2]['item']['expires_at']['N'] = (string)(time() - 1);
    $i = app(DynamoStateImporter::class);
    $i->begin($s);
    $first = $i->import($s);
    $a = app('redis')->connection('auth');
    $key = Cache::store('auth')->getStore()->getPrefix().'fixture-session';
    $expiry = $a->pexpiretime($key);
    $i->import($s);
    expect($first['imported'])->toBe('3')->and($first['expired'])->toBe('1')->and($a->pexpiretime($key))->toBe($expiry)->and(Cache::get('fixture-once-context'))->toBeNull();
});
it('resumes a real killed worker without restoring consumed or newer auth state', function ($action) {
    $s = stateImportSnapshot();
    $i = app(DynamoStateImporter::class);
    $i->begin($s);
    expect(runStateWorker($s, true)['killed'])->toBeTrue();
    $auth = Cache::store('auth');
    $key = $auth->getStore()->getPrefix().'fixture-session';
    $expiry = app('redis')->connection('auth')->pexpiretime($key);
    if ($action === 'consume') {
        $auth->pull('fixture-session');
    }
    if ($action === 'overwrite') {
        $auth->put('fixture-session', 'newer', 120);
    }
    if ($action === 'ledger') {
        [, $ledger] = $i->keys();
        app('redis')->connection('state')->del($ledger);
        $auth->pull('fixture-session');
    }
    $res = runStateWorker($s, false);
    if ($action === 'clean') {
        expect($res['exit'])->toBe(0)->and(app('redis')->connection('auth')->pexpiretime($key))->toBe($expiry);
        expect($i->seal($s)['phase'])->toBe('sealed');
    } else {
        expect($res['exit'])->toBe(78)->and($auth->get('fixture-session'))->toBe($action === 'overwrite' ? 'newer' : null);
        expect(fn () => $i->seal($s))->toThrow(RuntimeException::class);
    }
})->with(['clean','consume','overwrite','ledger']);
it('refuses to initialize when the auth database already contains target writes', function () {
    Cache::store('auth')->put('preserve', 'existing', 120);
    expect(fn () => app(DynamoStateImporter::class)->begin(stateImportSnapshot()))->toThrow(RuntimeException::class);
    expect(Cache::store('auth')->get('preserve'))->toBe('existing')->and(app('redis')->connection('state')->dbsize())->toBe(0);
});
it('admits and renews real signed receipts with auth witnesses and state epoch', function () {
    $s = stateImportSnapshot();
    $i = app(DynamoStateImporter::class);
    $i->begin($s);
    $i->import($s);
    $i->seal($s);
    importedStateReceipt($s);
    $l = app(HostedStateReceiptLifecycle::class);
    $l->publish();
    app(HostedStateGuard::class)->assertReady();
    $m = Cache::get('opnform:state-recovery:fictional-migration');
    Cache::pull('fixture-once-context');
    importedStateReceipt($s, ['phase' => 'recovery-renewal','previous_receipt_sha256' => $m['payload_sha256']]);
    $l->publish();
    app(HostedStateGuard::class)->assertReady();
    expect(Cache::get('fixture-once-context'))->toBeNull();
    expect(fn () => $i->import($s))->toThrow(RuntimeException::class);
    expect(fn () => $l->publish())->toThrow(RuntimeException::class);
});
it('surfaces saturation errors without evicting auth state or queued work', function () {
    Cache::store('auth')->put('security', 'keep', 120);
    Cache::put('security', 'state', 120);
    Queue::connection('redis')->push('FictionalFixtureHandler', []);
    $r = app('redis')->connection('state');
    $memory = $r->info('memory');
    $r->command('config', ['SET','maxmemory',(string)($memory['used_memory'] + 100000)]);
    expect(fn () => Cache::store('volatile')->put('too-large', str_repeat('x', 3000000), 120))->toThrow(RedisException::class);
    expect(Cache::store('auth')->get('security'))->toBe('keep')->and(Cache::get('security'))->toBe('state')->and(app('redis')->connection('default')->llen('queues:default'))->toBe(1);
    $stats = $r->info('stats');
    expect($stats['evicted_keys'])->toBe(0);
    $r->command('config', ['SET','maxmemory','32mb']);
});


it('routes disposable imported cache to cache DB and keeps its expiry', function () {
    $s = stateImportSnapshot([['cache','fixture-disposable','value']]);
    $i = app(DynamoStateImporter::class);
    $i->begin($s);
    $i->import($s);
    expect(Cache::store('volatile')->get('fixture-disposable'))->toBe('value')->and(Cache::get('fixture-disposable'))->toBeNull()->and(Cache::store('auth')->get('fixture-disposable'))->toBeNull();
});
