<?php

namespace App\Service\Integrations;

use App\Events\Models\FormIntegrationsEventCreated;
use App\Models\Integration\FormIntegration;
use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordWebhookOutcome
{
    private const FAILURE_LIMIT = 3;
    private const WINDOW_SECONDS = 900;
    private const CONFIGURATION_ERRORS = [401, 403, 404, 405, 410];

    public function handle(
        FormIntegration $snapshot,
        array $attributes,
        ?FormIntegrationsEvent $attempt
    ): FormIntegrationsEvent {
        $event = DB::transaction(function () use ($snapshot, $attributes, $attempt) {
            $integration = FormIntegration::query()->lockForUpdate()->find($snapshot->id);
            if ($integration?->status === FormIntegration::STATUS_ACTIVE
                && ($integration->failure_streak['version'] ?? null) === ($snapshot->failure_streak['version'] ?? null)
                && $attributes['status'] !== 'skipped') {
                $this->updateFailureStreak($integration, $attributes);
            }

            if (!$attempt) {
                $event = $snapshot->events()->make($attributes);
                $event->saveWithoutCreationNotification();

                return $event;
            }

            $attributes['data'] = array_merge((array) $attempt->data, $attributes['data']);
            $attempt->update($attributes);

            return $attempt;
        });

        if (!$attempt || $event->status === FormIntegrationsEvent::STATUS_ERROR) {
            DB::afterCommit(function () use ($event) {
                try {
                    FormIntegrationsEventCreated::dispatch($event);
                } catch (\Throwable $exception) {
                    Log::error('Integration outcome notification could not be queued', [
                        'integration_event_id' => $event->id,
                        'exception_type' => $exception::class,
                    ]);
                }
            });
        }

        return $event;
    }

    private function updateFailureStreak(FormIntegration $integration, array &$attributes): void
    {
        $streak = $integration->failure_streak ?? [];
        $code = $attributes['data']['status'] ?? null;

        if ($attributes['status'] !== FormIntegrationsEvent::STATUS_ERROR
            || !in_array($code, self::CONFIGURATION_ERRORS, true)) {
            if (isset($streak['code'])) {
                $integration->failure_streak = array_intersect_key($streak, ['version' => true, 'resume_after' => true]);
                $integration->saveQuietly();
            }
            return;
        }

        $now = now()->getTimestamp();
        $failures = $code === ($streak['code'] ?? null)
            ? array_values(array_filter($streak['failures'], fn (int $time) => $time >= $now - self::WINDOW_SECONDS))
            : [];
        $failures[] = $now;

        if (count($failures) >= self::FAILURE_LIMIT) {
            $integration->status = FormIntegration::STATUS_INACTIVE;
            $streak['auto_disabled'] = true;
            $attributes['data']['automatic_deactivation'] = "Disabled automatically after three consecutive HTTP {$code} failures within 15 minutes. Check the integration settings before reactivating it.";
        }

        $integration->failure_streak = array_merge($streak, ['code' => $code, 'failures' => $failures]);
        $integration->saveQuietly();
    }
}
