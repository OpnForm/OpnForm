<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Models\UserIdentity;
use App\Enterprise\Oidc\ProvisioningService;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestHelpers;

require_once __DIR__ . '/../../../TestHelpers/OidcTestHelpers.php';

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

afterEach(function () {
    Mockery::close();
});

describe('ProvisioningService - New User Creation', function () {
    it('creates new user and UserIdentity when no existing user found', function () {
        $connection = IdentityConnection::factory()->create();
        $socialiteUser = createMockSocialiteUser(
            email: 'newuser@example.com',
            name: 'New User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-123');

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->email)->toBe('newuser@example.com');
        expect($user->name)->toBe('New User');
        expect($user->email_verified_at)->not->toBeNull();

        // Check UserIdentity was created
        $userIdentity = UserIdentity::where('user_id', $user->id)
            ->where('connection_id', $connection->id)
            ->first();
        expect($userIdentity)->not->toBeNull();
        expect($userIdentity->subject)->toBe('sub-123');
        expect($userIdentity->email)->toBe('newuser@example.com');
    });

    it('creates user with random password for SSO-only accounts', function () {
        $connection = IdentityConnection::factory()->create();
        $socialiteUser = createMockSocialiteUser(
            email: 'sso@example.com',
            name: 'SSO User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-456');

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user->password)->not->toBeNull();
        expect(strlen($user->password))->toBeGreaterThan(50); // Hashed password length
    });

    it('uses email prefix as name when name is not provided', function () {
        $connection = IdentityConnection::factory()->create();
        $socialiteUser = createMockSocialiteUser(
            email: 'noname@example.com',
            name: null
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-789');
        // Remove all name-related fields to test fallback to email prefix
        // Note: We need to ensure given_name and family_name are not set, otherwise
        // trim(' ') becomes empty string which is truthy
        unset($idTokenClaims['name']);
        unset($idTokenClaims['display_name']);
        unset($idTokenClaims['given_name']);
        unset($idTokenClaims['family_name']);
        unset($idTokenClaims['preferred_username']);

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        // The service should fall back to email prefix when name is null/empty
        // Current implementation: $name ?? $socialiteUser->getName() ?? explode('@', $email)[0]
        // Since $name will be null (all fields unset), it should use email prefix
        expect($user->name)->toBe('noname');
    });
});

describe('ProvisioningService - Existing User Login', function () {
    it('returns existing user when UserIdentity exists', function () {
        $connection = IdentityConnection::factory()->create();
        $existingUser = $this->createUser(['email' => 'existing@example.com']);

        $userIdentity = UserIdentity::factory()->create([
            'user_id' => $existingUser->id,
            'connection_id' => $connection->id,
            'subject' => 'sub-existing',
            'email' => 'existing@example.com',
        ]);

        $socialiteUser = createMockSocialiteUser(
            email: 'existing@example.com',
            name: 'Existing User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-existing');
        $idTokenClaims['name'] = 'Updated Name';

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user->id)->toBe($existingUser->id);
        expect($user->email)->toBe('existing@example.com');

        // Check UserIdentity was updated
        $userIdentity->refresh();
        expect($userIdentity->claims)->toHaveKey('name', 'Updated Name');
    });

    it('updates UserIdentity email if changed', function () {
        $connection = IdentityConnection::factory()->create();
        $existingUser = $this->createUser(['email' => 'old@example.com']);

        $userIdentity = UserIdentity::factory()->create([
            'user_id' => $existingUser->id,
            'connection_id' => $connection->id,
            'subject' => 'sub-update',
            'email' => 'old@example.com',
        ]);

        $socialiteUser = createMockSocialiteUser(
            email: 'new@example.com',
            name: 'Updated User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-update');
        $idTokenClaims['email'] = 'new@example.com';

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        $userIdentity->refresh();
        expect($userIdentity->email)->toBe('new@example.com');
    });
});

describe('ProvisioningService - Account Linking', function () {
    it('throws exception when user with email exists but no UserIdentity', function () {
        $connection = IdentityConnection::factory()->create();
        $existingUser = $this->createUser(['email' => 'existing@example.com']);

        $socialiteUser = createMockSocialiteUser(
            email: 'existing@example.com',
            name: 'Existing User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-new');

        $service = app(ProvisioningService::class);

        expect(fn () => $service->provisionUser($connection, $socialiteUser, $idTokenClaims))
            ->toThrow(Exception::class, 'An account with this email already exists');
    });
});

