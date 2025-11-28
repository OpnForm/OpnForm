<?php

namespace App\Http\Middleware\Form;

use App\Models\Forms\Form;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProtectedForm
{
    public const PASSWORD_HEADER_NAME = 'form-password';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->route('form')) {
            return $next($request);
        }

        $form = $request->route('form');
        $userIsFormOwner = Auth::check() && Auth::user()->ownsForm($form);
        if (! $userIsFormOwner && $this->isProtected($request, $form)) {
            return response([
                'status' => 'Unauthorized',
                'message' => 'Form is protected.',
            ], 403);
        }

        return $next($request);
    }

    public static function isProtected(Request $request, Form $form)
    {
        if (! $form->has_password) {
            return false;
        }

        return ! self::hasCorrectPassword($request, $form);
    }

    public static function hasCorrectPassword(Request $request, Form $form)
    {
        return $request->headers->has(self::PASSWORD_HEADER_NAME) && $request->headers->get(self::PASSWORD_HEADER_NAME) == hash('sha256', $form->password);
    }
}
