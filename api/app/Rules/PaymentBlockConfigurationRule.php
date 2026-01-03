<?php

namespace App\Rules;

use App\Models\OAuthProvider;
use App\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\ValidationRule;

class PaymentBlockConfigurationRule implements ValidationRule
{
    protected array $properties;
    protected array $field;
    protected ?Workspace $workspace;

    /**
     * Cached stripe currency codes (static to persist across instances)
     */
    protected static ?array $stripeCurrencyCodes = null;

    public function __construct(array $properties, ?Workspace $workspace = null)
    {
        $this->properties = $properties;
        $this->workspace = $workspace;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Early bail - $value is the type, skip all processing for non-payment blocks
        if ($value !== 'payment') {
            return;
        }

        // Set the field only for payment blocks
        $fieldIndex = explode('.', $attribute)[1];
        $this->field = $this->properties[$fieldIndex];

        // Payment block not allowed if self hosted
        if (config('app.self_hosted')) {
            $fail('Payment block is not allowed on self hosted. Please use our hosted version.');
            return;
        }

        // Only one payment block allowed
        $paymentBlocks = collect($this->properties)
            ->filter(fn ($prop) => $prop['type'] === 'payment')
            ->count();

        if ($paymentBlocks > 1) {
            $fail('Only one payment block allowed');
            return;
        }

        // Amount validation
        if (!isset($this->field['amount']) || !is_numeric($this->field['amount']) || $this->field['amount'] < 1) {
            $fail('Amount must be a number greater than 1');
            return;
        }

        // Currency validation (lazy-load and cache currency codes)
        if (self::$stripeCurrencyCodes === null) {
            $stripeCurrencies = json_decode(file_get_contents(resource_path('data/stripe_currencies.json')), true);
            self::$stripeCurrencyCodes = array_column($stripeCurrencies, 'code');
        }

        if (!isset($this->field['currency']) || !in_array(strtoupper($this->field['currency']), self::$stripeCurrencyCodes)) {
            $fail('Currency must be a valid currency');
            return;
        }

        // Stripe account validation
        if (!isset($this->field['stripe_account_id']) || empty($this->field['stripe_account_id'])) {
            $fail('Stripe account is required');
            return;
        }
        try {
            $provider = OAuthProvider::find($this->field['stripe_account_id']);
            if ($provider === null) {
                $fail('Failed to validate Stripe account');
                return;
            }

            // Check if the provider is associated with the workspace (if workspace is provided)
            if ($this->workspace && !$this->workspace->hasProvider($provider->id)) {
                Log::error('Attempted to use Stripe account not associated with the workspace', [
                    'stripe_account_id' => $this->field['stripe_account_id'],
                    'provider_id' => $provider->id,
                    'workspace_id' => $this->workspace->id,
                ]);
                $fail('The configured Stripe account is not associated with this workspace');
                return;
            }
        } catch (\Exception $e) {
            Log::error('Failed to validate Stripe account', [
                'error' => $e->getMessage(),
                'account_id' => $this->field['stripe_account_id']
            ]);
            $fail('Failed to validate Stripe account');
            return;
        }
    }
}
