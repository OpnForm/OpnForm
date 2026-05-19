<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\License\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(private LicenseService $licenseService)
    {
    }

    /**
     * Return installed license status.
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorizeLicenseManagement($request);

        $result = $this->licenseService->checkLicense();

        return response()->json([
            'status' => $result->status,
            'features' => $result->features,
            'expires_at' => $result->expiresAt?->format('c'),
        ]);
    }

    /**
     * Activate a license key.
     */
    public function activate(Request $request): JsonResponse
    {
        $this->authorizeLicenseManagement($request);

        $request->validate([
            'license_key' => 'required|string|min:10',
        ]);

        $result = $this->licenseService->storeLicenseKey($request->input('license_key'));

        if (!$result->isActive()) {
            return response()->json([
                'status' => $result->status,
                'features' => null,
                'expires_at' => null,
                'message' => $this->activationFailureMessage($result->status),
            ], 422);
        }

        return response()->json([
            'status' => $result->status,
            'features' => $result->features,
            'expires_at' => $result->expiresAt?->format('c'),
            'message' => 'License activated successfully.',
        ]);
    }

    /**
     * Remove the local license from this self-hosted instance.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $this->authorizeLicenseManagement($request);

        $this->licenseService->removeLicenseKey();

        return response()->json([
            'message' => 'License removed from this instance.',
        ]);
    }

    public function portal(Request $request): JsonResponse
    {
        $this->authorizeLicenseManagement($request);

        try {
            return response()->json([
                'portalUrl' => $this->licenseService->createBillingPortalUrl(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function authorizeLicenseManagement(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->workspaces()->wherePivot('role', User::ROLE_ADMIN)->exists()) {
            abort(403, 'You need to be a workspace admin to manage the instance license.');
        }
    }

    private function activationFailureMessage(string $status): string
    {
        return match ($status) {
            'activation_limit_reached' => 'This license key is already activated on another self-hosted instance. Contact support to reset it.',
            default => 'License key is invalid or expired. Please check your key and try again.',
        };
    }
}
