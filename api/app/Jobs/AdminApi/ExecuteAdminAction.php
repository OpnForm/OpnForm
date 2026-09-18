<?php

namespace App\Jobs\AdminApi;

use App\Models\AdminApiAction;
use App\Service\AdminApi\ActionExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteAdminAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(public string $actionId)
    {
    }

    public function handle(ActionExecutor $executor): void
    {
        app(\App\Http\Middleware\AdminApiRemoteTimeout::class)->handle(request(), fn () => $executor->execute($this->actionId));
    }

    public function failed(?Throwable $exception): void
    {
        AdminApiAction::whereKey($this->actionId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'uncertain', 'result' => json_encode(['code' => 'worker_interrupted'])]);
    }
}
