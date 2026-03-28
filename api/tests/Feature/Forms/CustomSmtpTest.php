<?php

use App\Notifications\Forms\FormEmailNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('can not save custom SMTP settings if not pro user', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    $this->putJson(route('open.workspaces.save-email-settings', $workspace), [
        'host' => 'custom.smtp.host',
        'port' => '587',
        'username' => 'custom_username',
        'password' => 'custom_password',
    ])->assertStatus(403);
});

it('send email with custom SMTP settings', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Set custom SMTP settings
    $this->putJson(route('open.workspaces.save-email-settings', $workspace), [
        'host' => 'custom.smtp.host',
        'port' => '587',
        'username' => 'custom_username',
        'password' => 'custom_password',
    ])->assertSuccessful();

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => 'test@test.com',
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there 👋 <br>New form submission received.',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => 'reply@example.com',
    ]);

    $formData = $this->generateFormSubmissionData($form);

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
            $renderedMail = $notification->toMail($notifiable);
            return $notifiable->routes['mail'] === 'test@test.com' &&
                config('mail.mailers.custom_smtp.host') === 'custom.smtp.host' &&
                config('mail.mailers.custom_smtp.port') === '587' &&
                config('mail.mailers.custom_smtp.username') === 'custom_username' &&
                config('mail.mailers.custom_smtp.password') === 'custom_password';
        }
    );
});
