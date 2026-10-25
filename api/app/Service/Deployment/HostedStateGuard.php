<?php

namespace App\Service\Deployment;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/** Runtime state bindings only; never imports state or approves a traffic cutover. */
class HostedStateGuard
{
    public function assertReady(): void
    {
        $this->verify(true);
    }

    /** Validate a prospective signed acceptance; does not publish readiness. */
    public function verifyPendingAcceptance(): array
    {
        if (config('hosted-state.mode') !== 'redis' || ! app()->environment('production')) {
            $this->fail('Acceptance publication requires production Redis mode');
        }

        return $this->verify(false);
    }

    private function verify(bool $requireCheckpoint): array
    {
        $mode = config('hosted-state.mode', 'historical-dynamodb');
        $store = config('session.store') ?: config('session.driver');

        if ($mode === 'historical-dynamodb') {
            if (app()->environment('production')
                && (config('cache.default') !== 'dynamodb'
                    || config('session.driver') !== 'dynamodb' || $store !== 'dynamodb')) {
                $this->fail('Initial production cutover must retain DynamoDB cache and sessions');
            }

            return [];
        }

        if (! in_array($mode, ['redis', 'redis-fresh'], true)) {
            $this->fail('Unknown hosted state mode');
        }

        if (config('cache.default') !== 'redis' || config('cache.stores.redis.driver') !== 'redis'
            || config('cache.stores.redis.connection') !== 'state'
            || config('session.driver') !== 'redis' || $store !== 'auth'
            || config('session.connection') !== 'auth'
            || config('jwt.providers.storage') !== UnifiedRedisJwtStorage::class
            || (config('cache.stores.redis.lock_connection') ?? 'state') !== 'state'
            || (config('hosted-state.scheduler_store') ?: config('cache.default')) !== 'redis'
            || config('jwt.blacklist_enabled') !== true) {
            $this->fail('Redis state requires protected auth/state bindings and the Redis scheduler');
        }

        // Fresh Redis is the maintenance strategy: cache and sessions start cold.
        // Source fencing, queue drain and the final revoked-token check belong to
        // the cutover runbook, not to an artificial DynamoDB import requirement.
        if ($mode === 'redis-fresh') {
            app(RedisStateTopology::class)->assertReady();

            return [];
        }

        if (! app()->environment('production')) {
            return [];
        }

        [$receipt, $payloadHash] = $this->signedReceipt();
        $now = time();
        if (($receipt['schema'] ?? null) !== 'opnform-state-recovery/v1'
            || ! in_array($receipt['phase'] ?? null, ['initial-recovery', 'recovery-renewal'], true)
            || ($receipt['source'] ?? null) !== 'dynamodb'
            || ($receipt['cutover_approved'] ?? null) !== true
            || ! is_int($receipt['verified_at'] ?? null) || $receipt['verified_at'] > $now
            || ! is_int($receipt['source_fenced_at'] ?? null) || $receipt['source_fenced_at'] <= 0
            || $receipt['source_fenced_at'] > $receipt['verified_at']
            || ! is_int($receipt['valid_until'] ?? null) || $receipt['valid_until'] <= $now
            || $receipt['valid_until'] - $receipt['verified_at'] > 3600
            || ! is_string($receipt['migration_id'] ?? null)
            || ! preg_match('/^[a-zA-Z0-9-]{1,80}$/', $receipt['migration_id'])
            || ! $this->digest($receipt['source_manifest_sha256'] ?? null)
            || ! is_int($receipt['source_unexpired_count'] ?? null) || $receipt['source_unexpired_count'] < 1
            || ($receipt['imported_count'] ?? null) !== $receipt['source_unexpired_count']
            || ($receipt['unknown_count'] ?? null) !== 0) {
            $this->fail('Recovery receipt is incomplete, expired or lacks cutover approval');
        }

        foreach (['identity', 'source_fence', 'sessions', 'ttl', 'revocations', 'atomicity', 'durability', 'rollback'] as $check) {
            if (! $this->digest($receipt['evidence'][$check] ?? null)) {
                $this->fail('Recovery acceptance evidence is incomplete');
            }
        }

        $revision = is_file('/app-revision') ? trim(file_get_contents('/app-revision')) : '';
        if (! preg_match('/^[a-f0-9]{40}$/', $revision)
            || ($receipt['revision'] ?? null) !== $revision
            || ! is_string(config('hosted-state.namespace')) || config('hosted-state.namespace') === ''
            || ! is_string($receipt['configuration_sha256'] ?? null)
            || ! hash_equals($this->configurationFingerprint(), $receipt['configuration_sha256'])) {
            $this->fail('Recovery receipt does not bind this committed runtime and state configuration');
        }

        $probes = $receipt['probes'] ?? null;
        if (! is_array($probes) || count($probes) < 1 || count($probes) > 20) {
            $this->fail('Recovery needs retained-state witnesses, not an empty Redis');
        }

        try {
            $cache = Cache::store('redis');
            $redis = $cache->getStore();
            if (! $redis instanceof RedisStore || $redis->connection() !== $redis->getRedis()->connection('state')) {
                $this->fail('Effective state store is not the durable Redis state connection');
            }
            app(RedisStateTopology::class)->assertReady();
            $server = $redis->connection()->info('server');
            $persistence = $redis->connection()->info('persistence');
            $runId = $server['run_id'] ?? $server['Server']['run_id'] ?? null;
            $persistence = $persistence['Persistence'] ?? $persistence;
            if (! is_string($runId) || ($receipt['redis_run_id'] ?? null) !== $runId
                || ($persistence['aof_enabled'] ?? null) !== 1
                || ($persistence['aof_last_write_status'] ?? null) !== 'ok') {
                $this->fail('Redis instance or persistence differs from recovery acceptance');
            }
            [$epochKey] = app(DynamoStateImporter::class)->keys();
            $epoch = $redis->connection()->hgetall($epochKey);
            if (($epoch['manifest'] ?? null) !== $receipt['source_manifest_sha256']
                || ($epoch['dirty'] ?? null) !== '0'
                || ($requireCheckpoint && (($epoch['phase'] ?? null) !== 'admitted'
                    || ($epoch['accepted_receipt'] ?? null) !== $payloadHash))) {
                $this->fail('Import epoch is not bound to this accepted recovery');
            }
            $markerKey = 'opnform:state-recovery:'.$receipt['migration_id'];
            $marker = $cache->get($markerKey);
            if ($requireCheckpoint && (! is_array($marker) || ($marker['payload_sha256'] ?? null) !== $payloadHash
                || ($marker['imported_count'] ?? null) !== $receipt['imported_count']
                || $redis->connection()->pttl($redis->getPrefix().$markerKey) !== -1)) {
                $this->fail('Verified recovery checkpoint is missing from Redis');
            }
            foreach ($probes as $probe) {
                if (! is_array($probe) || ! is_string($probe['key'] ?? null)
                    || $probe['key'] === '' || strlen($probe['key']) > 1024
                    || str_starts_with($probe['key'], 'opnform:state-recovery:')
                    || ! in_array($probe['kind'] ?? null, ['session', 'cache', 'jwt-revocation'], true)
                    || ! $this->digest($probe['value_sha256'] ?? null)
                    || ! is_int($probe['expires_at'] ?? null) || $probe['expires_at'] < $receipt['valid_until']) {
                    $this->fail('Recovery witness is malformed or expires before admission');
                }
                // JWT storage uses tags on Redis but not DynamoDB. Verify the actual
                // provider lookup and its tagged physical key, not a raw jti copy.
                $probeCache = in_array($probe['kind'], ['session', 'jwt-revocation'], true) ? Cache::store('auth') : $cache;
                $probeRedis = $probeCache->getStore();
                $physicalKey = $probe['key'];
                if ($probe['kind'] === 'jwt-revocation') {
                    $tagged = $probeCache->tags('tymon.jwt');
                    $physicalKey = $tagged->taggedItemKey($probe['key']);
                    $value = app('tymon.jwt.provider.storage')->get($probe['key']);
                } else {
                    $value = $probeCache->get($probe['key']);
                }
                $ttl = $probeRedis->connection()->pttl($probeRedis->getPrefix().$physicalKey);
                $remaining = $probe['expires_at'] * 1000 - (int) round(microtime(true) * 1000);
                if ($value === null || ! hash_equals($probe['value_sha256'], hash('sha256', serialize($value)))
                    || $ttl <= 0 || $remaining <= 0 || $ttl < $remaining || $ttl - $remaining > 2000) {
                    $this->fail('Retained Redis state or its absolute expiration does not match acceptance');
                }
            }
        } catch (Throwable) {
            // Never expose payloads, keys, credentials or provider error details.
            $this->fail('Redis recovery checkpoint or retained-state verification failed');
        }

        return [$receipt, $payloadHash];
    }

