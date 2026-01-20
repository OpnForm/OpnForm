<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Forms\Form;
use App\Models\OAuthProvider;
use App\Http\Requests\Forms\CreatePaymentIntentRequest;
use App\Http\Requests\Forms\GetStripeAccountRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class FormPaymentController extends Controller
{
    /**
     * Get the Stripe Connect Account ID for the form's payment block.
     *
     * Handles two cases:
     * 1. Editor Preview: `oauth_provider_id` is provided in the request by an authenticated user.
     * 2. Public Form/Saved: Loads the ID from the form's saved payment block properties.
     *
     * @param GetStripeAccountRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccount(GetStripeAccountRequest $request, Form $form)
    {
        // Disable payment features on self-hosted instances
        if (config('app.self_hosted')) {
            return $this->error(['message' => 'Payment features are not available in the self-hosted version.'], 403);
        }
        $provider = null;

        // Case 1: Editor Preview (Validated by GetStripeAccountRequest)
        if ($request->isPreview()) {
            $provider = $request->getPreviewProvider();
            if ($provider === null) {
                // Should not happen if validation passes, but defensively handle
                return $this->error(['message' => 'Invalid Stripe account selection.'], 400);
            }
        }
        // Case 2: Public Form / Loading from Saved Form Data
        else {
            $paymentBlock = collect($form->properties)->first(fn ($prop) => $prop['type'] === 'payment');

            if (!$paymentBlock || !isset($paymentBlock['stripe_account_id'])) {
                // Allow preview by returning a specific, non-blocking error message
                if (Auth::check()) { // Only return this hint to authenticated users (in editor)
                    return $this->error(['message' => 'Please save the form and try again.'], 400);
                } else {
                    return $this->error(['message' => 'Payment configuration not found.'], 404); // Public error
                }
            }

            // Load provider based on saved ID
            $provider = OAuthProvider::find($paymentBlock['stripe_account_id']);
            if ($provider === null) {
                return $this->error(['message' => 'Configured Stripe account not found.'], 404);
            }

            // Use the new workspace method to check if the provider belongs to *any* workspace user
            if (!$form->workspace->hasProvider($provider->id)) {
                Log::error('User attempted to use Stripe account not associated with the workspace', [
                    'form_id' => $form->id,
                    'stripe_account_id' => $paymentBlock['stripe_account_id'],
                    'provider_id' => $provider->id,
                    'workspace_id' => $form->workspace_id,
                    'auth_user_id' => Auth::id(), // Keep auth user for logging context
                ]);
                return $this->error(['message' => 'The configured Stripe account is not associated with this workspace.'], 403);
            }
        }

        // Return the Stripe Connect Account ID
        return $this->success(['stripeAccount' => $provider->provider_user_id]);
    }

    public function createIntent(CreatePaymentIntentRequest $request, Form $form)
    {
        // Disable payment features on self-hosted instances
        if (config('app.self_hosted')) {
            return $this->error(['message' => 'Payment features are not available in the self-hosted version.'], 403);
        }

        // Verify form exists and is accessible
        if ($form->workspace === null || $form->visibility !== 'public') {
            Log::warning('Attempt to create payment for invalid form', [
                'form_id' => $form->id
            ]);
            return $this->error(['message' => 'Form not found.'], 404);
        }

        // Get payment block (only one allowed)
        $paymentBlock = collect($form->properties)->first(fn ($prop) => $prop['type'] === 'payment');
        if (!$paymentBlock) {
            Log::warning('Attempt to create payment for form without payment block', [
                'form_id' => $form->id
            ]);
            return $this->error(['message' => 'Form does not have a payment block. If you just added a payment block, please save the form and try again.']);
        }

        // Get provider
        $provider = OAuthProvider::find($paymentBlock['stripe_account_id']);
        if ($provider === null) {
            Log::error('Failed to find Stripe account', [
                'stripe_account_id' => $paymentBlock['stripe_account_id']
            ]);
            return $this->error(['message' => 'Failed to find Stripe account']);
        }

        // Resolve amount from submission data
        $amount = $request->resolveAmount($paymentBlock['amount']);

        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            Log::warning('Invalid payment amount', [
                'form_id' => $form->id,
                'amount' => $amount,
                'original_amount' => $paymentBlock['amount']
            ]);
            return $this->error(['message' => 'Invalid payment amount. Please ensure the amount field has a valid value.']);
        }

        try {
            Log::info('Creating payment intent', [
                'form_id' => $form->id,
                'amount' => $amount,
                'currency' => $paymentBlock['currency']
            ]);

            Stripe::setApiKey(config('cashier.secret'));

            $intent = PaymentIntent::create([
                // Use description from payment block if available, fallback to form title
                'description' => $paymentBlock['description'] ?? ('Form - ' . $form->title),
                'amount' => (int) ($amount * 100),  // Stripe requires amount in cents
                'currency' => strtolower($paymentBlock['currency']),
                'payment_method_types' => ['card'],
                'metadata' => [
                    'form_id' => $form->id,
                    'workspace_id' => $form->workspace_id,
                    'form_name' => $form->title,
                ],
            ], [
                'stripe_account' => $provider->provider_user_id
            ]);

            Log::info('Payment intent created', [
                'form_id' => $form->id,
                'intent' => $intent
            ]);

            if ($intent->id) {
                return $this->success([
                    'intent' => ['id' => $intent->id, 'secret' => $intent->client_secret]
                ]);
            } else {
                return $this->error(['message' => 'Failed to create payment intent']);
            }
        } catch (\Stripe\Exception\CardException $e) {
            Log::warning('Failed to create payment intent', [
                'form_id' => $form->id,
                'message' => $e->getMessage()
            ]);
            return $this->error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Failed to create payment intent', [
                'form_id' => $form->id,
                'error' => $e->getMessage()
            ]);
            return $this->error(['message' => 'Failed to initialize payment.']);
        }
    }
}
