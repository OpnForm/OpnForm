<?php

namespace App\Http\Controllers\CloudApi;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
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
            'successUrl' => 'required|url',
            'cancelUrl' => 'required|url',
        ]);

        try {
            $result = $this->licenseKeyService->createCheckoutSession(
                billingEmail: $request->input('billingEmail'),
                plan: $request->input('plan'),
                period: $request->input('period'),
                successUrl: $request->input('successUrl'),
                cancelUrl: $request->input('cancelUrl'),
            );

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ray($e->getMessage());
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
            'usage' => 'nullable|array',
        ]);

        $result = $this->licenseKeyService->validate($request->input('licenseKey'));

        return response()->json($result);
    }

    /**
     * Get license details by key.
     */
    public function show(string $licenseKey): JsonResponse
    {
        $license = LicenseKey::where('license_key', $licenseKey)->first();

        if (!$license) {
            return response()->json(['error' => 'License not found.'], 404);
        }

        return response()->json([
            'licenseKey' => $license->license_key,
            'status' => $license->isActive() ? 'active' : 'expired',
            'plan' => $license->plan,
            'features' => $license->isActive() ? $license->features : null,
            'expiresAt' => $license->expires_at?->toIso8601String(),
            'createdAt' => $license->created_at->toIso8601String(),
        ]);
    }
}
