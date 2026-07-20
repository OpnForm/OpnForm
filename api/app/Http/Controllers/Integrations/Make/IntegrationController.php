<?php

namespace App\Http\Controllers\Integrations\Make;

use App\Http\Requests\Integration\Make\CreateIntegrationRequest;
use App\Http\Requests\Integration\Make\DeleteIntegrationRequest;
use App\Http\Requests\Integration\Make\PollSubmissionRequest;
use App\Integrations\Handlers\MakeIntegration;
use App\Service\Forms\FormSubmissionDataFactory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class IntegrationController
{
    use AuthorizesRequests;

    public function store(CreateIntegrationRequest $request)
    {
        $form = $request->form();

        $this->authorize('manageIntegrations', $form);

        $integration = $form->integrations()
            ->create([
                'integration_id' => 'make',
                'status' => 'active',
                'data' => [
                    'webhook_url' => $request->input('hookUrl'),
                ],
            ]);

        return response()->json([
            'integration_id' => $integration->id,
        ]);
    }

    public function destroy(DeleteIntegrationRequest $request)
    {
        $form = $request->form();

        $this->authorize('manageIntegrations', $form);

        $form
            ->integrations()
            ->where('data->webhook_url', $request->input('hookUrl'))
            ->delete();

        return response()->json();
    }

    public function poll(PollSubmissionRequest $request)
    {
        $form = $request->form();

        $this->authorize('manageIntegrations', $form);

        $lastSubmission = $form->submissions()->latest()->first();
        $submissionData = null;
        if (!$lastSubmission) {
            $submissionData = (new FormSubmissionDataFactory($form))->asFormSubmissionData()->createSubmissionData();
        }

        $cacheKey = "make-poll-submissions-{$form->id}";

        return (array) Cache::remember($cacheKey, 60 * 5, function () use ($form, $submissionData, $lastSubmission) {
            return [MakeIntegration::formatWebhookData($form, $submissionData ?? $lastSubmission->data)];
        });
    }
}