    /** Private binding; no identity material or endpoint values leave the process. */
    public function configurationFingerprint(): string
    {
        return hash('sha256', json_encode([
            'namespace' => config('hosted-state.namespace'),
            'cache_prefix' => config('cache.prefix'),
            'redis_options' => config('database.redis.options'),
            'redis_state' => config('database.redis.state'),
            'redis_auth' => config('database.redis.auth'),
            'role_stores' => [config('cache.stores.redis'), config('cache.stores.auth'), config('cache.stores.volatile')],
            'redis_volatile' => config('database.redis.cache'),
            'redis_queue' => config('database.redis.default'),
            'session' => config('session'),
            'identity' => [config('app.key'), config('app.cipher'), config('jwt.secret'), config('jwt.keys'), config('jwt.algo')],
            'jwt' => [config('jwt.blacklist_enabled'), config('jwt.blacklist_grace_period'),
                config('jwt.ttl'), config('jwt.refresh_ttl'), config('jwt.providers.storage')],
        ], JSON_THROW_ON_ERROR));
    }

    private function signedReceipt(): array
    {
        $envelope = json_decode($this->readOnlyFile(config('hosted-state.receipt_file'), 65536), true);
        $payload = is_array($envelope) && is_string($envelope['payload'] ?? null)
            ? base64_decode($envelope['payload'], true) : false;
        $signature = is_array($envelope) && is_string($envelope['signature'] ?? null)
            ? base64_decode($envelope['signature'], true) : false;
        $public = openssl_pkey_get_public($this->readOnlyFile(config('hosted-state.public_key_file'), 16384));
        $details = $public ? openssl_pkey_get_details($public) : false;
        if (! is_string($payload) || ! is_string($signature) || ! $details
            || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048
            || openssl_verify($payload, $signature, $public, OPENSSL_ALGO_SHA256) !== 1) {
            $this->fail('Recovery receipt signature is invalid');
        }
        $receipt = json_decode($payload, true);
        if (! is_array($receipt)) {
            $this->fail('Recovery receipt payload is invalid');
        }

        return [$receipt, hash('sha256', $payload)];
    }

    private function readOnlyFile(mixed $path, int $limit): string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || ! is_file($path)
            || ! is_readable($path) || is_writable($path) || filesize($path) > $limit) {
            $this->fail('Recovery receipt and trust key must be deployment-owned read-only files');
        }

        return file_get_contents($path);
    }

    private function digest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private function fail(string $message): never
    {
        throw new RuntimeException($message);
    }
}
