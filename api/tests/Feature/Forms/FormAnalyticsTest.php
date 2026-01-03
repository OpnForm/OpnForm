<?php

use App\Http\Resources\FormResource;

/**
 * Tests for form analytics feature
 */
describe('Form Analytics Validation', function () {
    it('can create form with valid meta pixel analytics', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => '1234567890123456',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $response = $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form created.',
            ]);

        $createdForm = \App\Models\Forms\Form::find($response->json('form.id'));
        expect($createdForm->analytics)->toBeArray();
        expect($createdForm->analytics['provider'])->toBe('meta_pixel');
        expect($createdForm->analytics['tracking_id'])->toBe('1234567890123456');
    });

    it('can create form with valid google analytics', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'google_analytics',
                'tracking_id' => 'G-ABC123XYZ9',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $response = $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form created.',
            ]);

        $createdForm = \App\Models\Forms\Form::find($response->json('form.id'));
        expect($createdForm->analytics['provider'])->toBe('google_analytics');
        expect($createdForm->analytics['tracking_id'])->toBe('G-ABC123XYZ9');
    });

    it('can create form with valid gtm analytics', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'gtm',
                'tracking_id' => 'GTM-ABC1234',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $response = $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form created.',
            ]);

        $createdForm = \App\Models\Forms\Form::find($response->json('form.id'));
        expect($createdForm->analytics['provider'])->toBe('gtm');
        expect($createdForm->analytics['tracking_id'])->toBe('GTM-ABC1234');
    });

    it('rejects tracking id with special characters', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => '<script>alert("xss")</script>',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['analytics.tracking_id']);
    });

    it('rejects tracking id with quotes', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'google_analytics',
                'tracking_id' => 'G-123"onload="alert(1)',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['analytics.tracking_id']);
    });

    it('rejects tracking id with spaces', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'gtm',
                'tracking_id' => 'GTM ABC1234',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['analytics.tracking_id']);
    });

    it('rejects tracking id exceeding max length', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => str_repeat('A', 51), // 51 characters, max is 50
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['analytics.tracking_id']);
    });

    it('rejects invalid analytics provider', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'invalid_provider',
                'tracking_id' => '12345',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['analytics.provider']);
    });

    it('requires tracking id when provider is set', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => null,
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['analytics.tracking_id']);
    });

    it('allows empty analytics configuration', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form created.',
            ]);
    });

    it('allows null analytics configuration', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => null,
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form created.',
            ]);
    });

    it('allows tracking id with dots and dashes', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'google_analytics',
                'tracking_id' => 'G-ABC_123.XYZ-789',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        $response = $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful();

        $createdForm = \App\Models\Forms\Form::find($response->json('form.id'));
        expect($createdForm->analytics['tracking_id'])->toBe('G-ABC_123.XYZ-789');
    });

    it('can update form analytics', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => '1234567890',
            ],
        ]);

        $form->analytics = [
            'provider' => 'google_analytics',
            'tracking_id' => 'G-NEWTRACKID',
        ];
        $formData = (new FormResource($form))->toArray(request());

        $this->putJson(route('open.forms.update', $form->id), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form updated.',
            ]);

        $form->refresh();
        expect($form->analytics['provider'])->toBe('google_analytics');
        expect($form->analytics['tracking_id'])->toBe('G-NEWTRACKID');
    });
});

describe('Form Analytics Pro Feature Gating', function () {
    it('warns about analytics cleaning for non-pro users on form create', function () {
        $user = $this->actingAsUser(); // Non-pro user
        $workspace = $this->createUserWorkspace($user);
        $form = $this->makeForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => '1234567890123456',
            ],
        ]);
        $formData = (new FormResource($form))->toArray(request());

        // Form is created with analytics (stored in DB), but response indicates cleaning will happen when shared
        $response = $this->postJson(route('open.forms.store', $formData))
            ->assertSuccessful();

        // Response message should indicate Pro features will be disabled when sharing
        expect($response->json('message'))->toContain('Pro features you used will be disabled');
    });

    it('cleans analytics when public form is fetched for non-pro user', function () {
        $user = $this->actingAsUser(); // Non-pro user
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => '1234567890123456',
            ],
            'visibility' => 'public', // Must be public to be fetched via public endpoint
        ]);

        // Logout to access as guest (public form endpoint)
        $this->actingAsGuest();

        // Public endpoint cleans pro features
        $response = $this->getJson(route('forms.show', $form->slug))
            ->assertSuccessful();

        // Analytics should be cleaned (empty) in response
        expect($response->json('analytics'))->toBeEmpty();
    });

    it('preserves analytics when public form is fetched for pro user', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'analytics' => [
                'provider' => 'gtm',
                'tracking_id' => 'GTM-PROACC01',
            ],
            'visibility' => 'public',
        ]);

        // Logout to access as guest (public form endpoint)
        $this->actingAsGuest();

        // Public endpoint should preserve analytics for pro workspace
        $response = $this->getJson(route('forms.show', $form->slug))
            ->assertSuccessful();

        expect($response->json('analytics'))->toBeArray();
        expect($response->json('analytics.provider'))->toBe('gtm');
        expect($response->json('analytics.tracking_id'))->toBe('GTM-PROACC01');
    });

    it('preserves analytics in database for non-pro users', function () {
        $user = $this->actingAsUser(); // Non-pro user
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'analytics' => [
                'provider' => 'meta_pixel',
                'tracking_id' => '1234567890123456',
            ],
        ]);

        // Re-fetch from DB to verify it's persisted (cleaning only affects response, not storage)
        $form->refresh();
        expect($form->analytics)->toBeArray();
        expect($form->analytics['provider'])->toBe('meta_pixel');
        expect($form->analytics['tracking_id'])->toBe('1234567890123456');
    });
});
