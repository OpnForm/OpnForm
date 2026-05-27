<?php

use App\Models\Forms\Form;
use App\Models\User;
use App\Service\Forms\FormSpamContentAnalyzer;
use App\Service\Forms\FormSpamService;
use App\Service\UserActionService;

uses(\Tests\TestCase::class);

it('checks spam keywords against real form properties instead of the missing fields attribute', function () {
    config()->set('spam.keywords', ['sign in']);

    $form = (new Form())->forceFill([
        'title' => 'Contact Form',
        'description' => null,
        'properties' => [
            [
                'id' => 'text',
                'type' => 'nf-text',
                'name' => 'Text',
                'hidden' => false,
                'content' => '<p>Securely sign in with your BT email address.</p>',
            ],
        ],
    ]);

    $service = new FormSpamService(new UserActionService(), new FormSpamContentAnalyzer());
    $method = new ReflectionMethod(FormSpamService::class, 'containsKeywords');
    $method->setAccessible(true);

    expect($method->invoke($service, $form))->toBeTrue();
});

it('matches short spam keywords as tokens instead of inside safe words', function () {
    config()->set('spam.keywords', ['pin', 'pins', 'pincode', 'otps']);

    $service = new FormSpamService(new UserActionService(), new FormSpamContentAnalyzer());
    $method = new ReflectionMethod(FormSpamService::class, 'containsKeywords');
    $method->setAccessible(true);

    $shippingForm = (new Form())->forceFill([
        'title' => 'Shipping Form',
        'description' => null,
        'properties' => [
            [
                'id' => 'address',
                'type' => 'text',
                'name' => 'Shipping Address',
                'hidden' => false,
            ],
        ],
    ]);

    $momoPinForm = (new Form())->forceFill([
        'title' => 'Delivery Form',
        'description' => null,
        'properties' => [
            [
                'id' => 'pin',
                'type' => 'text',
                'name' => 'Enter Your Account MoMo Pin To Authorize Your Delivery Receipt',
                'hidden' => false,
            ],
        ],
    ]);

    $pluralPinForm = (new Form())->forceFill([
        'title' => 'Wallet Verification',
        'description' => null,
        'properties' => [
            [
                'id' => 'pins',
                'type' => 'text',
                'name' => 'Enter your wallet PINs and OTPs',
                'hidden' => false,
            ],
        ],
    ]);

    $pincodeForm = (new Form())->forceFill([
        'title' => 'Delivery Form',
        'description' => null,
        'properties' => [
            [
                'id' => 'pincode',
                'type' => 'text',
                'name' => 'Enter your pincode',
                'hidden' => false,
            ],
        ],
    ]);

    expect($method->invoke($service, $shippingForm))->toBeFalse()
        ->and($method->invoke($service, $momoPinForm))->toBeTrue()
        ->and($method->invoke($service, $pluralPinForm))->toBeTrue()
        ->and($method->invoke($service, $pincodeForm))->toBeTrue();
});

it('checks keyword-triggered forms even when the creator account is established', function () {
    config()->set('opnform.admin_emails', []);
    config()->set('opnform.moderator_emails', []);
    config()->set('spam.random_check_percentage', 0);
    config()->set('spam.keywords', ['momo pin']);

    $creator = (new User())->forceFill([
        'id' => 123,
        'name' => 'Established User',
        'email' => 'established-spam-check@example.test',
        'created_at' => now()->subMonths(4),
        'blocked_at' => null,
        'meta' => [],
    ]);

    $form = (new Form())->forceFill([
        'id' => 456,
        'title' => 'Delivery Form',
        'properties' => [
            [
                'id' => 'pin',
                'type' => 'text',
                'name' => 'Enter Your Account MoMo Pin To Authorize Your Delivery Receipt',
                'hidden' => false,
            ],
        ],
    ]);
    $form->setRelation('creator', $creator);

    $service = new FormSpamService(new UserActionService(), new FormSpamContentAnalyzer());
    $method = new ReflectionMethod(FormSpamService::class, 'shouldCheck');
    $method->setAccessible(true);

    expect($method->invoke($service, $form))->toBeTrue();
});

it('skips ai spam checks when the openai key is missing', function () {
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
        'properties' => [
            [
                'id' => 'text',
                'type' => 'nf-text',
                'name' => 'Text',
                'hidden' => false,
                'content' => '<p>Securely sign in with your BT email address.</p>',
            ],
        ],
    ]);
    $form->setRelation('creator', $creator);

    $userActionService = Mockery::mock(UserActionService::class);
    $userActionService->shouldNotReceive('block');

    (new FormSpamService($userActionService, new FormSpamContentAnalyzer()))->checkForm($form);

    expect(true)->toBeTrue();
});
