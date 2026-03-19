<?php

use App\Models\User;
use App\Models\Workspace;
use App\Service\Plan\PlanService;
use Illuminate\Support\Str;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->planService = app(PlanService::class);
});

describe('Tier Comparison', function () {
    it('correctly compares tier ordering', function () {
        $planService = app(PlanService::class);

        expect($planService->tierMeetsRequirement('free', 'free'))->toBeTrue();
        expect($planService->tierMeetsRequirement('pro', 'free'))->toBeTrue();
        expect($planService->tierMeetsRequirement('business', 'free'))->toBeTrue();
        expect($planService->tierMeetsRequirement('enterprise', 'free'))->toBeTrue();

        expect($planService->tierMeetsRequirement('free', 'pro'))->toBeFalse();
        expect($planService->tierMeetsRequirement('pro', 'pro'))->toBeTrue();
        expect($planService->tierMeetsRequirement('business', 'pro'))->toBeTrue();
        expect($planService->tierMeetsRequirement('enterprise', 'pro'))->toBeTrue();

        expect($planService->tierMeetsRequirement('free', 'business'))->toBeFalse();
        expect($planService->tierMeetsRequirement('pro', 'business'))->toBeFalse();
        expect($planService->tierMeetsRequirement('business', 'business'))->toBeTrue();
        expect($planService->tierMeetsRequirement('enterprise', 'business'))->toBeTrue();

        expect($planService->tierMeetsRequirement('free', 'enterprise'))->toBeFalse();
        expect($planService->tierMeetsRequirement('pro', 'enterprise'))->toBeFalse();
        expect($planService->tierMeetsRequirement('business', 'enterprise'))->toBeFalse();
        expect($planService->tierMeetsRequirement('enterprise', 'enterprise'))->toBeTrue();
    });

    it('handles unknown tiers as free', function () {
        $planService = app(PlanService::class);

        expect($planService->tierMeetsRequirement('unknown', 'free'))->toBeTrue();
        expect($planService->tierMeetsRequirement('unknown', 'pro'))->toBeFalse();
    });
});

describe('User Tier Detection', function () {
    it('returns free for user without subscription', function () {
        $user = $this->createUser();
        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('free');
    });

    it('returns pro for user with default subscription', function () {
        $user = $this->createProUser();
        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('pro');
    });

    it('returns business for user with business subscription', function () {
        $user = $this->createBusinessUser();
        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('business');
    });

    it('returns enterprise for user with enterprise subscription', function () {
        $user = $this->createEnterpriseUser();
        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('enterprise');
    });

    it('returns enterprise when pricing is disabled', function () {
        config()->set('cashier.key', null);
        $user = $this->createUser();
        $tier = $this->planService->getUserTier($user);
        expect($tier)->toBe('enterprise');
    });
});

describe('Workspace Tier Detection', function () {
    it('returns free for workspace with free user', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);
        $tier = $this->planService->computeWorkspaceTier($workspace);
        expect($tier)->toBe('free');
    });

    it('returns pro for workspace with pro user', function () {
        $user = $this->createProUser();
        $workspace = $this->createUserWorkspace($user);
        $tier = $this->planService->computeWorkspaceTier($workspace);
        expect($tier)->toBe('pro');
    });

    it('returns business for workspace with business user', function () {
        $user = $this->createBusinessUser();
        $workspace = $this->createUserWorkspace($user);
        $tier = $this->planService->computeWorkspaceTier($workspace);
        expect($tier)->toBe('business');
    });

    it('returns enterprise for workspace with enterprise user', function () {
        $user = $this->createEnterpriseUser();
        $workspace = $this->createUserWorkspace($user);
        $tier = $this->planService->computeWorkspaceTier($workspace);
        expect($tier)->toBe('enterprise');
    });

    it('uses highest tier among workspace owners', function () {
        $freeUser = $this->createUser();
        $businessUser = $this->createBusinessUser();

        $workspace = Workspace::create([
            'name' => 'Shared Workspace',
            'icon' => '🏢',
        ]);

        $workspace->users()->sync([
            $freeUser->id => ['role' => 'admin'],
            $businessUser->id => ['role' => 'admin'],
        ]);

        $tier = $this->planService->computeWorkspaceTier($workspace);
        expect($tier)->toBe('business');
    });

    it('respects workspace tier override', function () {
        $user = $this->createUser(); // Free user
        $workspace = $this->createUserWorkspace($user);
        $workspace->update(['plan_overrides' => ['tier' => 'enterprise']]);

        $tier = $this->planService->computeWorkspaceTier($workspace);
        expect($tier)->toBe('enterprise');
    });
});

