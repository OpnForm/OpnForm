<?php

namespace App\Http\Controllers\Auth;

use App\Models\UserInvite;
use App\Models\Workspace;
use App\Service\WorkspaceHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserInviteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function listInvites(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        return (new WorkspaceHelper($workspace))->getAllInvites();
    }

    public function resendInvite(Workspace $workspace, $inviteId)
    {
        $this->authorize('adminAction', $workspace);
        $userInvite = $workspace->invites()->find($inviteId);
        if (!$userInvite) {
            return $this->error(['success' => false, 'message' => 'Invite not found for this workspace.']);
        }

        if ($userInvite->status == UserInvite::ACCEPTED_STATUS) {
            return $this->error(['success' => false, 'message' => 'Invite already accepted.']);
        }

        $userInvite->sendEmail();

        return $this->success(['message' => 'Invite email resent successfully.']);
    }

    public function cancelInvite(Workspace $workspace, $inviteId)
    {
        $this->authorize('adminAction', $workspace);
        $userInvite = $workspace->invites()->find($inviteId);
        if (!$userInvite) {
            return $this->error(['success' => false, 'message' => 'Invite not found for this workspace.']);
        }

        if ($userInvite->status == UserInvite::ACCEPTED_STATUS) {
            return $this->error(['success' => false, 'message' => 'Invite already accepted.']);
        }

        $userInvite->delete();

        return $this->success(['message' => 'Invite deleted successfully.']);
    }
}
