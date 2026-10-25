<?php

use App\Models\User;
use App\Service\CustomDomainOwnershipService;

beforeEach(function () {
    $this->owner = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($this->owner);
    $this->workspace->forceFill(['plan_overrides' => ['limits' => ['custom_domain_count' => 2]]])->save();
    $this->ownership = Mockery::mock(CustomDomainOwnershipService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    app()->instance(CustomDomainOwnershipService::class, $this->ownership);
});

it('retains and removes a previously saved maximum-length domain without requiring a new TXT record', function () {
    $domain = implode('.', [str_repeat('a', 63), str_repeat('b', 63), str_repeat('c', 63), str_repeat('d', 57), 'com']);
    expect(strlen($domain))->toBe(253)->toBeGreaterThan(CustomDomainOwnershipService::MAX_DOMAIN_LENGTH);
    $this->workspace->forceFill(['custom_domains' => [$domain]])->save();
    $this->ownership->shouldNotReceive('txtRecords');

    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => [$domain]])->assertSuccessful();
    expect($this->workspace->fresh()->custom_domains)->toBe([$domain]);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => []])->assertSuccessful();
    expect($this->workspace->fresh()->custom_domains)->toBe([]);
});

it('adds a verified domain while keeping an existing domain and normalizing duplicate input', function () {
    $this->workspace->forceFill(['custom_domains' => ['existing.example.com']])->save();
    $record = $this->ownership->challenge($this->workspace->id, 'new.example.com');
    $this->ownership->shouldReceive('txtRecords')->once()->with($record['host'])
        ->andReturn([['entries' => str_split($record['value'], 16)]]);

    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), [
        'custom_domains' => ['existing.example.com', ' New.Example.com ', 'new.example.com', ''],
    ])->assertSuccessful();

    expect($this->workspace->fresh()->custom_domains)->toBe(['existing.example.com', 'new.example.com']);
});

it('does not remove an existing domain when a replacement fails ownership verification', function () {
    $this->workspace->forceFill(['custom_domains' => ['existing.example.com']])->save();
    $this->ownership->shouldReceive('txtRecords')->once()->andReturn([]);

    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), [
        'custom_domains' => ['replacement.example.com'],
    ])->assertUnprocessable()->assertJsonValidationErrors('custom_domains');

    expect($this->workspace->fresh()->custom_domains)->toBe(['existing.example.com']);
});

it('allows clearing domains through a blank input row without a DNS lookup', function ($blank) {
    $this->workspace->forceFill(['custom_domains' => ['existing.example.com']])->save();
    $this->ownership->shouldNotReceive('txtRecords');

    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), [
        'custom_domains' => [$blank],
    ])->assertSuccessful();

    expect($this->workspace->fresh()->custom_domains)->toBe([]);
})->with(['empty string' => [''], 'converted empty string' => [null]]);

it('checks the domain count before DNS and preserves existing configuration on failure', function () {
    $this->workspace->forceFill(['custom_domains' => ['existing.example.com']])->save();
    $this->ownership->shouldNotReceive('txtRecords');

    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), [
        'custom_domains' => ['existing.example.com', 'second.example.com', 'third.example.com'],
    ])->assertUnprocessable()->assertJsonValidationErrors('custom_domains');

    expect($this->workspace->fresh()->custom_domains)->toBe(['existing.example.com']);
});

it('keeps DNS changes restricted to admins when a viewer is a workspace member', function () {
    $viewer = User::factory()->create();
    $viewer->workspaces()->attach($this->workspace->id, ['role' => User::ROLE_USER]);
    $this->actingAs($viewer, 'api');
    $this->ownership->shouldNotReceive('txtRecords');

    $this->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain=forms.example.com')->assertForbidden();
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => []])->assertForbidden();
});

it('permits a challenge at the TXT hostname length boundary but refuses a new longer domain before DNS', function () {
    $boundary = implode('.', [str_repeat('a', 63), str_repeat('b', 63), str_repeat('c', 63), str_repeat('d', 35), 'com']);
    expect(strlen($boundary))->toBe(CustomDomainOwnershipService::MAX_DOMAIN_LENGTH);
    $this->ownership->shouldNotReceive('txtRecords');
    $this->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain='.$boundary)
        ->assertOk()->assertJsonPath('host', '_opnform-verification.'.$boundary);
    // Keep every label within 63 characters while extending the total hostname.
    $tooLong = str_replace('.'.str_repeat('d', 35).'.', '.'.str_repeat('d', 36).'.', $boundary);
    $this->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain='.$tooLong)->assertUnprocessable();
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => [$tooLong]])->assertUnprocessable();
    expect($this->workspace->fresh()->custom_domains)->toBe([]);
});
