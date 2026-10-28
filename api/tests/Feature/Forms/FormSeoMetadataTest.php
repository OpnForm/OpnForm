<?php

use App\Http\Resources\FormResource;
use Illuminate\Support\Str;

it('publishes saved SEO metadata according to the workspace plan', function (string $tier, ?string $customDomain, bool $canPublishSeo) {
    $user = $this->createUser();
    if ($tier !== 'free') {
        $user->subscriptions()->create([
            'type' => $tier,
            'stripe_id' => (string) Str::uuid(),
            'stripe_status' => 'active',
            'stripe_price' => (string) Str::uuid(),
            'quantity' => 1,
        ]);
    }

    $this->actingAsUser($user);
    $workspace = $this->createUserWorkspace($user);
    if ($customDomain) {
        $workspace->update(['custom_domains' => [$customDomain]]);
    }
    $form = $this->createForm($user, $workspace, [
        'visibility' => 'public',
        'custom_domain' => $customDomain,
    ]);
    $seoMeta = [
        'page_title' => 'Contact our team',
        'page_description' => 'Send us your questions.',
        'page_thumbnail' => 'https://example.com/form-preview.png',
        'page_favicon' => 'https://example.com/favicon.png',
    ];
    $payload = (new FormResource($form))->toArray(request());
    $payload['seo_meta'] = $seoMeta;

    $this->putJson(route('open.forms.update', $form->id), $payload)
        ->assertSuccessful()
        ->assertJsonPath('form.seo_meta', $seoMeta);

    $this->actingAsGuest();
    $this->getJson(route('forms.show', $form->slug))
        ->assertSuccessful()
        ->assertJsonPath('seo_meta', $canPublishSeo ? $seoMeta : []);

    // Public filtering must never erase the owner's saved metadata.
    expect((array) $form->fresh()->seo_meta)->toBe($seoMeta);
})->with([
    'free' => ['free', null, false],
    'pro on OpnForm' => ['pro', null, true],
    'pro on a custom domain' => ['pro', 'forms.example.com', true],
    'business on OpnForm' => ['business', null, true],
    'business on a custom domain' => ['business', 'forms.example.com', true],
    'enterprise on OpnForm' => ['enterprise', null, true],
    'enterprise on a custom domain' => ['enterprise', 'forms.example.com', true],
]);
