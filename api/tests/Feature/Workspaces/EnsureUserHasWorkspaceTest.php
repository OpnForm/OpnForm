<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Billing\RemoveWorkspaceGuests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('detaching user from last workspace creates a default one via controller', function () {
    // Arrange: User with one workspace, acting user is admin
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $userToDetach = User::factory()->create();
    $workspace->users()->attach($admin, ['role' => 'admin']);
    $workspace->users()->attach($userToDetach, ['role' => 'member']);

    expect($userToDetach->workspaces()->count())->toBe(1);

    // Act: Admin detaches the user
    $this->actingAs($admin)
        ->deleteJson(route('open.workspaces.users.remove', ['workspace' => $workspace, 'user' => $userToDetach]))
        ->assertOk();

    // Assert: User now has 1 workspace, named 'My Workspace'
    $userToDetach->refresh();
    expect($userToDetach->workspaces()->count())->toBe(1);

    // Check workspace name
    $newWorkspace = $userToDetach->workspaces()->first();
    expect($newWorkspace->name)->toBe('My Workspace');

    // Check role by querying the pivot table directly
    $pivotData = DB::table('user_workspace')
        ->where('user_id', $userToDetach->id)
        ->where('workspace_id', $newWorkspace->id)
        ->first();
    expect($pivotData->role)->toBe('admin');
});

test('detaching user from one workspace when they have others does not create default one via controller', function () {
    // Arrange: User with two workspaces, acting user is admin
    $admin = User::factory()->create();
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();
    $userToDetach = User::factory()->create();

    $workspace1->users()->attach($admin, ['role' => 'admin']);
    $workspace1->users()->attach($userToDetach, ['role' => 'member']);
    $workspace2->users()->attach($userToDetach, ['role' => 'member']); // User belongs to a second workspace

    expect($userToDetach->workspaces()->count())->toBe(2);
    $initialWorkspaceIds = $userToDetach->workspaces()->pluck('workspaces.id')->toArray();

    // Act: Admin detaches the user from workspace1
    $this->actingAs($admin)
        ->deleteJson(route('open.workspaces.users.remove', ['workspace' => $workspace1, 'user' => $userToDetach]))
        ->assertOk();

    // Assert: User now has 1 workspace (workspace2), no default created
    $userToDetach->refresh();
    expect($userToDetach->workspaces()->count())->toBe(1);
    $remainingWorkspace = $userToDetach->workspaces()->first();
    expect($remainingWorkspace->id)->toBe($workspace2->id);
    expect($userToDetach->workspaces()->where('name', 'My Workspace')->exists())->toBeFalse();
    expect($userToDetach->workspaces()->pluck('workspaces.id')->toArray())->not->toContain($workspace1->id);
});

test('user leaving last workspace creates a default one', function () {
    // Arrange: User with one workspace
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $workspace->users()->attach($user, ['role' => 'member']); // Can be any role to leave

    expect($user->workspaces()->count())->toBe(1);

    // Act: User leaves the workspace
    $this->actingAs($user)
        ->postJson(route('open.workspaces.leave', ['workspace' => $workspace]))
        ->assertOk();

    // Assert: User now has 1 workspace, named 'My Workspace'
    $user->refresh();
    expect($user->workspaces()->count())->toBe(1);

    // Check workspace name
    $newWorkspace = $user->workspaces()->first();
    expect($newWorkspace->name)->toBe('My Workspace');

    // Check role by querying the pivot table directly
    $pivotData = DB::table('user_workspace')
        ->where('user_id', $user->id)
        ->where('workspace_id', $newWorkspace->id)
        ->first();
    expect($pivotData->role)->toBe('admin');
});

