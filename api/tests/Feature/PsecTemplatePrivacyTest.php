<?php

use App\Models\Template;

it('does not disclose private template slugs to guests or other users', function () {
    $this->owner = $this->actingAsUser();
    $template = Template::create(['name' => 'Private', 'description' => '', 'slug' => 'private-template', 'structure' => [], 'publicly_listed' => false]);
    $template->creator_id = $this->owner->id;
    $template->save();
    $this->actingAsGuest();
    $this->getJson(route('templates.show', $template->slug))->assertNotFound();
    $this->actingAsUser($this->createUser());
    $this->getJson(route('templates.show', $template->slug))->assertNotFound();
    $this->actingAsGuest();
    $this->actingAsUser($this->owner);
    $this->getJson(route('templates.show', $template->slug))->assertOk();
});
