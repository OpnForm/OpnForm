<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;

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
        $requestedEmail = $request->input('email');
        $emailChanged = is_string($requestedEmail)
            && strtolower($requestedEmail) !== strtolower($user->email);

        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        if ($emailChanged && $user->canChangeEmail()) {
            $rules['current_password'] = ['required', 'string'];
        }

        $this->validate($request, $rules);

        if ($emailChanged) {
            if (!$user->canChangeEmail()) {
                throw ValidationException::withMessages([
                    'email' => ['Your email address is managed by your sign-in provider and cannot be changed here.'],
                ]);
            }

            $reauthKey = 'profile-email-reauth:' . $user->id;

            if (RateLimiter::tooManyAttempts($reauthKey, 5)) {
                throw new ThrottleRequestsException('Too Many Attempts.');
            }

            if (!Hash::check($request->current_password, $user->password)) {
                RateLimiter::hit($reauthKey, 60);

                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            RateLimiter::clear($reauthKey);

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
