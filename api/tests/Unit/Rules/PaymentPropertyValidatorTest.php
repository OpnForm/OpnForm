<?php

use App\Rules\PropertyValidators\PaymentPropertyValidator;
use App\Models\OAuthProvider;
use App\Models\User;
use App\Models\Workspace;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->users()->attach($this->user->id, ['role' => 'admin']);

    // Create OAuth provider for Stripe
    $this->stripeAccount = OAuthProvider::factory()->for($this->user)->create([
        'provider' => 'stripe',
        'provider_user_id' => 'acct_test123'
    ]);
});

describe('amount validation', function () {
    it('accepts numeric amount greater than or equal to 1', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $property = [
            'type' => 'payment',
            'amount' => 99.99,
            'currency' => 'USD',
            'stripe_account_id' => $this->stripeAccount->id
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toBeEmpty();
    });

    it('rejects numeric amount less than 1', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $property = [
            'type' => 'payment',
            'amount' => 0.5,
            'currency' => 'USD',
            'stripe_account_id' => $this->stripeAccount->id
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toHaveKey('amount');
        expect($errors['amount'])->toBe('Amount must be a number of at least 1 or a field reference');
    });

    it('accepts amount with mention field reference', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $mentionHtml = '<span mention="true" mention-field-id="field-123">Number Field</span>';
        $property = [
            'type' => 'payment',
            'amount' => $mentionHtml,
            'currency' => 'USD',
            'stripe_account_id' => $this->stripeAccount->id
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toBeEmpty();
    });

    it('accepts amount with mention field containing currency symbol', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $mentionHtml = '<span mention="true" mention-field-id="field-123">$50.00</span>';
        $property = [
            'type' => 'payment',
            'amount' => $mentionHtml,
            'currency' => 'USD',
            'stripe_account_id' => $this->stripeAccount->id
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toBeEmpty();
    });

    it('rejects string amount without mention', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $property = [
            'type' => 'payment',
            'amount' => 'invalid amount',
            'currency' => 'USD',
            'stripe_account_id' => $this->stripeAccount->id
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toHaveKey('amount');
        expect($errors['amount'])->toBe('Amount must be a number of at least 1 or a field reference');
    });

    it('rejects missing amount', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $property = [
            'type' => 'payment',
            'currency' => 'USD',
            'stripe_account_id' => $this->stripeAccount->id
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toHaveKey('amount');
    });
});

describe('non-payment blocks', function () {
    it('skips validation for non-payment blocks', function () {
        $validator = new PaymentPropertyValidator($this->workspace);

        $property = [
            'type' => 'text',
            'name' => 'Text Field'
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$property]]);

        expect($errors)->toBeEmpty();
    });
});
