<?php

use App\Notifications\Forms\FormEmailNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('send email with the submitted data', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
    ]);

    $formData = $this->generateFormSubmissionData($form);

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);
    expect($renderedMail->subject)->toBe('New form submission');
    expect($renderedMail->replyTo[0][0])->toBe('reply@example.com');
    expect(trim($renderedMail->render()))->toContain('Test body');
});

it('sends a email if needed', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailProperty = collect($form->properties)->first(function ($property) {
        return $property['type'] == 'email';
    });

    $this->createFormIntegration('email', $form->id, [
        'send_to' => '<span mention-field-id="' . $emailProperty['id'] . '" mention-field-name="' . $emailProperty['name'] . '" mention-fallback="" contenteditable="false" mention="true">' . $emailProperty['name'] . '</span>',
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>New form submission received.',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
    ]);

    $formData = [
        $emailProperty['id'] => 'test@test.com',
    ];

    Notification::fake();

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    Notification::assertSentTo(
        new AnonymousNotifiable(),
        FormEmailNotification::class,
        function (FormEmailNotification $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'test@test.com';
        }
    );
});

it('does not send a email if not needed', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $emailProperty = collect($form->properties)->first(function ($property) {
        return $property['type'] == 'email';
    });
    $formData = [
        $emailProperty['id'] => 'test@test.com',
    ];

    Notification::fake();

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    Notification::assertNotSentTo(
        new AnonymousNotifiable(),
        FormEmailNotification::class,
        function (FormEmailNotification $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'test@test.com';
        }
    );
});

it('uses custom sender email in self-hosted mode', function () {
    config(['app.self_hosted' => true]);

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $customSenderEmail = 'custom@example.com';
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => 'test@test.com',
        'sender_name' => 'Custom Sender',
        'sender_email' => $customSenderEmail,
        'subject' => 'Custom Subject',
        'email_content' => 'Custom content',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
    ]);

    $formData = $this->generateFormSubmissionData($form);

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', 'test@test.com');
    $renderedMail = $mailable->toMail($notifiable);

    expect($renderedMail->from[0])->toBe($customSenderEmail);
    expect($renderedMail->from[1])->toBe('Custom Sender');
    expect($renderedMail->subject)->toBe('Custom Subject');
    expect(trim($renderedMail->render()))->toContain('Custom content');
});

it('does not use custom sender email in non-self-hosted mode', function () {
    config(['app.self_hosted' => false]);
    config(['mail.from.address' => 'default@example.com']);

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $customSenderEmail = 'custom@example.com';
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'Custom Sender',
        'sender_email' => $customSenderEmail,
        'subject' => 'Custom Subject',
        'email_content' => 'Custom content',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
    ]);

    $formData = $this->generateFormSubmissionData($form);

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);

    expect($renderedMail->from[0])->toMatch('/^default\+\d+@example\.com$/');
    expect($renderedMail->from[1])->toBe('Custom Sender');
    expect($renderedMail->subject)->toBe('Custom Subject');
    expect(trim($renderedMail->render()))->toContain('Custom content');
});

it('send email with mention as sender name', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailProperty = collect($form->properties)->first(function ($property) {
        return $property['type'] == 'email';
    });

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => '<span mention-field-id="' . $emailProperty['id'] . '" mention-field-name="' . $emailProperty['name'] . '" mention-fallback="" contenteditable="false" mention="true">' . $emailProperty['name'] . '</span>',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => null
    ]);

    $formData = [
        $emailProperty['id'] => 'reply@example.com',
    ];

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);
    expect($renderedMail->from[1])->toBe('reply@example.com');
});

it('send email with mention as reply to', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailProperty = collect($form->properties)->first(function ($property) {
        return $property['type'] == 'email';
    });

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => '<span mention-field-id="' . $emailProperty['id'] . '" mention-field-name="' . $emailProperty['name'] . '" mention-fallback="" contenteditable="false" mention="true">' . $emailProperty['name'] . '</span>'
    ]);

    $formData = [
        $emailProperty['id'] => 'reply@example.com',
    ];

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);
    expect($renderedMail->replyTo[0][0])->toBe('reply@example.com');
});

it('send email with empty reply to', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailProperty = collect($form->properties)->first(function ($property) {
        return $property['type'] == 'email';
    });

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => null,
    ]);

    $formData = [
        $emailProperty['id'] => 'reply@example.com',
    ];

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);
    expect($renderedMail->replyTo[0][0])->toBe($form->creator->email);
});

it('uses exact email address without timestamp in self-hosted mode', function () {
    config(['app.self_hosted' => true]);
    config(['mail.from.address' => 'default@example.com']);

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => 'test@test.com',
        'sender_name' => 'Custom Sender',
        'subject' => 'Test Subject',
        'email_content' => 'Test content',
        'include_submission_data' => true,
    ]);

    $formData = $this->generateFormSubmissionData($form);

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', 'test@test.com');
    $renderedMail = $mailable->toMail($notifiable);

    // In self-hosted mode, the email should be exactly as configured without timestamp
    expect($renderedMail->from[0])->toBe('default@example.com');
});

it('send email with hidden field as mention to send email', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailProperty = collect($form->properties)->first(function ($property) {
        return $property['type'] == 'email';
    });

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => '<span mention-field-id="' . $emailProperty['id'] . '" mention-field-name="' . $emailProperty['name'] . '" mention-fallback="" contenteditable="false" mention="true">' . $emailProperty['name'] . '</span>',
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => null,
    ]);

    $formData = [
        $emailProperty['id'] => 'test@test.com',
    ];

    Notification::fake();

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    Notification::assertSentTo(
        new AnonymousNotifiable(),
        FormEmailNotification::class,
        function (FormEmailNotification $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'test@test.com';
        }
    );
});

it('send email with the edit submission link', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'editable_submissions' => true,
        'editable_submissions_button_text' => 'Edit submission'
    ]);
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
        'link_edit_submission' => true,
    ]);

    $formData = $this->generateFormSubmissionData($form);

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);
    expect($renderedMail->subject)->toBe('New form submission');
    expect($renderedMail->replyTo[0][0])->toBe('reply@example.com');
    expect(trim($renderedMail->render()))->toContain('Test body');
    expect(trim($renderedMail->render()))->toContain('Edit submission');
});

it('send email without the edit submission link', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'editable_submissions' => true,
        'editable_submissions_button_text' => 'Edit submission'
    ]);
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>Test body',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
        'link_edit_submission' => false,
    ]);

    $formData = $this->generateFormSubmissionData($form);

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);
    expect($renderedMail->subject)->toBe('New form submission');
    expect($renderedMail->replyTo[0][0])->toBe('reply@example.com');
    expect(trim($renderedMail->render()))->toContain('Test body');
    expect(trim($renderedMail->render()))->not->toContain('Edit submission');
});
