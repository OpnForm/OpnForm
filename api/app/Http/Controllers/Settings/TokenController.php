<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AccessTokenAbility;
use App\Http\Requests\CreateTokenRequest;
use App\Http\Resources\TokenResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController
{
    use AuthorizesRequests;

    public function abilities()
    {
        return response()->json(!config('app.self_hosted') && request()->user()->moderator && !request()->user()->is_blocked ? AccessTokenAbility::adminValues() : []);
    }

    public function index()
    {
        return TokenResource::collection(
            Auth::user()->tokens()->get()
        );
    }

    public function store(CreateTokenRequest $request)
    {
        $abilities = $request->input('abilities') ?? [];
        $admin = array_intersect($abilities, AccessTokenAbility::adminValues());
        if ($admin) {
            abort_unless(!config('app.self_hosted') && $request->user()->moderator && !$request->user()->is_blocked, 403);
            $request->validate(['expires_at' => 'required|date|after:now|before_or_equal:'.now()->addDays(90)->toIso8601String()]);
        }
        $token = Auth::user()->createToken(
            $request->input('name'),
            array_values(array_unique([...AccessTokenAbility::allowed($abilities), ...$admin])),
            $admin ? \Carbon\Carbon::parse($request->input('expires_at')) : null
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'message' => 'Access token successfully created!',
        ]);
    }

    public function destroy(PersonalAccessToken $token)
    {
        $this->authorize('delete', $token);

        $token->delete();

        return response()->json();
    }
}
