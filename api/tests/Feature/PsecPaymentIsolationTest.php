<?php

use App\Models\OAuthProvider;
use App\Models\User;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

beforeEach(function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $this->form = $this->createForm($user, $workspace);
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldNotReceive('request');
    ApiRequestor::setHttpClient($http);
});

afterEach(function () {
    ApiRequestor::setHttpClient(new \Stripe\HttpClient\CurlClient());
});

it('rejects a payment provider from another workspace before calling Stripe', function () {
    $provider = OAuthProvider::factory()->for(User::factory()->create())->create(['provider' => 'stripe']);
    $this->form->update(['properties' => [['type' => 'payment', 'stripe_account_id' => $provider->id, 'amount' => 10, 'currency' => 'USD']]]);
    $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug))->assertForbidden();
});

it('rejects a non-Stripe provider belonging to a workspace member', function () {
    $provider = OAuthProvider::factory()->for($this->form->creator)->create(['provider' => 'google']);
    $this->form->update(['properties' => [['type' => 'payment', 'stripe_account_id' => $provider->id, 'amount' => 10, 'currency' => 'USD']]]);
    $this->postJson(route('forms.stripe-connect.create-intent', $this->form->slug))->assertForbidden();
});
