<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OAuthRedirectRequest;
use App\Service\OAuth\OAuthFlowOrchestrator;
use Illuminate\Http\Request;

class OAuthController extends Controller
{
    public function __construct(
        private OAuthFlowOrchestrator $flowOrchestrator
    ) {
    }

    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirect(OAuthRedirectRequest $request, string $provider)
    {
        return response()->json(
            $this->flowOrchestrator->processRedirect(
                $provider,
                $request->validated()
            )
        );
    }

    /**
     * Handle the OAuth callback from the provider.
     */
    public function callback(Request $request, string $provider)
    {
        $params = $request->all();

        // Orchestrator now returns JsonResponse directly with proper status codes
        return $this->flowOrchestrator->processCallback($provider, $params);
    }

    /**
     * Handle widget-based OAuth callback.
     */
    public function handleWidgetCallback(Request $request, string $service)
    {
        $request->validate([
            'intent' => 'required|in:auth,integration',
            'invite_token' => 'sometimes|string',
            'utm_data' => 'sometimes|array',
        ]);

        // Orchestrator now returns JsonResponse directly with proper status codes
        return $this->flowOrchestrator->processWidgetCallback($service, $request);
    }
}
