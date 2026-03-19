<?php

use App\Notifications\Forms\FormEmailNotification;
use Illuminate\Notifications\AnonymousNotifiable;

it('renders a custom logo even when opnform branding is disabled', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'no_branding' => true,
    ]);

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'Test Sender',
        'subject' => 'Test Subject',
        'email_content' => 'Custom content',
        'include_submission_data' => true,
        'logo_url' => 'https://example.com/logo.png',
        'font_family' => 'Inter',
        'font_color' => '#1a1a1a',
        'outer_background_color' => '#f5f5f5',
        'inner_background_color' => '#ffffff',
    ]);

    $formData = $this->generateFormSubmissionData($form);
    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData);
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);

    $html = trim($mailable->toMail($notifiable)->render());

    expect($html)->toContain('https://example.com/logo.png');
    expect($html)->toContain('background-color: #f5f5f5;');
    expect($html)->toContain('background-color: #ffffff;');
    expect($html)->toContain("font-family: 'Inter', sans-serif;");
    expect($html)->toContain('color: #1a1a1a;');
    expect($html)->not->toContain('border-top: 1px solid #edf2f7;');
    expect($html)->not->toContain('border-bottom: 1px solid #edf2f7;');
    expect($html)->not->toContain('box-shadow: 0 2px 0');
    expect($html)->not->toContain('All rights reserved.');
    expect(preg_match('/<body[^>]*background-color:\s*#f5f5f5;/i', $html))->toBe(1);
});

it('inlines resize styles in generated email content html', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'Test Sender',
        'subject' => 'Test Subject',
        'email_content' => '<p><img class="ql-resize-style-left" src="https://example.com/body.png"></p>',
        'include_submission_data' => true,
    ]);

    $formData = $this->generateFormSubmissionData($form);
    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData);
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);

    $mailData = $mailable->toMail($notifiable)->viewData;

    expect($mailData['emailContent'])->toContain('https://example.com/body.png');
    expect($mailData['emailContent'])->toContain('float:left; margin:0 1em 1em 0;');
    expect($mailData['emailContent'])->not->toContain('ql-resize-style-left');
});
