<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\Auth\SendPasswordResetLink;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

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
     * Send a reset link without revealing whether the email is registered.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $requestedEmail = $request->input('email');

        if (is_string($requestedEmail)) {
            $request->merge([
                'email' => strtolower(trim($requestedEmail)),
            ]);
        }

        $this->validateEmail($request);

        $email = $request->string('email')->toString();

        if (config('queue.default') === 'sync') {
            SendPasswordResetLink::dispatchAfterResponse($email);
        } else {
            SendPasswordResetLink::dispatch($email);
        }

        return response()->json([
            'status' => trans('passwords.sent_if_exists'),
        ]);
    }
}
