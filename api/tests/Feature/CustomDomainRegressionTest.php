<?php

use App\Models\User;
use App\Service\CustomDomainOwnershipService;

beforeEach(function () {
    $this->owner = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($this->owner);
    $this->ownership = Mockery::mock(CustomDomainOwnershipService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    app()->instance(CustomDomainOwnershipService::class, $this->ownership);
});

it('keeps domain reservations global when a request comes from a custom domain', function () {
    config(['custom-domains.enabled' => true]);
    $this->workspace->forceFill(['custom_domains' => ['alpha.example.com']])->save();
    $otherWorkspace = $this->createUserWorkspace($this->owner);
    $otherWorkspace->forceFill(['custom_domains' => ['forms.example.com']])->save();
    $proof = $this->ownership->challenge($this->workspace->id, 'forms.example.com');
    $this->ownership->shouldReceive('txtRecords')->andReturn([['txt' => $proof['value']]]);

    $scopeWasApplied = false;
    $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(function ($request, $next) use ($otherWorkspace, &$scopeWasApplied) {
        $scopeWasApplied = ! $otherWorkspace->newQuery()->whereKey($otherWorkspace->id)->exists();

        return $next($request);
    });

    $this->withHeader('x-custom-domain', 'alpha.example.com')
        ->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])
        ->assertStatus(422)->assertJsonValidationErrors('custom_domains');

    expect($scopeWasApplied)->toBeTrue();
    expect($otherWorkspace->newQuery()->whereKey($otherWorkspace->id)->exists())->toBeTrue();
    expect($this->workspace->newQueryWithoutScopes()->findOrFail($this->workspace->id)->custom_domains)->toBe(['alpha.example.com']);
    expect($otherWorkspace->newQueryWithoutScopes()->findOrFail($otherWorkspace->id)->custom_domains)->toBe(['forms.example.com']);
});

it('allows the full domain verification flow while impersonating an authorized owner', function () {
    $token = auth('api')->claims(['impersonating' => true])->login($this->owner);
    $proof = $this->ownership->challenge($this->workspace->id, 'forms.example.com');
    $this->ownership->shouldReceive('txtRecords')->andReturn([['txt' => $proof['value']]]);

    $this->withToken($token)->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain=forms.example.com')
        ->assertOk()->assertJsonPath('value', $proof['value']);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertSuccessful();
});

it('keeps workspace authorization during impersonated domain verification', function () {
    $nonmember = User::factory()->create();
    $token = auth('api')->claims(['impersonating' => true])->login($nonmember);
    $this->ownership->shouldNotReceive('txtRecords');

    $this->withToken($token)->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain=forms.example.com')->assertForbidden();
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertForbidden();
});
