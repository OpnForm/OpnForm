<?php

namespace App\Console\Commands\Billing;

use App\Models\License;
use App\Models\Workspace;
use App\Service\Billing\LifetimeLicenseWorkspaceEntitlements;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillLifetimeLicenseWorkspaceEntitlements extends Command
{
    protected $signature = 'billing:backfill-lifetime-license-workspace-entitlements
        {--apply : Persist the missing overrides. Without this option the command only reports what would change.}
        {--user-id= : Restrict to workspaces where this user is an admin.}
        {--workspace-id= : Restrict to one workspace.}';

    protected $description = 'Apply legacy lifetime and extra-pro workspace entitlements to eligible workspaces.';

    public function handle(LifetimeLicenseWorkspaceEntitlements $entitlements): int
    {
        $apply = (bool) $this->option('apply');
        $updated = 0;
        $skipped = 0;

        $extraProEmails = $entitlements->extraProEmails();

        $query = Workspace::query()
            ->whereHas('owners', function (Builder $query) use ($extraProEmails) {
                $query->whereHas('licenses', function (Builder $licenseQuery) {
                    $licenseQuery
                        ->where('license_provider', 'appsumo')
                        ->where('status', License::STATUS_ACTIVE);
                });

                if ($extraProEmails !== []) {
                    $query->orWhereIn('email', $extraProEmails);
                }
            })
            ->with(['users' => function ($query) {
                $query->withPivot('role');
            }])
            ->orderBy('id');

        if ($workspaceId = $this->option('workspace-id')) {
            $query->whereKey($workspaceId);
        }

        if ($userId = $this->option('user-id')) {
            $query->whereHas('owners', fn (Builder $query) => $query->whereKey($userId));
        }

        $query->chunkById(100, function ($workspaces) use ($apply, $entitlements, &$updated, &$skipped) {
            foreach ($workspaces as $workspace) {
                if ($entitlements->isComplete($workspace)) {
                    $this->line("skipped workspace={$workspace->id} reason=already_complete");
                    $skipped++;
                    continue;
                }

                if (!$apply) {
                    $this->line("would_update workspace={$workspace->id}");
                    $updated++;
                    continue;
                }

                if ($entitlements->applyForEligibleWorkspace($workspace)) {
                    $this->line("updated workspace={$workspace->id}");
                    $updated++;
                } else {
                    $this->line("skipped workspace={$workspace->id} reason=no_eligible_owner");
                    $skipped++;
                }
            }
        });

        $this->info(($apply ? 'Updated' : 'Would update') . " {$updated} workspace(s). Skipped {$skipped} workspace(s).");

        return self::SUCCESS;
    }
}
