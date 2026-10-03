<?php

namespace App\Service\OAuth;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class McpPassportClientRepository extends ClientRepository
{
    /**
     * Compatibility bridge for Laravel MCP dynamic registration on Passport 12.
     *
     * Passport 13 cannot coexist with OpnForm's current JWT dependency because
     * they require incompatible major versions of lcobucci/jwt.
     *
     * @param  array<int, string>  $redirectUris
     */
    public function createAuthorizationCodeGrantClient(
        string $name,
        array $redirectUris,
        bool $confidential,
        bool $enableDeviceFlow
    ): Client {
        $client = $this->create(
            null,
            $name,
            implode(',', $redirectUris),
            null,
            false,
            false,
            $confidential
        );

        // Laravel MCP uses Passport 13's response attributes. Keep them
        // in-memory while Passport 12 persists its equivalent redirect field.
        $client->setAttribute('redirect_uris', $redirectUris);
        $client->setAttribute('grant_types', ['authorization_code', 'refresh_token']);

        return $client;
    }
}
