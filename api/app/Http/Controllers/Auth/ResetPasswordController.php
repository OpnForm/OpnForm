<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\MaxPasswordLength;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    use ResetsPasswords {
        rules as protected defaultRules;
    }

    protected function rules()
    {
        $rules = $this->defaultRules();
        $rules['password'] = array_merge(['bail', 'string', new MaxPasswordLength()], $rules['password']);

        return $rules;
    }

    protected function resetPassword($user, $password)
    {
        $user = DB::transaction(function () use ($user, $password) {
            $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $this->setUserPassword($user, $password);
            $user->setRememberToken(Str::random(60));
            // The User event revokes existing credentials on this connection.
            $user->save();

            return $user;
        });

        event(new PasswordReset($user));
        $this->guard()->login($user);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get the response for a successful password reset.
     *
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetResponse(Request $request, $response)
    {
        return ['status' => trans($response)];
    }

    /**
     * Get the response for a failed password reset.
     *
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetFailedResponse(Request $request, $response)
    {
        return response()->json(['email' => trans($response)], 400);
    }
}
