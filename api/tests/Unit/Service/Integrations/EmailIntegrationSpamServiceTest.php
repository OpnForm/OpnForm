<?php

use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use App\Models\User;
use App\Service\Integrations\EmailIntegrationSpamService;
use App\Service\UserActionService;

uses(\Tests\TestCase::class);

it('skips ai email integration spam checks when the openai key is missing', function () {
    config()->set('opnform.admin_emails', []);
    config()->set('opnform.moderator_emails', []);
    config()->set('services.openai.api_key', null);
    config()->set('spam.enabled', true);
    config()->set('spam.keywords', ['sign in']);

    $creator = (new User())->forceFill([
        'id' => 123,
        'name' => 'Test User',
        'email' => 'spam-check-ci@example.test',
        'created_at' => now(),
        'blocked_at' => null,
        'meta' => [],
    ]);

    $form = (new Form())->forceFill([
        'id' => 456,
        'title' => 'Contact Form',
    ]);
    $form->setRelation('creator', $creator);

    $integration = (new FormIntegration())->forceFill([
        'id' => 789,
        'form_id' => $form->id,
        'integration_id' => 'email',
        'status' => FormIntegration::STATUS_ACTIVE,
        'data' => (object) [
            'send_to' => $creator->email,
            'subject' => 'Secure sign in required',
            'email_content' => 'Please sign in to continue.',
        ],
    ]);
    $integration->setRelation('form', $form);

    $userActionService = Mockery::mock(UserActionService::class);
    $userActionService->shouldNotReceive('block');

    $result = (new EmailIntegrationSpamService($userActionService))->checkForSpam($form, $integration);

    expect($result)->toBeNull();
});
