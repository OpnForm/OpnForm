<?php

namespace App\Enterprise\Oidc;

use App\Enterprise\Oidc\Adapters\OAuthOidcDriver;
use App\Enterprise\Oidc\Models\IdentityConnection;

class ConnectionManager
{
    /**
     * Build an OIDC driver instance for the given connection.
     */
    public function buildDriver(IdentityConnection $connection): OAuthOidcDriver
    {
        return new OAuthOidcDriver($connection);
    }

    /**
     * Get a connection by slug.
     */
    public function getConnectionBySlug(string $slug): ?IdentityConnection
    {
        return IdentityConnection::where('slug', $slug)
            ->where('enabled', true)
            ->where('type', IdentityConnection::TYPE_OIDC)
            ->first();
    }
}
