<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\RoleMapper;

uses()->group('oidc', 'feature');

describe('RoleMapper', function () {
    it('returns null when no groups provided', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'admins', 'role' => 'admin'],
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        expect($mapper->mapGroupsToRole($connection, []))->toBeNull();
    });

    it('returns null when no mappings configured', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [],
        ]);

        $mapper = new RoleMapper();
        expect($mapper->mapGroupsToRole($connection, ['admins']))->toBeNull();
    });

    it('returns null when groups do not match any mapping', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'admins', 'role' => 'admin'],
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        expect($mapper->mapGroupsToRole($connection, ['users']))->toBeNull();
    });

    it('maps single group to role', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'admins', 'role' => 'admin'],
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        expect($mapper->mapGroupsToRole($connection, ['admins']))->toBe('admin');
    });

    it('returns highest privilege role when multiple groups match', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'users', 'role' => 'member'],
                    ['idp_group' => 'editors', 'role' => 'editor'],
                    ['idp_group' => 'admins', 'role' => 'admin'],
                    ['idp_group' => 'owners', 'role' => 'owner'],
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        // User has multiple groups - should return highest privilege (owner)
        expect($mapper->mapGroupsToRole($connection, ['users', 'editors', 'owners']))->toBe('owner');
    });

    it('prioritizes roles correctly', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'group1', 'role' => 'member'],
                    ['idp_group' => 'group2', 'role' => 'admin'],
                    ['idp_group' => 'group3', 'role' => 'editor'],
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        // Should return admin (higher than editor and member)
        expect($mapper->mapGroupsToRole($connection, ['group1', 'group2', 'group3']))->toBe('admin');
    });

    it('handles invalid mapping entries gracefully', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'admins', 'role' => 'admin'],
                    ['invalid' => 'mapping'], // Missing required fields
                    ['idp_group' => 'users'], // Missing role
                    ['role' => 'member'], // Missing idp_group
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        // Should still work with valid mappings
        expect($mapper->mapGroupsToRole($connection, ['admins']))->toBe('admin');
        expect($mapper->mapGroupsToRole($connection, ['users']))->toBeNull();
    });

    it('handles case-sensitive group matching', function () {
        $connection = IdentityConnection::factory()->create([
            'options' => [
                'group_role_mappings' => [
                    ['idp_group' => 'Admins', 'role' => 'admin'],
                ],
            ],
        ]);

        $mapper = new RoleMapper();
        // Should be case-sensitive
        expect($mapper->mapGroupsToRole($connection, ['Admins']))->toBe('admin');
        expect($mapper->mapGroupsToRole($connection, ['admins']))->toBeNull();
    });
});
