<?php

namespace App\Http\Middleware;

use App\Service\Billing\BillingStateResolver;
use Closure;
use Illuminate\Http\Request;

class IsSubscribed
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
        if ($request->user() && !$this->billingStateResolver->hasActivePaidSubscription($request->user())) {
            // This user is not a paying customer...
            if ($request->expectsJson()) {
                return response([
                    'message' => 'You are not subscribed to NotionForms Pro.',
                    'type' => 'error',
                ], 401);
            }

            return redirect('billing');
        }

        return $next($request);
    }
}
