<?php

namespace App\Http\Middleware;

use App\Service\Plan\PlanService;
use Closure;
use Illuminate\Http\Request;

class RequirePlan
{
    public function __construct(protected PlanService $planService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  string  $minimumTier  The minimum tier required (pro, business, enterprise)
     */
    public function handle(Request $request, Closure $next, string $minimumTier = 'pro')
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        $userTier = $this->planService->getUserTier($user);

        if (!$this->planService->tierMeetsRequirement($userTier, $minimumTier)) {
            $tierDisplayName = $this->planService->getTierDisplayName($minimumTier);

            return response()->json([
                'message' => "A {$tierDisplayName} plan is required to use this feature.",
                'required_tier' => $minimumTier,
                'current_tier' => $userTier,
            ], 402);
        }

        return $next($request);
    }
}
