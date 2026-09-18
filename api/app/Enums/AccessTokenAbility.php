<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum AccessTokenAbility: string
{
    case ManageIntegrations = 'manage-integrations';
    // Granular scopes
    // Forms
    case FormsRead = 'forms-read';
    case FormsWrite = 'forms-write';

    // Workspaces
    case WorkspacesRead = 'workspaces-read';
    case WorkspacesWrite = 'workspaces-write';

    // Workspace Users
    case WorkspaceUsersRead = 'workspace-users-read';
    case WorkspaceUsersWrite = 'workspace-users-write';

    public static function adminValues(): array
    {
        return ['admin:users:read', 'admin:billing:read', 'admin:forms:read', 'admin:users:block', 'admin:users:unblock', 'admin:users:password-reset', 'admin:users:disable-2fa', 'admin:users:clear-cache', 'admin:billing:discount', 'admin:billing:extend-trial', 'admin:billing:cancel', 'admin:billing:refund', 'admin:billing:update', 'admin:forms:restore', 'admin:templates:create'];
    }

    public static function values(): array
    {
        return array_map(
            fn (AccessTokenAbility $case) => $case->value,
            static::cases()
        );
    }

    public static function allowed(array $abilities): array
    {
        return Arr::where(
            $abilities,
            fn (string $ability) => in_array($ability, static::values())
        );
    }
}