describe('Feature Access', function () {
    it('allows free tier to access unregistered features', function () {
        expect($this->planService->tierHasFeature('free', 'nonexistent_feature'))->toBeTrue();
    });

    it('gates pro features correctly', function () {
        expect($this->planService->tierHasFeature('free', 'branding.removal'))->toBeFalse();
        expect($this->planService->tierHasFeature('pro', 'branding.removal'))->toBeTrue();
        expect($this->planService->tierHasFeature('business', 'branding.removal'))->toBeTrue();
        expect($this->planService->tierHasFeature('enterprise', 'branding.removal'))->toBeTrue();
    });

    it('gates business features correctly', function () {
        expect($this->planService->tierHasFeature('free', 'enable_partial_submissions'))->toBeFalse();
        expect($this->planService->tierHasFeature('pro', 'enable_partial_submissions'))->toBeFalse();
        expect($this->planService->tierHasFeature('business', 'enable_partial_submissions'))->toBeTrue();
        expect($this->planService->tierHasFeature('enterprise', 'enable_partial_submissions'))->toBeTrue();
    });

    it('gates business features correctly for ip tracking', function () {
        expect($this->planService->tierHasFeature('free', 'enable_ip_tracking'))->toBeFalse();
        expect($this->planService->tierHasFeature('pro', 'enable_ip_tracking'))->toBeFalse();
        expect($this->planService->tierHasFeature('business', 'enable_ip_tracking'))->toBeTrue();
        expect($this->planService->tierHasFeature('enterprise', 'enable_ip_tracking'))->toBeTrue();
    });
});

describe('Workspace Feature Access', function () {
    it('checks workspace feature based on tier', function () {
        $proUser = $this->createProUser();
        $workspace = $this->createUserWorkspace($proUser);

        // Pro feature should be accessible
        expect($this->planService->workspaceHasFeature($workspace, 'branding.removal'))->toBeTrue();

        // Business feature should not be accessible for pro
        expect($this->planService->workspaceHasFeature($workspace, 'enable_partial_submissions'))->toBeFalse();
    });

    it('respects workspace feature overrides', function () {
        $user = $this->createUser(); // Free user
        $workspace = $this->createUserWorkspace($user);

        // Without override, free user doesn't have partial submissions
        expect($this->planService->workspaceHasFeature($workspace, 'enable_partial_submissions'))->toBeFalse();

        // With feature override
        $workspace->update([
            'plan_overrides' => ['features' => ['enable_partial_submissions']],
        ]);
        $workspace->flush();

        expect($this->planService->workspaceHasFeature($workspace, 'enable_partial_submissions'))->toBeTrue();
    });
});

