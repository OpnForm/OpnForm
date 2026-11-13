<?php

namespace App\Service\Integrations;

use App\Models\Integration\FormIntegration;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RedisWebhookFailureStreak
{
    private const UPDATE_STREAK = <<<'LUA'
        local key = KEYS[1]
        local code = ARGV[1]
        if code == '' then
            redis.call('DEL', key)
            return 0
        end

        local now = tonumber(ARGV[2])
        local window = tonumber(ARGV[3])
        local limit = tonumber(ARGV[4])
        local failures = {}
        local raw = redis.call('GET', key)
        if raw then
            local previous = cjson.decode(raw)
            if previous.code == code then
                for _, timestamp in ipairs(previous.failures) do
                    if timestamp >= now - window then
                        table.insert(failures, timestamp)
                    end
                end
            end
        end

        table.insert(failures, now)
        if #failures > limit then
            table.remove(failures, 1)
        end
        redis.call('SET', key, cjson.encode({code = code, failures = failures}), 'EX', window + 1)
        return #failures
        LUA;

    /** A null result requests the SQL fallback. */
    public function reachedLimit(FormIntegration $integration, ?int $code, int $window, int $limit): ?bool
    {
        if (config('cache.stores.' . config('cache.default') . '.driver') !== 'redis') {
            return null;
        }

        try {
            /** @var RedisStore $store */
            $store = Cache::store('volatile')->getStore();
            $version = $integration->failure_streak['version'] ?? 'initial';
            $key = $store->getPrefix() . "integration-failure-streak:{$integration->id}:{$version}";
            $count = $store->connection()->eval(
                self::UPDATE_STREAK,
                1,
                $key,
                $code === null ? '' : (string) $code,
                (string) now()->getTimestamp(),
                (string) $window,
                (string) $limit,
            );

            if (!is_int($count)) {
                throw new \RuntimeException('Redis did not return a failure count.');
            }

            return $count >= $limit;
        } catch (Throwable $exception) {
            Log::warning('Could not update webhook failure streak in Redis', [
                'integration_id' => $integration->id,
                'exception_type' => $exception::class,
            ]);

            return false;
        }
    }
}
