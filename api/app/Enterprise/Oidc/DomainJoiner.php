<?php

namespace App\Enterprise\Oidc;

use App\Models\User;
use App\Models\Workspace;

class DomainJoiner
{
    /**
     * Find workspace that should auto-join a user based on email domain.
     *
     * Checks settings.oidc.allowed_domain (dedicated OIDC domain field).
     *
     * @return Workspace|null
     */
    public function findWorkspaceForDomain(string $email): ?Workspace
    {
        $domain = $this->extractDomain($email);
        if (!$domain) {
            return null;
        }

        return Workspace::whereJsonContains('settings->oidc->allowed_domain', $domain)
            ->first();
    }

    /**
     * Extract domain from email address.
     */
    protected function extractDomain(string $email): ?string
    {
        $parts = explode('@', strtolower(trim($email)));
        return count($parts) === 2 ? $parts[1] : null;
    }

    /**
     * Ensure user is a member of the workspace with the given role.
     */
    public function ensureWorkspaceMembership(User $user, Workspace $workspace, string $role = 'member'): void
    {
        if (!$user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            $user->workspaces()->attach($workspace->id, ['role' => $role]);
        } else {
            // Update role if already a member
            $user->workspaces()->updateExistingPivot($workspace->id, ['role' => $role]);
        }
    }
}
