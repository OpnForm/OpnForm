<?php

namespace App\Service\Deployment;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Consume externally signed acceptance only. Never signs, imports or auto-renews. */
class HostedStateReceiptLifecycle
{
    public function publish(): void
    {
        [$receipt, $hash] = app(HostedStateGuard::class)->verifyPendingAcceptance();
        [$epochKey] = app(DynamoStateImporter::class)->keys();
        $store = Cache::store('redis')->getStore();
        $connection = $store->connection();
        $epoch = $connection->hgetall($epochKey);
        $initial = $receipt['phase'] === 'initial-recovery';
        $expected = $initial ? '' : ($receipt['previous_receipt_sha256'] ?? '');
        if (($epoch['complete'] ?? null) !== ($epoch['total'] ?? null)
            || (int) ($epoch['imported'] ?? -1) !== $receipt['imported_count']
            || ($initial && ($epoch['phase'] ?? null) !== 'sealed')
            || (! $initial && (($epoch['phase'] ?? null) !== 'admitted'
                || ! is_string($expected) || ! preg_match('/^[a-f0-9]{64}$/', $expected)
                || ($epoch['accepted_receipt'] ?? null) !== $expected || $expected === $hash
                || ($epoch['migration_id'] ?? null) !== $receipt['migration_id']))) {
            throw new RuntimeException('Receipt lifecycle is stale, incomplete or not linked to the admitted epoch');
        }
        if (($epoch['run_id'] ?? null) !== $receipt['redis_run_id']
            && (! isset($receipt['evidence']['redis_restore'])
                || ! is_string($receipt['evidence']['redis_restore'])
                || ! preg_match('/^[a-f0-9]{64}$/', $receipt['evidence']['redis_restore']))) {
            throw new RuntimeException('A changed Redis instance requires separate signed restoration evidence');
        }
        $codec = new StateRedisCodec($store->getRedis(), $store->getPrefix(), 'state');
        $markerKey = $store->getPrefix().'opnform:state-recovery:'.$receipt['migration_id'];
        $marker = $codec->packValue(['payload_sha256' => $hash, 'imported_count' => $receipt['imported_count']]);
        $result = $connection->eval(
            <<<'LUA'
if redis.call('HGET', KEYS[1], 'manifest') ~= ARGV[1] or redis.call('HGET', KEYS[1], 'dirty') ~= '0' then return 0 end
if ARGV[2] == '' then
 if redis.call('HGET', KEYS[1], 'phase') ~= 'sealed' then return 0 end
else
 if redis.call('HGET', KEYS[1], 'phase') ~= 'admitted' or redis.call('HGET', KEYS[1], 'accepted_receipt') ~= ARGV[2] then return 0 end
end
redis.call('HSET', KEYS[1], 'phase', 'admitted', 'accepted_receipt', ARGV[3], 'run_id', ARGV[4], 'migration_id', ARGV[6])
redis.call('HINCRBY', KEYS[1], 'acceptance_version', 1)
redis.call('SET', KEYS[2], ARGV[5]); return 1
LUA,
            2,
            $epochKey,
            $markerKey,
            $receipt['source_manifest_sha256'],
            $expected,
            $hash,
            $receipt['redis_run_id'],
            $marker,
            $receipt['migration_id']
        );
        if ($result !== 1) {
            throw new RuntimeException('Receipt publication lost its compare-and-swap or epoch fence');
        }
    }
}
