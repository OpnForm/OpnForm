<?php

use App\Http\Resources\FormSubmissionResource;
use App\Integrations\Handlers\AbstractIntegrationHandler;
use App\Models\Forms\FormSubmission;
use App\Service\Forms\SubmissionAttribution;

function attributionSubmissionData($test, $form, array $overrides = []): array
{
    $nameField = collect($form->properties)->firstWhere('type', 'text');

    return $test->generateFormSubmissionData($form, [
        $nameField['id'] => 'Attributed submission',
        ...$overrides,
    ]);
}

it('stores every supported attribution parameter outside submission data', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $trackingParameters = collect(SubmissionAttribution::PARAMETERS)
        ->mapWithKeys(fn (string $parameter) => [$parameter => $parameter . '-value'])
        ->all();

    $payload = attributionSubmissionData($this, $form);
    $payload['tracking_parameters'] = $trackingParameters;

    $this->postJson(route('forms.answer', $form->slug), $payload)->assertSuccessful();

    $submission = $form->submissions()->firstOrFail();
    expect($submission->meta['attribution'])->toBe($trackingParameters)
        ->and($submission->data)->not->toHaveKey('tracking_parameters');
});

it('rejects unsupported, non-string, and oversized attribution values', function (array $trackingParameters, string $errorKey) {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $payload = attributionSubmissionData($this, $form);
    $payload['tracking_parameters'] = $trackingParameters;

    $this->postJson(route('forms.answer', $form->slug), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'unsupported key' => [['secret_token' => 'do-not-store'], 'tracking_parameters'],
    'non-string value' => [['utm_source' => ['google']], 'tracking_parameters.utm_source'],
    'oversized value' => [['gclid' => str_repeat('x', SubmissionAttribution::MAX_VALUE_LENGTH + 1)], 'tracking_parameters.gclid'],
]);

it('does not create attribution metadata for empty values', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $payload = attributionSubmissionData($this, $form);
    $payload['tracking_parameters'] = ['utm_source' => '   ', 'gclid' => ''];

    $this->postJson(route('forms.answer', $form->slug), $payload)->assertSuccessful();

    expect($form->submissions()->firstOrFail()->meta)->toBeNull();
});

it('keeps first-touch attribution across partial completion', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['enable_partial_submissions' => true]);

    $partialPayload = attributionSubmissionData($this, $form);
    $partialPayload['is_partial'] = true;
    $partialPayload['tracking_parameters'] = ['utm_source' => 'first-touch'];
    $partialResponse = $this->postJson(route('forms.answer', $form->slug), $partialPayload)
        ->assertSuccessful();

    $completePayload = attributionSubmissionData($this, $form);
    $completePayload['submission_hash'] = $partialResponse->json('submission_hash');
    $completePayload['tracking_parameters'] = [
        'utm_source' => 'later-touch',
        'utm_campaign' => 'must-not-be-added',
    ];
    $this->postJson(route('forms.answer', $form->slug), $completePayload)->assertSuccessful();

    $submission = $form->submissions()->firstOrFail();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED)
        ->and($submission->meta['attribution'])->toBe(['utm_source' => 'first-touch']);
});

it('does not allow an admin edit to change attribution', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $payload = attributionSubmissionData($this, $form);
    $payload['tracking_parameters'] = ['utm_source' => 'original'];
    $this->postJson(route('forms.answer', $form->slug), $payload)->assertSuccessful();

    $submission = $form->submissions()->firstOrFail();
    $editPayload = attributionSubmissionData($this, $form);
    $editPayload['tracking_parameters'] = ['utm_source' => 'admin-change'];
    $this->putJson(route('open.forms.submissions.update', [$form, $submission->id]), $editPayload)
        ->assertSuccessful();

    expect($submission->fresh()->meta['attribution'])->toBe(['utm_source' => 'original']);
});

it('exposes attribution to owners but not publicly', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $submission = $form->submissions()->create([
        'data' => [],
        'status' => FormSubmission::STATUS_COMPLETED,
        'meta' => ['attribution' => ['utm_source' => 'newsletter']],
    ]);

    $ownerResponse = $this->getJson(route('open.forms.submissions.fetch', [$form, $submission->id]))
        ->assertSuccessful();
    expect($ownerResponse->json('meta.attribution.utm_source'))->toBe('newsletter');

    $submission->setRelation('form', $form);
    $publicData = (new FormSubmissionResource($submission))->publiclyAccessed()->resolve();
    expect($publicData)->not->toHaveKey('meta');
});

it('adds attribution to machine integration payloads without changing field data', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $submissionData = attributionSubmissionData($this, $form);

    $payload = AbstractIntegrationHandler::formatWebhookData(
        $form,
        $submissionData,
        ['attribution' => ['utm_campaign' => 'launch']],
    );

    expect($payload['meta']['attribution'])->toBe(['utm_campaign' => 'launch'])
        ->and($payload['data'])->not->toHaveKey('utm_campaign');
});
