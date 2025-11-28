<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        // Check if email is actually changing
        $emailChanged = strtolower($request->email) !== strtolower($user->email);

        // Apply throttling only if email is changing
        if ($emailChanged) {
            $key = 'profilechange:' . $user->id;
            $attempts = RateLimiter::attempts($key);

            if ($attempts >= 2) {
                throw new ThrottleRequestsException('Too Many Attempts.');
            }

            RateLimiter::hit($key, 3600); // 1 hour
        }

        return tap($user)->update([
            'name' => $request->name,
            'email' => strtolower($request->email),
        ]);
    }
}
