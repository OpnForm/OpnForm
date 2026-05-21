<?php

it('returns the public plans catalog', function () {
    $response = $this->getJson(route('content.plans'));

    $response->assertSuccessful()
        ->assertJsonPath('tiers.pro.price_monthly', 29)
        ->assertJsonPath('tiers.enterprise.price_yearly_per_month', 220)
        ->assertJsonPath('tiers.self_hosted.price_yearly', 1999)
        ->assertJsonPath('tiers.self_hosted.price_yearly_per_month', 167);
});