test('user leaving one workspace when they have others does not create default one', function () {
    // Arrange: User with two workspaces
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();
    $user = User::factory()->create();

    $workspace1->users()->attach($user, ['role' => 'member']);
    $workspace2->users()->attach($user, ['role' => 'member']);

    expect($user->workspaces()->count())->toBe(2);

    // Act: User leaves workspace1
    $this->actingAs($user)
        ->postJson(route('open.workspaces.leave', ['workspace' => $workspace1]))
        ->assertOk();

    // Assert: User now has 1 workspace (workspace2), no default created
    $user->refresh();
    expect($user->workspaces()->count())->toBe(1);
    $remainingWorkspace = $user->workspaces()->first();
    expect($remainingWorkspace->id)->toBe($workspace2->id);
    expect($user->workspaces()->where('name', 'My Workspace')->exists())->toBeFalse();
});

// Tests for RemoveWorkspaceGuests job
test('removing guest from last workspace via job creates a default one', function () {
    // Arrange: Guest user with one workspace (the one they'll be removed from), Admin user
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $guest = User::factory()->create();
    $workspace->users()->attach($admin, ['role' => 'admin']);
    $workspace->users()->attach($guest, ['role' => 'guest']);

    expect($guest->workspaces()->count())->toBe(1);

    // Act: Dispatch the job to remove guests from the workspace
    // Pass the admin user as required by the job constructor
    RemoveWorkspaceGuests::dispatchSync($admin, $workspace); // Fixed: Job expects User first, then additional param

    // Assert: Guest user now has 1 workspace, named 'My Workspace'
    $guest->refresh();
    expect($guest->workspaces()->count())->toBe(1);

    // Check workspace name
    $newWorkspace = $guest->workspaces()->first();
    expect($newWorkspace->name)->toBe('My Workspace');

    // Check role by querying the pivot table directly
    $pivotData = DB::table('user_workspace')
        ->where('user_id', $guest->id)
        ->where('workspace_id', $newWorkspace->id)
        ->first();
    expect($pivotData->role)->toBe('admin');
});

test('removing guest from one workspace when they have others via job does not create default one', function () {
    // Arrange: Guest user with two workspaces, Admin user
    $admin = User::factory()->create();
    $workspaceToRemoveFrom = Workspace::factory()->create();
    $otherWorkspace = Workspace::factory()->create();
    $guest = User::factory()->create();

    $workspaceToRemoveFrom->users()->attach($admin, ['role' => 'admin']);
    $workspaceToRemoveFrom->users()->attach($guest, ['role' => 'guest']);
    $otherWorkspace->users()->attach($guest, ['role' => 'member']); // Belongs to another workspace

    expect($guest->workspaces()->count())->toBe(2);

    // Act: Dispatch the job to remove guests from the first workspace
    // Pass the admin user as required by the job constructor
    RemoveWorkspaceGuests::dispatchSync($admin, $workspaceToRemoveFrom); // Fixed: Job expects User first, then additional param

    // Assert: Guest user now has 1 workspace (the other one), no default created
    $guest->refresh();
    expect($guest->workspaces()->count())->toBe(1);
    $remainingWorkspace = $guest->workspaces()->first();
    expect($remainingWorkspace->id)->toBe($otherWorkspace->id);
    expect($guest->workspaces()->where('name', 'My Workspace')->exists())->toBeFalse();
});

test('ensures user with no workspaces gets a default one', function () {
    // Arrange: Create user without any workspaces
    $user = User::factory()->create();
    expect($user->workspaces()->count())->toBe(0);

    // Create a controller that uses the trait
    $controller = new class () {
        use \App\Traits\EnsureUserHasWorkspace;

        public function ensureWorkspace(User $user)
        {
            $this->ensureUserHasWorkspace($user);
        }
    };

    // Act: Call the trait method
    $controller->ensureWorkspace($user);

    // Assert: User now has a workspace
    $user->refresh();
    expect($user->workspaces()->count())->toBe(1);

    // Check workspace properties
    $newWorkspace = $user->workspaces()->first();
    expect($newWorkspace->name)->toBe('My Workspace');

    // Check role
    $pivotData = DB::table('user_workspace')
        ->where('user_id', $user->id)
        ->where('workspace_id', $newWorkspace->id)
        ->first();
    expect($pivotData->role)->toBe('admin');
});