describe('Tier Limits', function () {
    it('returns correct limits per tier', function () {
        expect($this->planService->getTierLimit('free', 'custom_domain_count'))->toBe(0);
        expect($this->planService->getTierLimit('pro', 'custom_domain_count'))->toBe(1);
        expect($this->planService->getTierLimit('business', 'custom_domain_count'))->toBe(10);
        expect($this->planService->getTierLimit('enterprise', 'custom_domain_count'))->toBeNull();
    });

    it('returns correct workspace limits', function () {
        $proUser = $this->createProUser();
        $workspace = $this->createUserWorkspace($proUser);

        $limit = $this->planService->getWorkspaceLimit($workspace, 'custom_domain_count');
        expect($limit)->toBe(1);
    });

    it('respects workspace limit overrides', function () {
        $user = $this->createUser(); // Free user
        $workspace = $this->createUserWorkspace($user);

        // Without override
        $limit = $this->planService->getWorkspaceLimit($workspace, 'custom_domain_count');
        expect($limit)->toBe(0);

        // With override
        $workspace->update([
            'plan_overrides' => ['limits' => ['custom_domain_count' => 5]],
        ]);
        $workspace->flush();

        $limit = $this->planService->getWorkspaceLimit($workspace, 'custom_domain_count');
        expect($limit)->toBe(5);
    });
});

describe('Subscription Mapping', function () {
    it('maps default subscription to pro tier', function () {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);

        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('pro');
    });

    it('maps business subscription name to business tier', function () {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'type' => 'business',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);

        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('business');
    });

    it('maps enterprise subscription name to enterprise tier', function () {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'type' => 'enterprise',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);

        $tier = $this->planService->computeUserTier($user);
        expect($tier)->toBe('enterprise');
    });
});

describe('Display Names', function () {
    it('returns correct display names', function () {
        expect($this->planService->getTierDisplayName('free'))->toBe('Free');
        expect($this->planService->getTierDisplayName('pro'))->toBe('Pro');
        expect($this->planService->getTierDisplayName('business'))->toBe('Business');
        expect($this->planService->getTierDisplayName('enterprise'))->toBe('Enterprise');
    });

    it('capitalizes unknown tiers', function () {
        expect($this->planService->getTierDisplayName('unknown'))->toBe('Unknown');
    });
});

describe('Multi-workspace Authorization', function () {
    it('prevents cross-workspace feature leakage', function () {
        $proUser = $this->createProUser();
        $freeUser = $this->createUser();

        $proWorkspace = $this->createUserWorkspace($proUser);
        $freeWorkspace = $this->createUserWorkspace($freeUser);

        // Pro user's workspace has feature access
        expect($this->planService->workspaceHasFeature($proWorkspace, 'branding.removal'))->toBeTrue();

        // Free user's workspace does NOT, even though a pro user exists elsewhere
        expect($this->planService->workspaceHasFeature($freeWorkspace, 'branding.removal'))->toBeFalse();
    });

    it('grants access when pro user is added to free workspace', function () {
        $proUser = $this->createProUser();
        $freeUser = $this->createUser();

        $workspace = $this->createUserWorkspace($freeUser);

        expect($this->planService->computeWorkspaceTier($workspace))->toBe('free');

        // Add pro user as admin
        $workspace->users()->attach($proUser->id, ['role' => 'admin']);
        $workspace->flush();

        expect($this->planService->computeWorkspaceTier($workspace))->toBe('pro');
    });

    it('downgrades workspace tier when pro owner is removed', function () {
        $proUser = $this->createProUser();
        $freeUser = $this->createUser();

        $workspace = Workspace::create(['name' => 'Shared', 'icon' => '🔗']);
        $workspace->users()->sync([
            $proUser->id => ['role' => 'admin'],
            $freeUser->id => ['role' => 'admin'],
        ]);

        expect($this->planService->computeWorkspaceTier($workspace))->toBe('pro');

        // Remove pro user
        $workspace->users()->detach($proUser->id);
        $workspace->flush();

        expect($this->planService->computeWorkspaceTier($workspace))->toBe('free');
    });
});

describe('Subscription Name Billing', function () {
    it('handles pro subscription type correctly', function () {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'type' => 'pro',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);

        expect($this->planService->computeUserTier($user))->toBe('pro');
    });

    it('selects highest tier when user has multiple subscriptions', function () {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'type' => 'pro',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);
        $user->subscriptions()->create([
            'type' => 'business',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);

        expect($this->planService->computeUserTier($user))->toBe('business');
    });

    it('ignores inactive subscriptions', function () {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'type' => 'business',
            'stripe_id' => Str::random(),
            'stripe_status' => 'canceled',
            'stripe_price' => Str::random(),
            'quantity' => 1,
            'ends_at' => now()->subDay(), // Ended subscription — Cashier's valid() checks ends_at
        ]);

        expect($this->planService->computeUserTier($user))->toBe('free');
    });
});

