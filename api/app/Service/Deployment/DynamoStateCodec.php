<?php

namespace App\Service\Deployment;

use RuntimeException;

/** Decode only the documented Laravel DynamoDbStore scalar/array format. */
class DynamoStateCodec
{
    public function decode(array $row, string $prefix, array $attributes): array
    {
        $kind = $row['kind'] ?? null;
        $item = $row['item'] ?? [];
        $key = $item[$attributes['key']]['S'] ?? null;
        $expiration = $item[$attributes['expiration']]['N'] ?? null;
        $encoded = $item[$attributes['value']] ?? null;
        if (! in_array($kind, ['session', 'jwt-revocation', 'context', 'quota', 'cache'], true)
            || ! is_string($key) || ! str_starts_with($key, $prefix)
            || ! is_string($expiration) || ! ctype_digit($expiration)
            || strlen($expiration) > 13 || (int) $expiration > 9007199254740
            || ! is_array($encoded) || count($encoded) !== 1) {
            throw new RuntimeException('Unknown DynamoDB state shape, class, expiration or namespace');
        }
        $key = substr($key, strlen($prefix));
        if ($key === '' || strlen($key) > 1024 || str_starts_with($key, 'opnform:state-')
            || str_starts_with($key, 'tag:')) {
            throw new RuntimeException('Invalid or reserved source state key');
        }
        if (isset($encoded['N']) && is_string($encoded['N']) && is_numeric($encoded['N'])) {
            $raw = $encoded['N'];
            $value = filter_var($raw, FILTER_VALIDATE_INT) !== false ? (int) $raw : (float) $raw;
            if (! is_finite((float) $value)) {
                throw new RuntimeException('Unsupported numeric state');
            }
        } elseif (isset($encoded['S']) && is_string($encoded['S'])) {
            $raw = $encoded['S'];
            $value = strlen($raw) <= 1048576
                ? @unserialize($raw, ['allowed_classes' => false, 'max_depth' => 64]) : false;
            if (! $this->scalarTree($value) || serialize($value) !== $raw) {
                throw new RuntimeException('Unsupported or noncanonical serialized state');
            }
        } else {
            throw new RuntimeException('Unsupported DynamoDB value type');
        }
        if ($kind === 'session' && ! is_string($value)) {
            throw new RuntimeException('Session payload must remain a Laravel serialized string');
        }
        if ($kind === 'jwt-revocation' && ! is_array($value) && $value !== 'forever') {
            throw new RuntimeException('JWT revocation payload is incompatible');
        }

        return ['kind' => $kind, 'key' => $key, 'value' => $value, 'expires_at' => (int) $expiration];
    }

    private function scalarTree(mixed $value, int $depth = 0): bool
    {
        if ($depth > 64) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $child) {
                if (! $this->scalarTree($child, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }
}
