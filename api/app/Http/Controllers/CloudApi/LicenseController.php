<?php

namespace App\Http\Controllers\CloudApi;

use App\Http\Controllers\Controller;
use App\Service\License\LicenseKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(private LicenseKeyService $licenseKeyService)
    {
    }

    /**
     * Create a Stripe checkout session for self-hosted license purchase.
     * Public endpoint — no auth required. Rate limited.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'billingEmail' => 'required|email',
            'plan' => 'required|string|in:self_hosted',
            'period' => 'required|string|in:monthly,yearly',
        ]);

        try {
            $result = $this->licenseKeyService->createCheckoutSession(
                billingEmail: $request->input('billingEmail'),
                plan: $request->input('plan'),
                period: $request->input('period'),
            );

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create checkout session.'], 500);
        }
    }

    /**
     * Validate a license key.
     * Public endpoint — the license key itself is the credential.
     */
    public function validateKey(Request $request): JsonResponse
    {
        $request->validate([
            'licenseKey' => 'required|string|min:10',
            'instanceId' => 'required|string|max:255',
            'usage' => 'nullable|array',
        ]);

        $result = $this->licenseKeyService->validate(
            key: $request->input('licenseKey'),
            instanceId: $request->input('instanceId'),
            usage: $request->input('usage', []),
        );

        return response()->json($result);
    }

    public function portal(Request $request): JsonResponse
    {
        $request->validate([
            'licenseKey' => 'required|string|min:10',
        ]);

        try {
            return response()->json(
                $this->licenseKeyService->createBillingPortalSession($request->input('licenseKey'))
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create billing portal session.'], 500);
        }
    }

}
