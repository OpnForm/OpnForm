<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Service\Telemetry\TelemetryEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    /**
     * Enable 2FA and return QR code data.
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        // Check if already enabled
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        // Create 2FA secret and QR code
        $secret = $user->createTwoFactorAuth();

        return response()->json([
            'qr_code' => $secret->toQr(),
            'uri' => $secret->toUri(),
            'secret' => $secret->toString(),
        ]);
    }

    /**
     * Confirm 2FA setup with verification code.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Check if 2FA is already enabled (confirmed)
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        // Confirm 2FA (this validates and enables automatically)
        if (!$user->confirmTwoFactorAuth($request->code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code. Please try again.'],
            ]);
        }

        telemetry(TelemetryEvent::TWO_FACTOR_ENABLED);

        return response()->json([
            'message' => 'Two-factor authentication has been enabled successfully.',
            'recovery_codes' => $user->getRecoveryCodes()->toArray(),
        ]);
    }

    /**
     * Disable 2FA (requires 2FA code or recovery code verification).
     */
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:8', // Supports both TOTP (6) and recovery codes (8)
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        // Verify code (TOTP or recovery code - validateTwoFactorCode handles both automatically)
        if (!$user->validateTwoFactorCode($request->code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code. Please try again.'],
            ]);
        }

        // Disable 2FA
        $user->disableTwoFactorAuth();

        return response()->json([
            'message' => 'Two-factor authentication has been disabled successfully.',
        ]);
    }

    /**
     * Get recovery codes.
     * Requires password verification for security.
     */
    public function recoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        return response()->json([
            'recovery_codes' => $user->getRecoveryCodes()->toArray(),
        ]);
    }

    /**
     * Regenerate recovery codes (requires 2FA code only - supports OAuth users without passwords).
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:8', // Supports both TOTP (6) and recovery codes (8)
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        // Verify 2FA code (TOTP or recovery code)
        if (!$user->validateTwoFactorCode($request->code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code. Please try again.'],
            ]);
        }

        $user->generateRecoveryCodes();

        return response()->json([
            'recovery_codes' => $user->getRecoveryCodes()->toArray(),
            'message' => 'Recovery codes have been regenerated. Please save them in a safe place.',
        ]);
    }
}
