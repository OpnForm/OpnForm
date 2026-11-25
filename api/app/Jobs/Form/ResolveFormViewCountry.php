<?php

namespace App\Jobs\Form;

use App\Models\Forms\FormView;
use App\Service\Forms\Analytics\IpLocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sets the country of a view saved as "Unknown". It only ever updates that view, so a retry can't count it twice.
 */
class ResolveFormViewCountry implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const QUEUE = 'form-view-country';

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $viewId, public string $ip)
    {
    }

    /**
     * Apply a cached country or queue a lookup for a public IP on an asynchronous connection.
     */
    public static function dispatchFor(FormView $view, ?string $ip): void
    {
        $ip = IpLocationService::lookupAddress($ip);
        $driver = config('queue.connections.' . config('queue.default') . '.driver');

        if ($ip === null || in_array($driver, [null, 'sync', 'null'], true)) {
            return;
        }

        try {
            $locationService = app(IpLocationService::class);
            if (!$locationService->enabled()) {
                return;
            }

            $cached = $locationService->cachedLookup($ip);
            if ($cached === null) {
                Bus::dispatch((new self($view->id, $ip))->onQueue(self::QUEUE));
            } elseif ($cached) {
                $view->update(['meta' => array_merge($view->meta ?? [], ['country' => $cached->country])]);
            }
        } catch (Throwable $exception) {
            Log::warning('Could not schedule form view country enrichment', [
                'view_id' => $view->id,
                'error' => class_basename($exception),
            ]);
        }
    }

    /**
     * A failed lookup isn't retried: the service caches it briefly, and the view stays "Unknown"
     */
    public function handle(IpLocationService $ipLocationService): void
    {
        // CleanDatabase may have already folded the view into daily statistics.
        $view = FormView::find($this->viewId);
        // Leave enough time for the lookup to finish before the view becomes eligible for aggregation.
        $latestViewCutoff = now()->subSeconds(FormView::COUNTRY_ENRICHMENT_WINDOW_SECONDS - $this->timeout);
        if (!$view || $view->created_at->lte($latestViewCutoff)) {
            return;
        }

        $location = $ipLocationService->lookup($this->ip);
        if ($location) {
            $view->update(['meta' => array_merge($view->meta ?? [], ['country' => $location->country])]);
        }
    }

    public function backoff(): array
    {
        return [10, 30];
    }
}
