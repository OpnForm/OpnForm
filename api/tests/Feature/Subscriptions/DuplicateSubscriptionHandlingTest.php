<?php

use App\Http\Middleware\IsNotSubscribed;
use App\Http\Middleware\IsSubscribed;
use App\Service\Billing\BillingStateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

function createDefaultSubscriptionForTest($user, string $status)
{
    return $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => $status,
        'stripe_price' => (string) Str::uuid(),
        'quantity' => 1,
    ]);
}

it('keeps user subscribed when newest subscription is canceled but an older one is active', function () {
    $user = $this->createUser();
    createDefaultSubscriptionForTest($user, 'active');
    createDefaultSubscriptionForTest($user, 'canceled');

    $user = $user->fresh();

    expect($user->hasActivePaidSubscription())->toBeTrue();
    expect($user->is_subscribed)->toBeTrue();
});

it('selects newest active trialing subscription while ignoring canceled rows', function () {
    $user = $this->createUser();

    $olderActive = createDefaultSubscriptionForTest($user, 'active');
    $newerActive = createDefaultSubscriptionForTest($user, 'active');
    createDefaultSubscriptionForTest($user, 'canceled');

    $selected = $user->fresh()->activePaidSubscription();

    expect($selected)->not->toBeNull();
    expect($selected->id)->toBe($newerActive->id);
    expect($selected->id)->not->toBe($olderActive->id);
});

it('treats trialing subscriptions as active for entitlement checks', function () {
    $user = $this->createUser();
    createDefaultSubscriptionForTest($user, 'trialing');

    $user = $user->fresh();

    expect($user->hasActivePaidSubscription())->toBeTrue();
    expect($user->is_subscribed)->toBeTrue();
});

it('does not treat canceled or past due subscriptions as active', function () {
    $user = $this->createUser();
    createDefaultSubscriptionForTest($user, 'canceled');
    createDefaultSubscriptionForTest($user, 'past_due');

    $user = $user->fresh();

    expect($user->hasActivePaidSubscription())->toBeFalse();
    expect($user->activePaidSubscription())->toBeNull();
});

it('ignores non-plan active subscriptions for paid entitlement checks', function () {
    $user = $this->createUser();
    $user->subscriptions()->create([
        'type' => 'extra_user',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => 'active',
        'stripe_price' => (string) Str::uuid(),
        'quantity' => 1,
    ]);

    $user = $user->fresh();

    expect($user->hasActivePaidSubscription())->toBeFalse();
    expect($user->activePaidSubscription())->toBeNull();
});

it('allows subscribed middleware access when at least one active subscription exists', function () {
    $user = $this->createUser();
    createDefaultSubscriptionForTest($user, 'active');
    createDefaultSubscriptionForTest($user, 'canceled');

    $request = Request::create('/subscription-test', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setUserResolver(fn () => $user->fresh());

    $response = (new IsSubscribed(app(BillingStateResolver::class)))->handle($request, fn () => response()->json(['ok' => true]));

    expect($response->status())->toBe(200);
    expect($response->getData(true)['ok'])->toBeTrue();
});

it('blocks not subscribed middleware when at least one active subscription exists', function () {
    $user = $this->createUser();
    createDefaultSubscriptionForTest($user, 'active');
    createDefaultSubscriptionForTest($user, 'canceled');

    $request = Request::create('/subscription-test', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setUserResolver(fn () => $user->fresh());

    $response = (new IsNotSubscribed(app(BillingStateResolver::class)))->handle($request, fn () => response()->json(['ok' => true]));

    expect($response->status())->toBe(401);
});

it('treats business subscriptions as active for middleware and checkout blocking', function () {
    $user = $this->createUser();
    $user->subscriptions()->create([
        'type' => 'business',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => 'active',
        'stripe_price' => (string) Str::uuid(),
        'quantity' => 1,
    ]);

    $request = Request::create('/subscription-test', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setUserResolver(fn () => $user->fresh());

    $response = (new IsNotSubscribed(app(BillingStateResolver::class)))->handle($request, fn () => response()->json(['ok' => true]));

    expect($response->status())->toBe(401);
});

it('blocks checkout when a user already has an active subscription', function () {
    $user = $this->createUser();
    createDefaultSubscriptionForTest($user, 'active');
    $this->actingAsUser($user);

    $response = $this->getJson(route('subscription.checkout', [
        'subscription' => 'pro',
        'plan' => 'monthly',
    ]));

    $response->assertStatus(400)->assertJson([
        'type' => 'error',
        'message' => 'You already have an active subscription.',
    ]);
});

it('blocks checkout while another checkout creation is already in progress', function () {
    $user = $this->createUser();
    $this->actingAsUser($user);

    $lock = Cache::lock(
        'subscription_checkout:' . $user->id . ':pro',
        15
    );
    expect($lock->get())->toBeTrue();

    try {
        $response = $this->getJson(route('subscription.checkout', [
            'subscription' => 'pro',
            'plan' => 'monthly',
        ]));

        $response->assertStatus(429)->assertJson([
            'type' => 'error',
            'message' => 'A checkout session is already being created. Please retry in a few seconds.',
        ]);
    } finally {
        $lock->release();
    }
});

it('returns explicit error when upgrading yearly without an active subscription row', function () {
    $user = $this->createUser();
    config(['opnform.extra_pro_users_emails' => [$user->email]]);
    $workspace = $this->createUserWorkspace($user);
    $this->actingAsUser($user);

    $response = $this->postJson(route('subscription.upgrade-to-yearly'), [
        'workspace_id' => $workspace->id,
    ]);

    $response->assertStatus(400)->assertJson([
        'type' => 'error',
        'message' => 'No active subscription found for this user.',
    ]);
});
