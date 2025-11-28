<?php

namespace App\Service;

use App\Models\Workspace;

class WorkspaceHelper
{
    public function __construct(public Workspace $workspace)
    {
    }

    public function getAllUsers()
    {
        return $this->workspace->users()->withPivot('role')->get();
    }

    public function getAllInvites()
    {
        return $this->workspace->invites()->get();
    }
}
