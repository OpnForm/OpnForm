<?php

use App\Http\Middleware\CustomDomainRestriction;
use App\Models\Forms\Form;
use App\Models\Workspace;
use Illuminate\Http\Request;

it('rejects unknown domains and restores scopes after successful or failed requests', function () {
    config(['custom-domains.enabled' => true]);
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->forceFill(['custom_domains' => ['forms.example.test']])->save();
    $other = $this->createUserWorkspace($this->createUser());
    $this->createForm($user, $workspace);
    $outside = $this->createForm($other->users->first(), $other);
    $middleware = new CustomDomainRestriction();
    $request = Request::create('/forms');
    $request->headers->set('x-custom-domain', 'unknown.example.test');
    expect($middleware->handle($request, fn () => response('unexpected'))->status())->toBe(420);
    $request->headers->set('x-custom-domain', 'forms.example.test');
    $middleware->handle($request, function () use ($outside) {
        expect(Form::whereKey($outside->id)->exists())->toBeFalse();
        return response('ok');
    });
    expect(Form::whereKey($outside->id)->exists())->toBeTrue();
    expect(fn () => $middleware->handle($request, fn () => throw new RuntimeException('test')))->toThrow(RuntimeException::class);
    expect(Form::whereKey($outside->id)->exists())->toBeTrue();
});
