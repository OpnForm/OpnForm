<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Service\License\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(private LicenseService $licenseService)
    {
    }

    /**
     * Activate a license key.
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string|min:10',
        ]);

        $result = $this->licenseService->storeLicenseKey($request->input('license_key'));

        if (!$result->isActive()) {
            return response()->json([
                'status' => $result->status,
                'features' => null,
                'expires_at' => null,
                'message' => 'License key is invalid or expired. Please check your key and try again.',
            ], 422);
        }

        return response()->json([
            'status' => $result->status,
            'features' => $result->features,
            'expires_at' => $result->expiresAt?->format('c'),
            'message' => 'License activated successfully.',
        ]);
    }
}
