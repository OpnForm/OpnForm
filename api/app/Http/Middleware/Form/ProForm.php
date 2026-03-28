<?php

namespace App\Http\Middleware\Form;

use Closure;
use Illuminate\Http\Request;

class ProForm
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->route('form') && $form = $request->route('form')) {
            if ($form->is_pro) {
                $request->merge([
                    'form' => $form,
                ]);

                return $next($request);
            }
        }

        return response([
            'status' => 'Unauthorized',
            'message' => 'You need a subscription to access this content.',
        ], 403);
    }
}
