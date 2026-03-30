<?php

use Illuminate\Testing\Fluent\AssertableJson;
use App\Models\OAuthProvider;

beforeEach(function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Create OAuth provider for Stripe
    $this->stripeAccount = OAuthProvider::factory()->for($user)->create([
        'provider' => 'stripe',
        'provider_user_id' => 'acct_1LhEwZCragdZygxE'
    ]);

    // Create form with payment block
    $this->form = $this->createForm($user, $workspace);
    $this->form->properties = array_merge($this->form->properties, [
        [
            'type' => 'payment',
            'stripe_account_id' => $this->stripeAccount->id,
            'amount' => 99.99,
            'currency' => 'USD'
        ]
    ]);
    $this->form->update();
});

it('can get stripe account for form', function () {
    $this->getJson(route('forms.stripe-connect.get-account', $this->form->slug))
        ->assertSuccessful()
        ->assertJson(function (AssertableJson $json) {
            return $json->has('stripeAccount')
                ->where('stripeAccount', fn ($id) => str_starts_with($id, 'acct_'))
                ->etc();
        });
});

it('cannot create payment intent for non-public form', function () {
    // Update form visibility to private
    $this->form->update(['visibility' => 'private']);

    $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug))
        ->assertStatus(404)
        ->assertJson([
            'message' => 'Form not found.'
        ]);
});

it('cannot create payment intent for form without payment block', function () {
    // Remove payment block entirely
    $properties = collect($this->form->properties)
        ->reject(fn ($block) => $block['type'] === 'payment')
        ->values()
        ->all();

    $this->form->update(['properties' => $properties]);

    $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug))
        ->assertStatus(400)
        ->assertJson([
            'type' => 'error',
            'message' => 'Form does not have a payment block. If you just added a payment block, please save the form and try again.'
        ]);
});

it('cannot create payment intent with invalid stripe account', function () {
    // Update payment block with non-existent stripe account
    $properties = collect($this->form->properties)->map(function ($block) {
        if ($block['type'] === 'payment') {
            $block['stripe_account_id'] = 999999;
        }
        return $block;
    })->all();

    $this->form->update(['properties' => $properties]);

    $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug))
        ->assertStatus(400)
        ->assertJson([
            'message' => 'Failed to find Stripe account'
        ]);
});

describe('payment amount with mentions', function () {
    it('can parse amount from mention field reference', function () {
        // Get the number field ID from form properties
        $numberField = collect($this->form->properties)->firstWhere('type', 'number');

        // Update payment block with mention-based amount
        $mentionHtml = '<span mention="true" mention-field-id="' . $numberField['id'] . '">Number</span>';
        $properties = collect($this->form->properties)->map(function ($block) use ($mentionHtml) {
            if ($block['type'] === 'payment') {
                $block['amount'] = $mentionHtml;
            }
            return $block;
        })->all();

        $this->form->update(['properties' => $properties]);

        // Submit with submission data containing the referenced field value
        // Note: This will fail at Stripe API level (no real credentials), but validates parsing works
        $response = $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug), [
            'submission_data' => [
                $numberField['id'] => 50.00
            ]
        ]);

        // Should not get "Invalid payment amount" error - parsing worked
        $response->assertStatus(400);
        expect($response->json('message'))->not->toBe('Invalid payment amount. Please ensure the amount field has a valid value.');
    });

    it('returns error when mention resolves to invalid amount', function () {
        // Get a text field ID from form properties
        $textField = collect($this->form->properties)->firstWhere('type', 'text');

        // Update payment block with mention-based amount
        $mentionHtml = '<span mention="true" mention-field-id="' . $textField['id'] . '">Text</span>';
        $properties = collect($this->form->properties)->map(function ($block) use ($mentionHtml) {
            if ($block['type'] === 'payment') {
                $block['amount'] = $mentionHtml;
            }
            return $block;
        })->all();

        $this->form->update(['properties' => $properties]);

        // Submit with submission data containing non-numeric value
        $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug), [
            'submission_data' => [
                $textField['id'] => 'not a number'
            ]
        ])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid payment amount. Please ensure the amount field has a valid value.'
            ]);
    });

    it('returns error when mention field is empty', function () {
        // Get the number field ID from form properties
        $numberField = collect($this->form->properties)->firstWhere('type', 'number');

        // Update payment block with mention-based amount
        $mentionHtml = '<span mention="true" mention-field-id="' . $numberField['id'] . '">Number</span>';
        $properties = collect($this->form->properties)->map(function ($block) use ($mentionHtml) {
            if ($block['type'] === 'payment') {
                $block['amount'] = $mentionHtml;
            }
            return $block;
        })->all();

        $this->form->update(['properties' => $properties]);

        // Submit without the referenced field in submission data
        $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug), [
            'submission_data' => []
        ])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid payment amount. Please ensure the amount field has a valid value.'
            ]);
    });

    it('can parse amount with currency symbols and commas', function () {
        $numberField = collect($this->form->properties)->firstWhere('type', 'number');

        $mentionHtml = '<span mention="true" mention-field-id="' . $numberField['id'] . '">Number</span>';
        $properties = collect($this->form->properties)->map(function ($block) use ($mentionHtml) {
            if ($block['type'] === 'payment') {
                $block['amount'] = $mentionHtml;
            }
            return $block;
        })->all();

        $this->form->update(['properties' => $properties]);

        $response = $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug), [
            'submission_data' => [
                $numberField['id'] => '$1,234.50'
            ]
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->not->toBe('Invalid payment amount. Please ensure the amount field has a valid value.');
    });

    it('returns error when mention resolves to negative amount', function () {
        $numberField = collect($this->form->properties)->firstWhere('type', 'number');

        $mentionHtml = '<span mention="true" mention-field-id="' . $numberField['id'] . '">Number</span>';
        $properties = collect($this->form->properties)->map(function ($block) use ($mentionHtml) {
            if ($block['type'] === 'payment') {
                $block['amount'] = $mentionHtml;
            }
            return $block;
        })->all();

        $this->form->update(['properties' => $properties]);

        $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug), [
            'submission_data' => [
                $numberField['id'] => -10
            ]
        ])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid payment amount. Please ensure the amount field has a valid value.'
            ]);
    });
});
