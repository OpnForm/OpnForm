<?php

namespace App\Enterprise\Oidc;

use App\Enterprise\Oidc\Models\IdentityConnection;

class RoleMapper
{
    /**
     * Map IdP groups to workspace role based on connection options.
     *
     * Role mappings are stored in connection.options.group_role_mappings.
     *
     * Returns the highest privilege role found, or null if no match.
     */
    public function mapGroupsToRole(IdentityConnection $connection, array $groups): ?string
    {
        if (empty($groups)) {
            return null;
        }

        $mappings = $connection->options['group_role_mappings'] ?? [];
        if (empty($mappings)) {
            return null;
        }

        // Priority order: owner > admin > editor > member
        $priority = [
            'owner' => 4,
            'admin' => 3,
            'editor' => 2,
            'member' => 1,
        ];

        $highestRole = null;
        $highestPriority = 0;

        foreach ($mappings as $mapping) {
            if (!isset($mapping['idp_group']) || !isset($mapping['role'])) {
                continue;
            }

            // Check if this IdP group matches any of the user's groups
            if (!in_array($mapping['idp_group'], $groups, true)) {
                continue;
            }

            $rolePriority = $priority[$mapping['role']] ?? 0;
            if ($rolePriority > $highestPriority) {
                $highestPriority = $rolePriority;
                $highestRole = $mapping['role'];
            }
        }

        return $highestRole;
    }
}
