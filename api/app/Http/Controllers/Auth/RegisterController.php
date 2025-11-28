<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Service\WorkspaceInviteService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Rules\ValidReCaptcha;

class RegisterController extends Controller
{
    use RegistersUsers;

    private ?bool $appsumoLicense = null;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');

        $this->middleware('throttle:5,1')->only('register'); // 5 attempts per minute
        $this->middleware('throttle:30,60')->only('register'); // 30 attempts per hour
    }

    /**
     * The user has been registered.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function registered(Request $request, User $user)
    {
        if ($user instanceof MustVerifyEmail) {
            return response()->json(['status' => trans('verification.sent')]);
        }

        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->fromUser($user);

        // The 'new_user' field is used by the front-end to ensure that we can track this event as a registration.
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'appsumo_license' => $this->appsumoLicense,
            'new_user' => true,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $rules = [
            'name' => 'required|max:255',
            'email' => 'required|email:filter|max:255|unique:users|indisposable',
            'password' => [
                'required',
                'string',
                'min:8', // Minimum password length
                'regex:/[A-Za-z]/', // Include letters
                'regex:/[0-9]/', // Include numbers
                'regex:/[@$!%*#?&\-_+=.,:;<>^()[\]{}|~]/', // Include special characters (expanded set)
                'confirmed',
            ],
            'hear_about_us' => 'required|string',
            'appsumo_license' => ['nullable'],
            'invite_token' => ['nullable', 'string'],
            'utm_data' => ['nullable', 'array'],
        ];

        // Only require terms agreement in cloud/hosted mode, not self-hosted
        if (!config('app.self_hosted')) {
            $rules['agree_terms'] = ['required', Rule::in([true])];
        }

        if (config('services.re_captcha.secret_key')) {
            $rules['g-recaptcha-response'] = [new ValidReCaptcha()];
        }

        return Validator::make($data, $rules, [
            'agree_terms' => 'Please agree with the terms and conditions.',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     */
    protected function create(array $data)
    {
        $this->checkRegistrationAllowed($data);
        [$workspace, $role] = app(WorkspaceInviteService::class)->getWorkspaceAndRole($data);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => bcrypt($data['password']),
            'hear_about_us' => $data['hear_about_us'],
            'utm_data' => array_key_exists('utm_data', $data) ? $data['utm_data'] : null,
            'meta' => ['registration_ip' => request()->ip()],
        ]);

        // Add relation with user
        $user->workspaces()->sync([
            $workspace->id => [
                'role' => $role,
            ],
        ], false);

        $this->appsumoLicense = AppSumoAuthController::registerWithLicense($user, $data['appsumo_license'] ?? null);

        // Clear feature flags cache when first user is created (affects setup_required flag)
        if (config('app.self_hosted') && $user->id === 1) {
            \Illuminate\Support\Facades\Cache::forget('feature_flags');
        }

        return $user;
    }

    private function checkRegistrationAllowed(array $data)
    {
        if (config('app.self_hosted') && !array_key_exists('invite_token', $data) && (app()->environment() !== 'testing')) {
            // Allow registration during setup (when no users exist)
            if (!\App\Models\User::max('id')) {
                return; // Setup mode - allow registration
            }
            return response()->json(['message' => 'Registration is not allowed.'], 400)->throwResponse();
        }
    }
}
