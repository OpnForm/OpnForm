<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Service\Plan\PlanService;
use Closure;
use Illuminate\Http\Request;

class RequireFeature
{
    public function __construct(protected PlanService $planService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  string  $feature  The feature key to check (e.g., 'custom_domain', 'integrations.slack')
     */
    public function handle(Request $request, Closure $next, string $feature)
    {
        // Try to get workspace from route binding
        $workspace = $request->route('workspace');

        // If workspace is an ID, resolve it
        if (is_numeric($workspace)) {
            $workspace = Workspace::find($workspace);
        }

        // If no workspace in route, try to get from form's workspace
        if (!$workspace) {
            $form = $request->route('form');
            if ($form && $form->workspace) {
                $workspace = $form->workspace;
            }
        }

        // Fallback: use user's tier directly
        if (!$workspace) {
            $user = $request->user();
            if ($user) {
                $userTier = $this->planService->getUserTier($user);
                if (!$this->planService->tierHasFeature($userTier, $feature)) {
                    return $this->denyAccess($feature, $userTier);
                }
            }

            return $next($request);
        }

        // Check workspace feature access
        if (!$this->planService->workspaceHasFeature($workspace, $feature)) {
            $workspaceTier = $this->planService->getWorkspaceTier($workspace);

            return $this->denyAccess($feature, $workspaceTier);
        }

        return $next($request);
    }

    /**
     * Return access denied response.
     */
    protected function denyAccess(string $feature, string $currentTier)
    {
        $requiredTier = $this->planService->getRequiredTier($feature) ?? 'pro';
        $tierDisplayName = $this->planService->getTierDisplayName($requiredTier);

        return response()->json([
            'message' => "A {$tierDisplayName} plan is required to use this feature.",
            'feature' => $feature,
            'required_tier' => $requiredTier,
            'current_tier' => $currentTier,
        ], 402);
    }
}
