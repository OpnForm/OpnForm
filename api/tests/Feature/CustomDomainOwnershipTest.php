<?php

use App\Service\CustomDomainOwnershipService;

beforeEach(function () {
    $this->owner = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($this->owner);
    $this->ownership = Mockery::mock(CustomDomainOwnershipService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    app()->instance(CustomDomainOwnershipService::class, $this->ownership);
});

it('rejects claiming an unverified domain even when it points at the platform', function () {
    $this->ownership->shouldReceive('txtRecords')->once()->andReturn([]);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertStatus(422)->assertJsonValidationErrors(['custom_domains']);
    expect($this->workspace->fresh()->custom_domains)->toBe([]);
});

it('accepts the exact workspace and domain TXT proof', function () {
    $record = $this->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain=Forms.Example.com')
        ->assertOk()->assertJsonPath('type', 'TXT')->assertJsonPath('host', '_opnform-verification.forms.example.com')->json();
    $this->ownership->shouldReceive('txtRecords')->once()->with($record['host'])->andReturn([['entries' => str_split($record['value'], 30)]]);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => [' Forms.Example.com ']])->assertSuccessful();
    expect($this->workspace->fresh()->custom_domains)->toBe(['forms.example.com']);
});

it('rejects a proof issued for another workspace', function () {
    $otherWorkspace = $this->createUserWorkspace($this->owner);
    $otherProof = $this->ownership->challenge($otherWorkspace->id, 'forms.example.com');
    $this->ownership->shouldReceive('txtRecords')->once()->andReturn([['txt' => $otherProof['value']]]);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertStatus(422);
    expect($this->workspace->fresh()->custom_domains)->toBe([]);
});

it('rejects a proof issued for another domain', function () {
    $otherProof = $this->ownership->challenge($this->workspace->id, 'other.example.com');
    $this->ownership->shouldReceive('txtRecords')->once()->andReturn([['txt' => $otherProof['value']]]);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertStatus(422);
});

it('preserves existing domains without a DNS check and allows removal', function () {
    $this->workspace->forceFill(['custom_domains' => ['forms.example.com']])->save();
    $this->ownership->shouldNotReceive('txtRecords');
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertSuccessful();
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => []])->assertSuccessful();
    expect($this->workspace->fresh()->custom_domains)->toBe([]);
});

it('denies nonmembers access to domain proofs and mutations', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $this->ownership->shouldNotReceive('txtRecords');
    $this->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain=forms.example.com')->assertForbidden();
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => ['forms.example.com']])->assertForbidden();
});

it('validates domain inputs without querying DNS', function () {
    $this->ownership->shouldNotReceive('txtRecords');
    foreach (['https://example.com', '-forms.example.com', str_repeat('a', 64).'.example.com', str_repeat(str_repeat('a', 60).'.', 4).'com', ['invalid']] as $domain) {
        $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), ['custom_domains' => [$domain]])->assertStatus(422);
    }
    $this->getJson(route('open.workspaces.custom-domain-verification', $this->workspace).'?domain[]=example.com')->assertStatus(422);
});

it('issues stable canonical challenges bound to product workspace and domain', function () {
    $proof = $this->ownership->challenge($this->workspace->id, 'Forms.Example.com');
    expect($proof)->toBe($this->ownership->challenge($this->workspace->id, 'forms.example.com'));
    expect($proof['value'])->not->toBe($this->ownership->challenge($this->workspace->id + 1, 'forms.example.com')['value']);
    expect($proof['value'])->not->toBe($this->ownership->challenge($this->workspace->id, 'other.example.com')['value']);
});

it('does not persist partially verified domain lists', function () {
    $verified = $this->ownership->challenge($this->workspace->id, 'first.example.com');
    $this->ownership->shouldReceive('txtRecords')->with($verified['host'])->andReturn([['txt' => $verified['value']]]);
    $this->ownership->shouldReceive('txtRecords')->with('_opnform-verification.second.example.com')->andReturn([]);
    $this->putJson(route('open.workspaces.save-custom-domains', $this->workspace), [
        'custom_domains' => ['first.example.com', 'second.example.com'],
    ])->assertStatus(422);
    expect($this->workspace->fresh()->custom_domains)->toBe([]);
});