describe('ProvisioningService - Field Mappings', function () {
    it('uses custom email field mapping when configured', function () {
        $connection = IdentityConnection::factory()->withFieldMappings([
            'email' => 'mail',
            'name' => 'display_name',
        ])->create();

        $socialiteUser = createMockSocialiteUser(
            email: null, // Not provided by Socialite
            name: null
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-mapped');
        $idTokenClaims['mail'] = 'mapped@example.com';
        $idTokenClaims['display_name'] = 'Mapped Name';

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user->email)->toBe('mapped@example.com');
        expect($user->name)->toBe('Mapped Name');
    });

    it('falls back to standard fields when mapped field not found', function () {
        $connection = IdentityConnection::factory()->withFieldMappings([
            'email' => 'nonexistent_field',
        ])->create();

        $socialiteUser = createMockSocialiteUser(
            email: null,
            name: null
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-fallback');
        $idTokenClaims['email'] = 'fallback@example.com';
        $idTokenClaims['name'] = 'Fallback Name';

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user->email)->toBe('fallback@example.com');
        expect($user->name)->toBe('Fallback Name');
    });

    it('throws exception when email cannot be extracted', function () {
        $connection = IdentityConnection::factory()->create();
        $socialiteUser = createMockSocialiteUser(
            email: null,
            name: null
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-no-email');
        unset($idTokenClaims['email']);

        $service = app(ProvisioningService::class);

        expect(fn () => $service->provisionUser($connection, $socialiteUser, $idTokenClaims))
            ->toThrow(Exception::class, 'Email is required for OIDC authentication');
    });
});

describe('ProvisioningService - Workspace Membership', function () {
    it('adds user to workspace for workspace-scoped connection', function () {
        $workspace = Workspace::factory()->create();
        $connection = IdentityConnection::factory()->forWorkspace($workspace)->create();

        $socialiteUser = createMockSocialiteUser(
            email: 'workspace@example.com',
            name: 'Workspace User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-workspace');

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user->workspaces()->where('workspaces.id', $workspace->id)->exists())->toBeTrue();

        $pivot = $user->workspaces()->withPivot('role')->where('workspaces.id', $workspace->id)->first();
        expect($pivot->pivot->role)->toBe('member'); // Default role
    });

    it('applies role mapping from groups for workspace-scoped connection', function () {
        $workspace = Workspace::factory()->create();
        $connection = IdentityConnection::factory()
            ->forWorkspace($workspace)
            ->withRoleMappings([
                ['idp_group' => 'admins', 'role' => 'admin'],
                ['idp_group' => 'users', 'role' => 'member'],
            ])
            ->create();

        $socialiteUser = createMockSocialiteUser(
            email: 'admin@example.com',
            name: 'Admin User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-admin');
        $idTokenClaims['groups'] = ['admins', 'users'];

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        $pivot = $user->workspaces()->withPivot('role')->where('workspaces.id', $workspace->id)->first();
        expect($pivot->pivot->role)->toBe('admin'); // Highest privilege role
    });

    it('finds workspace by domain for global connection', function () {
        $workspace = Workspace::factory()->create([
            'settings' => [
                'oidc' => [
                    'allowed_domain' => 'example.com',
                ],
            ],
        ]);
        $connection = IdentityConnection::factory()->create(['workspace_id' => null]);

        $socialiteUser = createMockSocialiteUser(
            email: 'global@example.com',
            name: 'Global User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-global');

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user->workspaces()->where('workspaces.id', $workspace->id)->exists())->toBeTrue();
    });

    it('updates role for existing workspace member', function () {
        $workspace = Workspace::factory()->create();
        $user = $this->createUser(['email' => 'member@example.com']);
        $user->workspaces()->attach($workspace->id, ['role' => 'member']);

        $connection = IdentityConnection::factory()
            ->forWorkspace($workspace)
            ->withRoleMappings([
                ['idp_group' => 'admins', 'role' => 'admin'],
            ])
            ->create();

        $userIdentity = UserIdentity::factory()->create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'subject' => 'sub-member',
        ]);

        $socialiteUser = createMockSocialiteUser(
            email: 'member@example.com',
            name: 'Member User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-member');
        $idTokenClaims['groups'] = ['admins'];

        $service = app(ProvisioningService::class);
        $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        $pivot = $user->workspaces()->withPivot('role')->where('workspaces.id', $workspace->id)->first();
        expect($pivot->pivot->role)->toBe('admin'); // Role updated
    });
});

describe('ProvisioningService - ID Token Validation', function () {
    it('validates issuer matches connection issuer', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
        ]);
        $socialiteUser = createMockSocialiteUser();
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-invalid');
        $idTokenClaims['iss'] = 'https://wrong-issuer.com';

        $service = app(ProvisioningService::class);

        expect(fn () => $service->provisionUser($connection, $socialiteUser, $idTokenClaims))
            ->toThrow(Exception::class, 'Invalid issuer');
    });

    it('validates audience contains client_id', function () {
        $connection = IdentityConnection::factory()->create([
            'client_id' => 'correct-client-id',
        ]);
        $socialiteUser = createMockSocialiteUser();
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-invalid');
        $idTokenClaims['aud'] = 'wrong-client-id';

        $service = app(ProvisioningService::class);

        expect(fn () => $service->provisionUser($connection, $socialiteUser, $idTokenClaims))
            ->toThrow(Exception::class, 'Invalid audience');
    });

    it('validates audience when it is an array', function () {
        $connection = IdentityConnection::factory()->create([
            'client_id' => 'correct-client-id',
        ]);
        $socialiteUser = createMockSocialiteUser();
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-array');
        $idTokenClaims['aud'] = ['other-client', 'correct-client-id'];

        $service = app(ProvisioningService::class);
        $user = $service->provisionUser($connection, $socialiteUser, $idTokenClaims);

        expect($user)->toBeInstanceOf(User::class);
    });

    it('validates token expiration', function () {
        $connection = IdentityConnection::factory()->create();
        $socialiteUser = createMockSocialiteUser();
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-expired');
        $idTokenClaims['exp'] = time() - 3600; // Expired 1 hour ago

        $service = app(ProvisioningService::class);

        expect(fn () => $service->provisionUser($connection, $socialiteUser, $idTokenClaims))
            ->toThrow(Exception::class, 'ID token has expired');
    });

    it('validates subject is present', function () {
        $connection = IdentityConnection::factory()->create();
        $socialiteUser = createMockSocialiteUser();
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-missing');
        unset($idTokenClaims['sub']);

        $service = app(ProvisioningService::class);

        expect(fn () => $service->provisionUser($connection, $socialiteUser, $idTokenClaims))
            ->toThrow(Exception::class, 'Missing subject');
    });
});
