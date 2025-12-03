<?php

use App\Enterprise\Oidc\Adapters\OAuthOidcDriver;
use App\Enterprise\Oidc\ConnectionManager;
use App\Enterprise\Oidc\Models\IdentityConnection;

uses()->group('oidc', 'feature');

describe('ConnectionManager', function () {
    it('builds OAuthOidcDriver instance', function () {
        $connection = IdentityConnection::factory()->create();
        $manager = new ConnectionManager();

        $driver = $manager->buildDriver($connection);

        expect($driver)->toBeInstanceOf(OAuthOidcDriver::class);
    });

    it('gets connection by slug when enabled', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-connection',
            'enabled' => true,
            'type' => IdentityConnection::TYPE_OIDC,
        ]);

        $manager = new ConnectionManager();
        $found = $manager->getConnectionBySlug('test-connection');

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($connection->id);
    });

    it('returns null for disabled connection', function () {
        IdentityConnection::factory()->create([
            'slug' => 'disabled-connection',
            'enabled' => false,
            'type' => IdentityConnection::TYPE_OIDC,
        ]);

        $manager = new ConnectionManager();
        $found = $manager->getConnectionBySlug('disabled-connection');

        expect($found)->toBeNull();
    });

    it('returns null for non-existent slug', function () {
        $manager = new ConnectionManager();
        $found = $manager->getConnectionBySlug('non-existent');

        expect($found)->toBeNull();
    });

    it('only returns OIDC type connections', function () {
        IdentityConnection::factory()->create([
            'slug' => 'saml-connection',
            'enabled' => true,
            'type' => IdentityConnection::TYPE_SAML,
        ]);

        $manager = new ConnectionManager();
        $found = $manager->getConnectionBySlug('saml-connection');

        expect($found)->toBeNull();
    });
});
