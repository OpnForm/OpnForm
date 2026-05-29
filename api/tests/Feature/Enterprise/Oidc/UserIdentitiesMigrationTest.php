<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Models\UserIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

function userIdentitiesMigration()
{
    return include database_path('migrations/2025_11_17_105205_create_user_identities_table.php');
}

it('does not drop existing user identities when migration up runs again', function () {
    expect(Schema::hasTable('user_identities'))->toBeTrue();

    $connection = IdentityConnection::factory()->create();
    $user = User::factory()->create();

    $identity = UserIdentity::factory()->create([
        'user_id' => $user->id,
        'connection_id' => $connection->id,
        'subject' => 'oidc-subject-keep-me',
        'email' => 'oidc-keep-me@example.com',
    ]);

    userIdentitiesMigration()->up();

    expect(Schema::hasTable('user_identities'))->toBeTrue();
    $this->assertDatabaseHas('user_identities', [
        'id' => $identity->id,
        'subject' => 'oidc-subject-keep-me',
        'email' => 'oidc-keep-me@example.com',
    ]);
});

it('creates user identities table when missing', function () {
    Schema::dropIfExists('user_identities');

    userIdentitiesMigration()->up();

    expect(Schema::hasTable('user_identities'))->toBeTrue();
    expect(Schema::hasColumn('user_identities', 'connection_id'))->toBeTrue();
    expect(Schema::hasColumn('user_identities', 'subject'))->toBeTrue();
});
