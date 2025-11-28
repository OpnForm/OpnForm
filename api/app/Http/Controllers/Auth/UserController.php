<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get authenticated user.
     */
    public function current(Request $request)
    {
        // Eager load active licenses and workspaces to prevent N+1 queries in UserResource
        $user = $request->user()->load([
            'licenses' => function ($query) {
                $query->active();
            },
            'workspaces' => function ($query) {
                $query->withPivot('role');
            }
        ]);

        return new UserResource($user);
    }

    public function deleteAccount()
    {
        $this->middleware('auth');
        if (Auth::user()->admin) {
            return $this->error([
                'message' => 'Cannot delete an admin. Stay with us 🙏',
            ]);
        }
        Auth::user()->delete();

        return $this->success([
            'message' => 'Sorry to see you go 👋',
        ]);
    }
}
