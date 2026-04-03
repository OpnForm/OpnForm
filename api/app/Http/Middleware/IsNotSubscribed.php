<?php

namespace App\Http\Middleware;

use App\Service\Billing\BillingStateResolver;
use Closure;
use Illuminate\Http\Request;

class IsNotSubscribed
{
    public function __construct(protected BillingStateResolver $billingStateResolver)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $this->billingStateResolver->hasActivePaidSubscription($request->user())) {
            // This user is a paying customer...
            if ($request->expectsJson()) {
                return response([
                    'message' => 'You are already subscribed to NotionForms Pro.',
                    'type' => 'error',
                ], 401);
            }

            return redirect('billing');
        }

        return $next($request);
    }
}
