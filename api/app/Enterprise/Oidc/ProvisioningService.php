<?php

namespace App\Enterprise\Oidc;

use App\Enterprise\Oidc\Exceptions\OidcAccountLinkRequiredException;
use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Models\UserIdentity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ProvisioningService
{
    public function __construct(
        private RoleMapper $roleMapper,
        private DomainJoiner $domainJoiner,
        private ExternalUserFactory $userFactory,
    ) {
    }

    /**
     * Provision or authenticate a user from OIDC claims.
     *
     * @throws \Exception
     */
    public function provisionUser(
        IdentityConnection $connection,
        SocialiteUser $socialiteUser,
        array $idTokenClaims
    ): User {
        return DB::transaction(function () use ($connection, $socialiteUser, $idTokenClaims) {
            // Validate ID token claims
            $this->validateIdToken($connection, $idTokenClaims);

            $subject = $idTokenClaims['sub'];

            // Extract email and name from claims
            $email = $this->extractEmail($socialiteUser, $idTokenClaims, $connection);
            $name = $this->extractName($socialiteUser, $idTokenClaims, $connection);

            if (!$email) {
                throw new \Exception('Email is required for OIDC authentication. Check your field mappings configuration.');
            }

            // Check for existing UserIdentity
            $userIdentity = UserIdentity::where('connection_id', $connection->id)
                ->where('subject', $subject)
                ->first();

            if ($userIdentity) {
                // Update claims and email if changed
                $userIdentity->update([
                    'email' => $email,
                    'claims' => $idTokenClaims,
                ]);

                $user = $userIdentity->user;

                Log::info('OIDC user authenticated', [
                    'connection_id' => $connection->id,
                    'user_id' => $user->id,
                    'subject' => $subject,
                    'email' => $email,
                ]);
            } else {
                // Check if user with same email exists
                $existingUser = User::where('email', strtolower($email))->first();

                if ($existingUser) {
                    // Account linking - require explicit action for security
                    Log::warning('OIDC account linking required', [
                        'connection_id' => $connection->id,
                        'existing_user_id' => $existingUser->id,
                        'subject' => $subject,
                        'email' => $email,
                    ]);

                    throw new OidcAccountLinkRequiredException(
                        email: $email,
                        subject: $subject,
                        connectionId: $connection->id,
                        claims: $idTokenClaims,
                    );
                }

                // Create new user
                $userName = $name ?? $socialiteUser->getName() ?? explode('@', $email)[0];
                $user = $this->userFactory->createVerifiedExternalUser(
                    name: $userName,
                    email: $email,
                    provider: 'oidc',
                    providerUserId: $subject,
                    utmData: null,
                    extraMeta: [],
                    setRandomPassword: true,
                );

                // Create UserIdentity
                UserIdentity::create([
                    'user_id' => $user->id,
                    'connection_id' => $connection->id,
                    'subject' => $subject,
                    'email' => $email,
                    'claims' => $idTokenClaims,
                ]);

                Log::info('OIDC user provisioned', [
                    'connection_id' => $connection->id,
                    'user_id' => $user->id,
                    'subject' => $subject,
                    'email' => $email,
                ]);
            }

            // Handle workspace membership
            $this->handleWorkspaceMembership($connection, $user, $idTokenClaims);

            return $user;
        });
    }

    /**
     * Validate ID token claims.
     *
     * @throws \Exception
     */
    protected function validateIdToken(IdentityConnection $connection, array $claims): void
    {
        // Validate issuer
        if (isset($claims['iss']) && $claims['iss'] !== $connection->issuer) {
            throw new \Exception('Invalid issuer in ID token');
        }

        // Validate audience (client_id)
        $aud = $claims['aud'] ?? null;
        if (!$aud) {
            throw new \Exception('Missing audience in ID token');
        }

        $audArray = is_array($aud) ? $aud : [$aud];
        if (!in_array($connection->client_id, $audArray)) {
            throw new \Exception('Invalid audience in ID token');
        }

        // Validate expiration
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            throw new \Exception('ID token has expired');
        }

        // Validate subject
        if (empty($claims['sub'])) {
            throw new \Exception('Missing subject in ID token');
        }
    }

    /**
     * Handle workspace membership based on connection and claims.
     */
    protected function handleWorkspaceMembership(
        IdentityConnection $connection,
        User $user,
        array $claims
    ): void {
        // Get role from group mapping or default to member
        $groups = $claims['groups'] ?? $claims['group'] ?? [];
        $groupsArray = is_array($groups) ? $groups : [$groups];
        $role = $this->roleMapper->mapGroupsToRole($connection, $groupsArray) ?? 'member';

        // If connection has a workspace_id, ensure membership
        if ($connection->workspace_id) {
            $workspace = Workspace::find($connection->workspace_id);
            if (!$workspace) {
                return;
            }

            $wasMember = $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
            $this->domainJoiner->ensureWorkspaceMembership($user, $workspace, $role);

            Log::info('OIDC workspace membership ensured', [
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'role' => $role,
                'was_member' => $wasMember,
                'groups' => $groupsArray,
            ]);

            return;
        }

        // No workspace_id - use domain-based auto-join for global connections
        $email = $user->email;
        $workspace = $this->domainJoiner->findWorkspaceForDomain($email);

        if (!$workspace) {
            return;
        }

        $wasMember = $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
        $this->domainJoiner->ensureWorkspaceMembership($user, $workspace, $role);

        Log::info('OIDC workspace membership ensured via domain', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => $role,
            'was_member' => $wasMember,
            'email_domain' => $this->extractDomain($email),
            'groups' => $groupsArray,
        ]);
    }

    /**
     * Extract email from socialite user or ID token claims.
     */
    protected function extractEmail(SocialiteUser $socialiteUser, array $idTokenClaims, IdentityConnection $connection): ?string
    {
        // Try socialite user first
        $email = $socialiteUser->getEmail();
        if ($email) {
            return $email;
        }

        // Get field mappings from connection options
        $fieldMappings = $connection->options['field_mappings'] ?? [];
        $emailField = !empty($fieldMappings['email']) ? $fieldMappings['email'] : null;

        // Try mapped field first (if configured)
        if ($emailField && isset($idTokenClaims[$emailField])) {
            return $idTokenClaims[$emailField];
        }

        // Fallback to standard fields
        return $idTokenClaims['email']
            ?? $idTokenClaims['email_address']
            ?? $idTokenClaims['mail']
            ?? $idTokenClaims['preferred_username'] // Common Keycloak field
            ?? null;
    }

    /**
     * Extract name from socialite user or ID token claims.
     */
    protected function extractName(SocialiteUser $socialiteUser, array $idTokenClaims, IdentityConnection $connection): ?string
    {
        // Try socialite user first
        $name = $socialiteUser->getName();
        if (!empty($name)) {
            return $name;
        }

        // Get field mappings from connection options
        $fieldMappings = $connection->options['field_mappings'] ?? [];
        $nameField = !empty($fieldMappings['name']) ? $fieldMappings['name'] : null;

        // Try mapped field first (if configured)
        if ($nameField && isset($idTokenClaims[$nameField]) && !empty($idTokenClaims[$nameField])) {
            return $idTokenClaims[$nameField];
        }

        // Fallback to standard fields
        $trimmedName = trim(($idTokenClaims['given_name'] ?? '') . ' ' . ($idTokenClaims['family_name'] ?? ''));

        return $idTokenClaims['name']
            ?? $idTokenClaims['display_name']
            ?? (!empty($trimmedName) ? $trimmedName : null)
            ?? $idTokenClaims['preferred_username']
            ?? null;
    }

    /**
     * Extract domain from email address.
     */
    protected function extractDomain(string $email): ?string
    {
        $parts = explode('@', strtolower(trim($email)));
        return count($parts) === 2 ? $parts[1] : null;
    }
}
