<?php

namespace App\Http\Controllers\Forms\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormIntegrationsEventResource;
use App\Models\Forms\Form;
use App\Service\Integrations\RetryIntegrationEvent;
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
        $integration = $form->integrations()->findOrFail((int) $integrationid);
        $integration->setRelation('form', $form);

        return FormIntegrationsEventResource::forIntegration(
            $integration,
            $integration->events()->orderByDesc('created_at')->orderByDesc('id')->get()
        );
    }

    public function retry(Form $form, string $integrationid, string $event, RetryIntegrationEvent $retry): JsonResponse
    {
        $this->authorize('manageIntegrations', $form);
        $eventId = filter_var($event, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($eventId === false, 404);
        $integration = $form->integrations()->findOrFail((int) $integrationid);
        $result = $retry->handle($integration, $eventId);
        $message = match ($result->status) {
            'success' => 'Event retried successfully.',
            'accepted' => 'Retry accepted by the mail transport. Delivery is not yet confirmed.',
            'delivered' => 'Retry delivered to the receiving mail server.',
            'skipped' => 'No request was sent because the integration conditions were not met.',
            'processing' => 'The retry is still processing. Refresh the history to review its outcome.',
            'unknown' => 'Delivery is uncertain. Review the event before sending again.',
            default => 'Retry failed. Review the latest event for details.',
        };

        return response()->json([
            'type' => match ($result->status) {
                'success', 'accepted', 'delivered' => 'success',
                'error' => 'error',
                default => 'warning',
            },
            'message' => $message,
            'event' => FormIntegrationsEventResource::make($result),
        ]);
    }
}
