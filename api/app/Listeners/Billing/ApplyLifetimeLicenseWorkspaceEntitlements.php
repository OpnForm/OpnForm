<?php

namespace App\Listeners\Billing;

use App\Events\Models\UserWorkspaceCreated;
use App\Models\User;
use App\Service\Billing\LifetimeLicenseWorkspaceEntitlements;

class ApplyLifetimeLicenseWorkspaceEntitlements
{
    public function __construct(
        protected LifetimeLicenseWorkspaceEntitlements $entitlements,
    ) {
    }

    public function handle(UserWorkspaceCreated $event): void
    {
        if ($event->userWorkspace->role !== User::ROLE_ADMIN) {
            return;
        }

        $user = $event->userWorkspace->user;
        $workspace = $event->userWorkspace->workspace;

        if (!$user || !$workspace) {
            return;
        }

        $this->entitlements->applyForUser($workspace, $user);
    }
}
