<?php

namespace App\Listeners\Forms;

use App\Events\Forms\FormSubmitted;
use App\Models\Integration\FormIntegration;
use App\Integrations\Handlers\AbstractIntegrationHandler;
use App\Models\Forms\SubmissionOperation;
use App\Service\Forms\SubmissionOperationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyFormSubmission implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Sends notification to pre-defined emails on form submissions
     *
     * @param object $event
     * @return void
     */
    public function handle(FormSubmitted $event)
    {
        $operationId = $event->operationId ?? null;
        if ($operationId !== null && ! SubmissionOperation::whereKey($operationId)
            ->where('form_id', $event->form->id)->where('submission_id', $event->data['submission_id'] ?? null)
            ->whereNotNull('completed_at')->exists()) {
            return; // Deleted or mismatched operation: never send its stale effects.
        }
        $formIntegrations = $event->form->integrations()->where('status', FormIntegration::STATUS_ACTIVE)->get();
        foreach ($formIntegrations as $formIntegration) {
            if ($operationId !== null) {
                app(SubmissionOperationService::class)->invokeEffect(
                    $operationId,
                    $formIntegration->id,
                    fn () => $this->getIntegrationHandler($event, $formIntegration)->run()
                );
                continue;
            }
            // Previously queued events have no operation ID; preserve their
            // original contract. Drain/reconcile them before migration.
            $this->getIntegrationHandler(
                $event,
                $formIntegration
            )->run();
        }
    }

    public static function getIntegrationHandler(
        FormSubmitted $event,
        FormIntegration $formIntegration
    ): AbstractIntegrationHandler {
        $integration = FormIntegration::getIntegration($formIntegration->integration_id);
        if ($integration && isset($integration['file_name']) && class_exists(
            'App\Integrations\Handlers\\' . $integration['file_name']
        )) {
            $className = 'App\Integrations\Handlers\\' . $integration['file_name'];
            return new $className($event, $formIntegration, $integration);
        }
        throw new \Exception('Unknown Integration!');
    }
}
