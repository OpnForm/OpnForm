<?php

use App\Models\Workspace;
use App\Service\Billing\Feature;
use App\Service\Billing\PlanAccessService;

function renameMultiUserRolesMigration()
{
    return include database_path('migrations/2026_06_04_000000_rename_multi_user_roles_to_invite_user_in_plan_overrides.php');
}

it('renames multi_user.roles overrides to invite_user', function () {
    $workspace = Workspace::factory()->create([
        'plan_overrides' => [
            'features' => ['multi_user.roles', 'branding.advanced'],
            'legacy_pro_grandfathering' => [
                'features' => ['multi_user.roles'],
            ],
            'permanent' => [
                'features' => ['multi_user.roles'],
            ],
        ],
    ]);

    renameMultiUserRolesMigration()->up();

    $workspace = $workspace->fresh();
    $overrides = $workspace->plan_overrides;

    expect($overrides['features'])->toContain('invite_user');
    expect($overrides['features'])->not->toContain('multi_user.roles');
    expect($overrides['legacy_pro_grandfathering']['features'])->toContain('invite_user');
    expect($overrides['permanent']['features'])->toContain('invite_user');
    expect(app(PlanAccessService::class)->hasFeature($workspace, Feature::INVITE_USER))->toBeTrue();
});

it('leaves workspaces without multi_user.roles overrides unchanged', function () {
    $workspace = Workspace::factory()->create([
        'plan_overrides' => [
            'features' => ['branding.advanced'],
        ],
    ]);

    renameMultiUserRolesMigration()->up();

    expect($workspace->fresh()->plan_overrides)->toBe([
        'features' => ['branding.advanced'],
    ]);
});
