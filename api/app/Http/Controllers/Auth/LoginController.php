<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\VerifyEmailException;
use App\Http\Controllers\Auth\Traits\ManagesJWT;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;
    use ManagesJWT {
        ManagesJWT::sendLoginResponse as traitSendLoginResponse;
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Attempt to log the user into the application.
     *
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        // Block password-based login if OIDC force_login is enabled
        if (config('oidc.force_login', false)) {
            $hasOidcConnection = \App\Enterprise\Oidc\Models\IdentityConnection::enabled()
                ->where('type', \App\Enterprise\Oidc\Models\IdentityConnection::TYPE_OIDC)
                ->exists();

            if ($hasOidcConnection) {
                throw ValidationException::withMessages([
                    $this->username() => ['Password-based login is disabled. Please use OIDC authentication.'],
                ]);
            }
        }

        // Only set custom TTL if remember me is checked
        $guard = $this->guard();

        if ($request->remember) {
            // Use the extended TTL from config for "Remember me"
            $guard->setTTL(config('jwt.remember_ttl'));
        }

        $token = $guard->attempt($this->credentials($request));

        if (! $token) {
            return false;
        }

        $user = $this->guard()->user();
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return false;
        }

        // Blocked check is now handled in sendLoginResponseWithTwoFactorCheck
        $guard->setToken($token);

        return true;
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @return array
     */
    protected function credentials(Request $request)
    {
        return [
            $this->username() => strtolower($request->get($this->username())),
            'password' => $request->password,
        ];
    }

    /**
     * Send the response after the user was authenticated.
     * Override to use centralized logic that handles 2FA and blocked user checks.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendLoginResponse(Request $request)
    {
        $this->clearLoginAttempts($request);

        $user = $this->guard()->user();

        // traitSendLoginResponse() automatically handles 2FA check and blocked user check
        return $this->traitSendLoginResponse($user, [
            'method' => 'password',
            'remember' => $request->remember ?? false,
        ]);
    }

    /**
     * Get the failed login response instance.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $user = $this->guard()->user();
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            throw VerifyEmailException::forUser($user);
        }

        $user = \App\Models\User::where($this->username(), strtolower($request->get($this->username())))->first();

        if ($user && $user->is_blocked) {
            throw ValidationException::withMessages([
                $this->username() => ['Your account has been blocked. Please contact support.'],
            ]);
        }

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Customize throttling to be per-credential (email) instead of IP-based, to avoid X-Forwarded-For spoofing bypasses.
     */
    protected function throttleKey(Request $request)
    {
        $email = strtolower((string) $request->input($this->username()));
        return 'login:' . sha1($email !== '' ? $email : 'unknown');
    }

    /**
     * Log the user out of the application.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();
    }
}
