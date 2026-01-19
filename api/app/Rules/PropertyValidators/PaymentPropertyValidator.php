<?php

namespace App\Rules\PropertyValidators;

use App\Models\OAuthProvider;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

/**
 * Validates payment block properties.
 * Only runs validation for properties with type === 'payment'.
 */
class PaymentPropertyValidator implements PropertyValidatorInterface
{
    private ?Workspace $workspace;

    /**
     * Cached stripe currency codes (static to persist across requests)
     */
    private static ?array $stripeCurrencyCodes = null;

    public function __construct(?Workspace $workspace = null)
    {
        $this->workspace = $workspace;
    }

    public function validate(array $property, int $index, array $context): array
    {
        $errors = [];
        $type = $property['type'] ?? null;

        // Early bail - skip all processing for non-payment blocks
        if ($type !== 'payment') {
            return $errors;
        }

        // Payment block not allowed if self hosted
        if (config('app.self_hosted')) {
            $errors['type'] = 'Payment block is not allowed on self hosted. Please use our hosted version.';
            return $errors;
        }

        // Only one payment block allowed (check against all properties)
        $properties = $context['properties'] ?? [];
        $paymentBlockCount = 0;
        foreach ($properties as $prop) {
            if (($prop['type'] ?? null) === 'payment') {
                $paymentBlockCount++;
            }
        }

        if ($paymentBlockCount > 1) {
            $errors['type'] = 'Only one payment block allowed';
            return $errors;
        }

        // Amount validation
        if (!isset($property['amount']) || !is_numeric($property['amount']) || $property['amount'] < 1) {
            $errors['amount'] = 'Amount must be a number greater than 1';
            return $errors;
        }

        // Currency validation (lazy-load and cache currency codes)
        if (self::$stripeCurrencyCodes === null) {
            $stripeCurrencies = json_decode(file_get_contents(resource_path('data/stripe_currencies.json')), true);
            self::$stripeCurrencyCodes = array_column($stripeCurrencies, 'code');
        }

        if (!isset($property['currency']) || !in_array(strtoupper($property['currency']), self::$stripeCurrencyCodes)) {
            $errors['currency'] = 'Currency must be a valid currency';
            return $errors;
        }

        // Stripe account validation
        if (!isset($property['stripe_account_id']) || empty($property['stripe_account_id'])) {
            $errors['stripe_account_id'] = 'Stripe account is required';
            return $errors;
        }

        try {
            $provider = OAuthProvider::find($property['stripe_account_id']);
            if ($provider === null) {
                $errors['stripe_account_id'] = 'Failed to validate Stripe account';
                return $errors;
            }

            // Check if the provider is associated with the workspace (if workspace is provided)
            if ($this->workspace && !$this->workspace->hasProvider($provider->id)) {
                Log::error('Attempted to use Stripe account not associated with the workspace', [
                    'stripe_account_id' => $property['stripe_account_id'],
                    'provider_id' => $provider->id,
                    'workspace_id' => $this->workspace->id,
                ]);
                $errors['stripe_account_id'] = 'The configured Stripe account is not associated with this workspace';
                return $errors;
            }
        } catch (\Exception $e) {
            Log::error('Failed to validate Stripe account', [
                'error' => $e->getMessage(),
                'account_id' => $property['stripe_account_id']
            ]);
            $errors['stripe_account_id'] = 'Failed to validate Stripe account';
            return $errors;
        }

        return $errors;
    }
}
