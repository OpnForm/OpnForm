<?php

namespace App\Http\Controllers\Forms\Integration;

use App\Events\Forms\FormSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Resources\FormIntegrationsEventResource;
use App\Listeners\Forms\NotifyFormSubmission;
use App\Models\Forms\Form;
use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Http\JsonResponse;

class FormIntegrationsEventController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Form $form, string $integrationid)
    {
        $this->authorize('manageIntegrations', $form);
        $formIntegration = $form->integrations()->findOrFail((int) $integrationid);

        return FormIntegrationsEventResource::collection(
            $formIntegration->events()->orderByDesc('created_at')->get()
        );
    }

    public function retry(Form $form, string $integrationid, int $event): JsonResponse
    {
        $this->authorize('manageIntegrations', $form);
        $formIntegration = $form->integrations()->findOrFail((int) $integrationid);
        $integrationEvent = $formIntegration->events()->findOrFail($event);

        if ($integrationEvent->status !== FormIntegrationsEvent::STATUS_ERROR) {
            return response()->json([
                'message' => 'Only failed events can be retried.',
            ], 422);
        }

        $eventData = (array) ($integrationEvent->data ?? []);
        $submissionId = $eventData['submission_id'] ?? null;

        if (!$submissionId) {
            return response()->json([
                'message' => 'This event cannot be retried because submission data is unavailable.',
            ], 422);
        }

        $integrationEvents = $formIntegration->events()->get();

        if (!$integrationEvent->canRetry($integrationEvents)) {
            return response()->json([
                'message' => 'This event has already been successfully retried.',
            ], 422);
        }

        $submission = $form->submissions()->findOrFail($submissionId);
        $submissionData = array_merge($submission->data ?? [], [
            'submission_id' => $submission->id,
        ]);

        NotifyFormSubmission::getIntegrationHandler(
            new FormSubmitted($form, $submissionData),
            $formIntegration
        )->run();

        $integrationEvents = $formIntegration->events()->get();
        FormIntegrationsEventResource::withIntegrationEvents($integrationEvents);

        $latestEvent = $integrationEvents->sortByDesc('id')->first();
        $message = $latestEvent->status === FormIntegrationsEvent::STATUS_SUCCESS
            ? 'Event retried successfully.'
            : 'Retry failed.';

        return $this->success([
            'message' => $message,
            'event' => FormIntegrationsEventResource::make($latestEvent),
        ]);
    }
}