describe('Override-only Workspace', function () {
    it('grants tier via plan_overrides without any subscription', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);

        // No subscription — free by default
        expect($this->planService->computeWorkspaceTier($workspace))->toBe('free');

        // Admin sets an override
        $workspace->update(['plan_overrides' => ['tier' => 'business']]);
        $workspace->flush();

        expect($this->planService->computeWorkspaceTier($workspace))->toBe('business');
    });

    it('grants specific features via plan_overrides without matching tier', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);

        $workspace->update(['plan_overrides' => ['features' => ['sso.oidc']]]);
        $workspace->flush();

        // Free tier, but OIDC specifically overridden
        expect($this->planService->getWorkspaceTier($workspace))->toBe('free');
        expect($this->planService->workspaceHasFeature($workspace, 'sso.oidc'))->toBeTrue();
        // Other enterprise features NOT granted
        expect($this->planService->workspaceHasFeature($workspace, 'audit_logs'))->toBeFalse();
    });

    it('grants specific limits via plan_overrides', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);

        $workspace->update(['plan_overrides' => ['limits' => ['custom_domain_count' => 50]]]);
        $workspace->flush();

        $limit = $this->planService->getWorkspaceLimit($workspace, 'custom_domain_count');
        expect($limit)->toBe(50);
    });

    it('billingOwners returns all admins for override-only workspace', function () {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);
        $workspace->update(['plan_overrides' => ['tier' => 'pro']]);

        $billingOwners = $workspace->billingOwners();
        expect($billingOwners->count())->toBe(1);
        expect($billingOwners->first()->id)->toBe($user->id);
    });
});

describe('Cache Invalidation', function () {
    it('flushWithOwners clears workspace and owner caches', function () {
        $user = $this->createProUser();
        $workspace = $this->createUserWorkspace($user);

        // Warm the cache
        $this->planService->getWorkspaceTier($workspace);
        expect($workspace->is_pro)->toBeTrue();

        // Flush
        $workspace->flushWithOwners();

        // After flush, cache should be rebuilt on next access
        // (This tests that flush doesn't error and the value is still correct)
        expect($this->planService->computeWorkspaceTier($workspace))->toBe('pro');
    });

    it('user flushCache cascades to workspaces and forms', function () {
        $user = $this->createProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Warm caches
        $workspace->is_pro;
        $user->is_pro;

        // Flush user cache (cascades to workspaces/forms)
        $user->flushCache();

        // Verify no errors and values rebuild correctly
        expect($user->plan_tier)->toBe('pro');
        expect($workspace->plan_tier)->toBe('pro');
    });
});

describe('Form Feature Checks', function () {
    it('checks form_features via tierHasFeature', function () {
        // Form features should be checked by tierHasFeature too
        expect($this->planService->tierHasFeature('free', 'no_branding'))->toBeFalse();
        expect($this->planService->tierHasFeature('pro', 'no_branding'))->toBeTrue();
        expect($this->planService->tierHasFeature('free', 'seo_meta'))->toBeFalse();
        expect($this->planService->tierHasFeature('business', 'seo_meta'))->toBeTrue();
    });

    it('getRequiredTier looks up both features and form_features', function () {
        expect($this->planService->getRequiredTier('custom_smtp'))->toBe('pro');
        expect($this->planService->getRequiredTier('seo_meta'))->toBe('business');
        expect($this->planService->getRequiredTier('no_branding'))->toBe('pro');
        expect($this->planService->getRequiredTier('nonexistent'))->toBeNull();
    });
});
