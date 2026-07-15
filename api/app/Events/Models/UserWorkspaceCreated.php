<?php

namespace App\Events\Models;

use App\Models\UserWorkspace;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserWorkspaceCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public UserWorkspace $userWorkspace)
    {
    }
}
