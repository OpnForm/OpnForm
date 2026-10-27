<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Rules\SafeAccountName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }
        $requestedEmail = $request->input('email');
        $emailChanged = is_string($requestedEmail)
            && strtolower($requestedEmail) !== strtolower($user->email);

        $rules = [
            'name' => ['bail', 'required', 'string', 'max:255', new SafeAccountName()],
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        if ($emailChanged && $user->canChangeEmail()) {
            $rules['current_password'] = ['required', 'string', 'max:1024'];
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

        return DB::transaction(function () use ($request, $user, $emailChanged) {
            if ($emailChanged) {
                Password::broker()->deleteToken($user);
            }

            return tap($user)->update([
                'name' => $request->name,
                'email' => strtolower($request->email),
            ]);
        });
    }
}
