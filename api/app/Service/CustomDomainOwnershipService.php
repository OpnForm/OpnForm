<?php

namespace App\Service;

use RuntimeException;

class CustomDomainOwnershipService
{
    // Leave room for the TXT record prefix within the DNS name length limit.
    public const MAX_DOMAIN_LENGTH = 231;

    public const DOMAIN_REGEX = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    public function challenge(int $workspaceId, string $domain): array
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('An application key is required for domain verification.');
        }

        $domain = strtolower(trim($domain));
        $token = hash_hmac('sha256', 'opnform:'.$workspaceId.':'.$domain, $key);

        return [
            'type' => 'TXT',
            'host' => '_opnform-verification.'.$domain,
            'value' => 'opnform-verification='.$token,
        ];
    }

    public function verify(int $workspaceId, string $domain): bool
    {
        if (strlen(trim($domain)) > self::MAX_DOMAIN_LENGTH) {
            return false;
        }

        $challenge = $this->challenge($workspaceId, $domain);
        foreach ($this->txtRecords($challenge['host']) as $record) {
            $value = $record['txt'] ?? implode('', $record['entries'] ?? []);
            if (hash_equals($challenge['value'], $value)) {
                return true;
            }
        }

        return false;
    }

    protected function txtRecords(string $host): array
    {
        return @dns_get_record($host, DNS_TXT) ?: [];
    }
}
