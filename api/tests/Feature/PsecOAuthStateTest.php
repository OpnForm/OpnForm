<?php

use App\Service\OAuth\OAuthContextService;
use App\Service\OAuth\OAuthFlowOrchestrator;
use App\Service\OAuth\OAuthUserDataService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->context = app(OAuthContextService::class);
    $this->userData = Mockery::mock(OAuthUserDataService::class);
    app()->instance(OAuthUserDataService::class, $this->userData);
});

it('rejects missing or wrong-provider states before contacting the provider', function () {
    $this->userData->shouldNotReceive('extractFromRedirect');
    $state = $this->context->storeContext(['provider' => 'stripe', 'intent' => 'auth', 'user_id' => null]);
    $flow = app(OAuthFlowOrchestrator::class);
    expect($flow->processCallback('google', ['state' => $state])->status())->toBe(419)
        ->and($flow->processCallback('google', ['state' => 'unknown'])->status())->toBe(419);
});

it('binds integration states to the initiating user', function () {
    $owner = $this->actingAsUser();
    $state = $this->context->storeContext(['provider' => 'google', 'intent' => 'integration', 'user_id' => $owner->id]);
    Auth::setUser($this->createUser());
    $this->userData->shouldNotReceive('extractFromRedirect');
    expect(app(OAuthFlowOrchestrator::class)->processCallback('google', ['state' => $state])->status())->toBe(419);
});

it('consumes a valid state even if the provider exchange fails', function () {
    $state = $this->context->storeContext(['provider' => 'google', 'intent' => 'auth', 'user_id' => Auth::id()]);
    $this->userData->shouldReceive('extractFromRedirect')->once()->andThrow(new RuntimeException('Provider unavailable'));
    $flow = app(OAuthFlowOrchestrator::class);
    expect(fn () => $flow->processCallback('google', ['state' => $state]))->toThrow(RuntimeException::class);
    expect($flow->processCallback('google', ['state' => $state])->status())->toBe(419);
});

it('keeps attribution available only for the consuming request', function () {
    $state = $this->context->storeContext(['intent' => 'auth', 'utm_data' => ['source' => 'test']]);
    request()->merge(['state' => $state]);
    expect($this->context->consumeContext($state))->not->toBeNull()
        ->and($this->context->getUtmData())->toBe(['source' => 'test'])
        ->and(Cache::get('oauth-context:state:' . $state))->toBeNull()
        ->and($this->context->consumeContext($state))->toBeNull();
    $this->context->clearContext($state);
    expect($this->context->getContext($state))->toBeNull();
});
