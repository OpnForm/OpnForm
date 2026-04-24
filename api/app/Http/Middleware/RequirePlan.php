<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
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
     * Resolves the effective tier from the workspace (route-bound or via form),
     * falling back to the authenticated user's tier when no workspace context exists.
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

        $currentTier = $this->resolveEffectiveTier($request, $user);

        if (!$this->planService->tierMeetsRequirement($currentTier, $minimumTier)) {
            $tierDisplayName = $this->planService->getTierDisplayName($minimumTier);

            return response()->json([
                'message' => "A {$tierDisplayName} plan is required to use this feature.",
                'required_tier' => $minimumTier,
                'current_tier' => $currentTier,
            ], 402);
        }

        return $next($request);
    }

    /**
     * Resolve the effective tier: workspace-scoped when possible, user-global as fallback.
     */
    protected function resolveEffectiveTier(Request $request, $user): string
    {
        $workspace = $request->route('workspace');

        if (is_numeric($workspace)) {
            $workspace = Workspace::find($workspace);
        }

        if (!$workspace) {
            $form = $request->route('form');
            if ($form && $form->workspace) {
                $workspace = $form->workspace;
            }
        }

        if ($workspace) {
            return $this->planService->getWorkspaceTier($workspace);
        }

        return $this->planService->getUserTier($user);
    }
}
