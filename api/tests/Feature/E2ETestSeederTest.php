<?php

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use Database\Seeders\E2ETestSeeder;

it('creates the Codex admin with realistic forms and submissions', function () {
    config(['opnform.admin_emails' => ['e2e@example.test']]);

    $this->seed(E2ETestSeeder::class);

    $user = User::query()->where('email', 'e2e@example.test')->firstOrFail();

    expect($user->admin)->toBeTrue()
        ->and($user->workspaces()->wherePivot('role', User::ROLE_ADMIN)->exists())->toBeTrue()
        ->and(Form::query()->pluck('title')->all())->toEqualCanonicalizing([
            'Product feedback',
            'Book a product demo',
            'Website redesign intake',
        ])
        ->and(Form::query()->where('visibility', 'public')->count())->toBe(2)
        ->and(FormSubmission::query()->count())->toBe(5);
});
