<?php

namespace Tests\Feature\Admin;

use App\Mail\UserBlockedEmail;
use App\Mail\UserUnblockedEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

function setupUsers()
{
    Config::set('opnform.moderator_emails', ['example@moderator.com']);
    $moderator = User::factory()->create([
        'email' => 'example@moderator.com',
    ]);

    $user = User::factory()->create();

    // Create workspace for user
    $workspace = \App\Models\Workspace::create([
        'name' => 'Test Workspace',
        'icon' => '🧪',
    ]);

    $user->workspaces()->sync([
        $workspace->id => [
            'role' => 'admin',
        ],
    ], false);

    return [$moderator, $user, $workspace];
}

it('can block a user', function () {
    Mail::fake();
    [$moderator, $user, $workspace] = setupUsers();

    $this->actingAs($moderator)
        ->postJson('/moderator/block-user', [
            'user_id' => $user->id,
            'reason' => 'Test block reason',
        ])
        ->assertSuccessful()
        ->assertJson([
            'message' => 'User has been blocked.',
        ]);

    $user->refresh();
    expect($user->is_blocked)->toBeTrue();
    expect($user->blocked_at)->not->toBeNull();
    $lastBlock = $user->getLastBlock();
    expect($lastBlock['reason'])->toBe('Test block reason');
    expect($lastBlock['blocked_by'])->toBe($moderator->id);
    expect($lastBlock['blocked_at'])->not->toBeNull();
    expect($lastBlock['unblocked_at'])->toBeNull();

    Mail::assertSent(UserBlockedEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('can unblock a user', function () {
    Mail::fake();
    [$moderator, $user, $workspace] = setupUsers();
    $user->blockUser('Initial reason', $moderator->id);
    $user->refresh();
    expect($user->is_blocked)->toBeTrue();


    $this->actingAs($moderator)
        ->postJson('/moderator/unblock-user', [
            'user_id' => $user->id,
            'reason' => 'Test unblock reason',
        ])
        ->assertSuccessful()
        ->assertJson([
            'message' => 'User has been unblocked.',
        ]);

    $user->refresh();
    expect($user->is_blocked)->toBeFalse();
    expect($user->blocked_at)->toBeNull();
    $lastBlock = $user->getLastBlock();
    expect($lastBlock['unblock_reason'])->toBe('Test unblock reason');
    expect($lastBlock['unblocked_by'])->toBe($moderator->id);
    expect($lastBlock['unblocked_at'])->not->toBeNull();

    Mail::assertSent(UserUnblockedEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('does not allow a non-moderator to block a user', function () {
    $nonModerator = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($nonModerator)
        ->postJson('/moderator/block-user', [
            'user_id' => $user->id,
            'reason' => 'Attempting to block',
        ])
        ->assertForbidden();
});

it('stores previous form visibility status when blocking a user', function () {
    Mail::fake();
    [$moderator, $user, $workspace] = setupUsers();

    // Create forms with different visibility statuses
    $publicForm = \App\Models\Forms\Form::factory()->create([
        'creator_id' => $user->id,
        'visibility' => 'public',
        'workspace_id' => $workspace->id,
        'properties' => [],
    ]);

    $closedForm = \App\Models\Forms\Form::factory()->create([
        'creator_id' => $user->id,
        'visibility' => 'closed',
        'workspace_id' => $workspace->id,
        'properties' => [],
    ]);

    $draftForm = \App\Models\Forms\Form::factory()->create([
        'creator_id' => $user->id,
        'visibility' => 'draft',
        'workspace_id' => $workspace->id,
        'properties' => [],
    ]);

    $this->actingAs($moderator)
        ->postJson('/moderator/block-user', [
            'user_id' => $user->id,
            'reason' => 'Test block reason',
        ])
        ->assertSuccessful();

    // Refresh forms from database
    $publicForm->refresh();
    $closedForm->refresh();
    $draftForm->refresh();

    // Check that all forms are now draft
    expect($publicForm->visibility)->toBe('draft');
    expect($closedForm->visibility)->toBe('draft');
    expect($draftForm->visibility)->toBe('draft');

    // Check that previous status is stored in tags for non-draft forms
    expect($publicForm->tags)->toContain('previous-status-public');
    expect($closedForm->tags)->toContain('previous-status-closed');
    expect($draftForm->tags)->not->toContain('previous-status-draft');
});

it('restores previous form visibility status when unblocking a user', function () {
    Mail::fake();
    [$moderator, $user, $workspace] = setupUsers();

    // Create forms and block user first
    $publicForm = \App\Models\Forms\Form::factory()->create([
        'creator_id' => $user->id,
        'visibility' => 'public',
        'workspace_id' => $workspace->id,
        'properties' => [],
    ]);

    $closedForm = \App\Models\Forms\Form::factory()->create([
        'creator_id' => $user->id,
        'visibility' => 'closed',
        'workspace_id' => $workspace->id,
        'properties' => [],
    ]);

    // Block the user
    $this->actingAs($moderator)
        ->postJson('/moderator/block-user', [
            'user_id' => $user->id,
            'reason' => 'Test block reason',
        ])
        ->assertSuccessful();

    // Now unblock the user
    $this->actingAs($moderator)
        ->postJson('/moderator/unblock-user', [
            'user_id' => $user->id,
            'reason' => 'Test unblock reason',
        ])
        ->assertSuccessful();

    // Refresh forms from database
    $publicForm->refresh();
    $closedForm->refresh();

    // Check that forms are restored to their previous visibility
    expect($publicForm->visibility)->toBe('public');
    expect($closedForm->visibility)->toBe('closed');

    // Check that previous status tags are removed
    expect($publicForm->tags)->not->toContain('previous-status-public');
    expect($closedForm->tags)->not->toContain('previous-status-closed');
});

it('handles forms with invalid previous status tags gracefully', function () {
    Mail::fake();
    [$moderator, $user, $workspace] = setupUsers();

    // Create a form with an invalid previous status tag
    $form = \App\Models\Forms\Form::factory()->create([
        'creator_id' => $user->id,
        'visibility' => 'draft',
        'tags' => ['previous-status-invalid', 'other-tag'],
        'workspace_id' => $workspace->id,
        'properties' => [],
    ]);

    // Block and then unblock the user
    $this->actingAs($moderator)
        ->postJson('/moderator/block-user', [
            'user_id' => $user->id,
            'reason' => 'Test block reason',
        ])
        ->assertSuccessful();

    $this->actingAs($moderator)
        ->postJson('/moderator/unblock-user', [
            'user_id' => $user->id,
            'reason' => 'Test unblock reason',
        ])
        ->assertSuccessful();

    // Refresh form from database
    $form->refresh();

    // Form should remain as draft (not restored to invalid status)
    expect($form->visibility)->toBe('draft');

    // Invalid previous-status tag should be cleaned up
    expect($form->tags)->not->toContain('previous-status-invalid');

    // Other tags should be preserved
    expect($form->tags)->toContain('other-tag');
});
