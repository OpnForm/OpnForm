<?php

namespace App\Jobs\Form;

use App\Models\Forms\FormView;
use App\Service\Forms\Analytics\IpLocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sets the country of a view saved as "Unknown". It only ever updates that view, so a retry can't count it twice.
 */
class ResolveFormViewCountry implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $viewId, public string $ip)
    {
    }

    /**
     * Queue a lookup only when lookups are on, the IP is public and a worker runs the queue,
     * since the sync driver would make the visitor wait for the provider
     */
    public static function dispatchFor(FormView $view, ?string $ip): void
    {
        $ip = IpLocationService::lookupAddress($ip);
        $driver = config('queue.connections.' . config('queue.default') . '.driver');

        if ($ip === null || in_array($driver, ['sync', 'null'], true) || !app(IpLocationService::class)->enabled()) {
            return;
        }

        self::dispatch($view->id, $ip);
    }

    /**
     * A failed lookup isn't retried: the service caches it briefly, and the view stays "Unknown"
     */
    public function handle(IpLocationService $ipLocationService): void
    {
        $location = $ipLocationService->lookup($this->ip);
        // The view is gone once CleanDatabase has folded it into the daily statistics
        $view = $location ? FormView::find($this->viewId) : null;

        if ($view) {
            $view->update(['meta' => array_merge($view->meta ?? [], ['country' => $location->country])]);
        }
    }

    public function backoff(): array
    {
        return [10, 30];
    }
}
