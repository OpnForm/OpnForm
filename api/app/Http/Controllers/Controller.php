<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Service\Plan\PlanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function success($data = [])
    {
        return response()->json(array_merge([
            'type' => 'success',
        ], $data));
    }

    public function error($data = [], $statusCode = 400)
    {
        return response()->json(array_merge([
            'type' => 'error',
        ], $data), $statusCode);
    }

    protected function featureDenied(Workspace $workspace, string $feature)
    {
        $planService = app(PlanService::class);
        $requiredTier = $planService->getRequiredTier($feature) ?? 'pro';
        $tierName = $planService->getTierDisplayName($requiredTier);

        return response()->json([
            'message' => "A {$tierName} plan is required to use this feature.",
            'required_tier' => $requiredTier,
            'current_tier' => $planService->getWorkspaceTier($workspace),
        ], 402);
    }
}
