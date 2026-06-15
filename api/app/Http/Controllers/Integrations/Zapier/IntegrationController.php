<?php

namespace App\Http\Controllers\Integrations\Zapier;

use App\Http\Requests\Integration\Zapier\PollSubmissionRequest;
use App\Http\Requests\Zapier\CreateIntegrationRequest;
use App\Http\Requests\Zapier\DeleteIntegrationRequest;
use App\Integrations\Handlers\ZapierIntegration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Service\Forms\FormSubmissionDataFactory;
use Illuminate\Support\Facades\Cache;

class IntegrationController
{
    use AuthorizesRequests;

    public function store(CreateIntegrationRequest $request)
    {
        $form = $request->form();

        $this->authorize('manageIntegrations', $form);

        $form->integrations()
            ->create([
                'integration_id' => 'zapier',
                'status' => 'active',
                'data' => [
                    'hook_url' => $request->input('hookUrl'),
                ],
            ]);

        return response()->json();
    }

    public function destroy(DeleteIntegrationRequest $request)
    {
        $form = $request->form();

        $this->authorize('manageIntegrations', $form);

        $form
            ->integrations()
            ->where('data->hook_url', $request->input('hookUrl'))
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
            // Generate fake data when no previous submissions
            $submissionData = (new FormSubmissionDataFactory($form))->asFormSubmissionData()->createSubmissionData();
        }
        $cacheKey = "zapier-poll-submissions-{$form->id}";
        return (array) Cache::remember($cacheKey, 60 * 5, function () use ($form, $submissionData, $lastSubmission) {
            return [ZapierIntegration::formatWebhookData($form, $submissionData ?? $lastSubmission->data)];
        });
    }
}
