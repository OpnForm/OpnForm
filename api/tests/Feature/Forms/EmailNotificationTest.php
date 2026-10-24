<?php

use App\Notifications\Forms\FormEmailNotification;
use App\Models\PdfTemplate;
use App\Service\Pdf\PdfCacheService;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

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

it('uses the workspace policy for file links in email notifications', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'settings' => [
            'external_file_links' => [
                'expires_in_hours' => 168,
            ],
        ],
    ]);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'attachment',
                'name' => 'Attachment',
                'type' => 'files',
                'required' => false,
            ],
        ],
    ]);
    $form->load('workspace', 'creator');
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'New submission',
        'include_submission_data' => true,
    ]);

    $now = \Carbon\Carbon::parse('2026-07-17 17:00:00');
    \Carbon\Carbon::setTestNow($now);

    try {
        $mailable = new FormEmailNotification(
            new \App\Events\Forms\FormSubmitted($form, ['attachment' => ['weekend-upload.png']]),
            $integrationData,
            'mail'
        );
        $notifiable = new AnonymousNotifiable();
        $notifiable->route('mail', $user->email);
        $renderedMail = html_entity_decode($mailable->toMail($notifiable)->render());
    } finally {
        \Carbon\Carbon::setTestNow();
    }

    preg_match('/href="([^"]*submissions\/file[^"]*)"/', $renderedMail, $matches);
    parse_str((string) parse_url($matches[1] ?? '', PHP_URL_QUERY), $queryParameters);

    expect((int) ($queryParameters['expires'] ?? 0))->toBe($now->copy()->addHours(168)->timestamp);
    expect($queryParameters['signature'] ?? null)->not->toBeEmpty();
});

it('shows readable file labels instead of raw api urls', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'attachment',
                'name' => 'Attachment',
                'type' => 'files',
                'required' => false,
            ],
        ],
    ]);
    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'New submission',
        'include_submission_data' => true,
    ]);
    $storedFileName = 'company-logo_' . Str::uuid() . '.png';
    $documentFileName = 'brief_' . Str::uuid() . '.pdf';

    $notification = new FormEmailNotification(
        new \App\Events\Forms\FormSubmitted($form, [
            'attachment' => [$storedFileName, $documentFileName],
        ]),
        $integrationData
    );

    $html = html_entity_decode($notification->toMail(new AnonymousNotifiable())->render());

    expect($html)
        ->toContain('🖼️ company-logo.png')
        ->toContain('📎 brief.pdf');
    expect($html)
        ->not->toMatch('/<a href="([^"]*submissions\/file[^"]*)">\1<\/a>/')
        ->not->toMatch('/>https?:\/\/[^<]*api[^<]*<\/a>/');
});

it('embeds multiple uploaded images and signatures vertically', function () {
    Storage::fake('local');

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'images',
                'name' => 'Images',
                'type' => 'files',
                'required' => false,
            ],
            [
                'id' => 'signature',
                'name' => 'Signature',
                'type' => 'signature',
                'required' => false,
            ],
        ],
    ]);
    $imageFileName = 'company-logo_' . Str::uuid() . '.png';
    $signatureFileName = 'sign_' . Str::uuid() . '.png';
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $imageFileName), emailTinyPngBytes());
    $sixMegabyteImage = emailTinyPngBytes();
    $sixMegabyteImage .= str_repeat("\0", (6 * 1024 * 1024) - strlen($sixMegabyteImage));
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $signatureFileName), $sixMegabyteImage);

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'New submission',
        'include_submission_data' => true,
        'embed_uploaded_images' => true,
    ]);
    $notification = new FormEmailNotification(
        new \App\Events\Forms\FormSubmitted($form, [
            'images' => [$imageFileName],
            'signature' => $signatureFileName,
        ]),
        $integrationData
    );

    $mailMessage = $notification->toMail(new AnonymousNotifiable());
    $html = html_entity_decode($mailMessage->render());
    $symfonyMessage = (new Email())->from('sender@example.com')->to('recipient@example.com')->html($html);
    foreach ($mailMessage->callbacks as $callback) {
        $callback($symfonyMessage);
    }

    expect(substr_count($html, 'src="cid:'))->toBe(2)
        ->and($html)->toContain('company-logo.png')
        ->and($html)->toContain('sign.png')
        ->and(strpos($html, 'company-logo.png'))->toBeLessThan(strpos($html, 'sign.png'))
        ->and($symfonyMessage->getAttachments())->toHaveCount(2);

    foreach ($symfonyMessage->getAttachments() as $attachment) {
        expect($attachment->getDisposition())->toBe('inline')
            ->and($attachment->getContentType())->toBe('image/png')
            ->and($attachment->getContentId())->toEndWith('@opnform')
            ->and($html)->toContain('cid:' . $attachment->getContentId());
    }
});

it('falls back to a readable link for each image that is too large', function () {
    Storage::fake('local');

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'images',
                'name' => 'Images',
                'type' => 'files',
                'required' => false,
            ],
        ],
    ]);
    $inlineFileName = 'small-image_' . Str::uuid() . '.png';
    $largeFileName = 'large-image_' . Str::uuid() . '.png';
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $inlineFileName), emailTinyPngBytes());
    $largeImageContents = emailTinyPngBytes();
    $largeImageContents .= str_repeat("\0", ((6 * 1024 * 1024) + 1) - strlen($largeImageContents));
    Storage::put(
        FileUploadPathService::getFileUploadPath($form->id, $largeFileName),
        $largeImageContents
    );

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'New submission',
        'include_submission_data' => true,
        'embed_uploaded_images' => true,
    ]);
    $notification = new FormEmailNotification(
        new \App\Events\Forms\FormSubmitted($form, ['images' => [$inlineFileName, $largeFileName]]),
        $integrationData
    );

    $mailMessage = $notification->toMail(new AnonymousNotifiable());
    $html = html_entity_decode($mailMessage->render());
    $symfonyMessage = (new Email())->from('sender@example.com')->to('recipient@example.com')->html($html);
    foreach ($mailMessage->callbacks as $callback) {
        $callback($symfonyMessage);
    }

    expect(substr_count($html, 'src="cid:'))->toBe(1)
        ->and($html)->toContain('🖼️ small-image.png')
        ->and($html)->toContain('🖼️ large-image.png')
        ->and($symfonyMessage->getAttachments())->toHaveCount(1);
});

it('keeps selected pdf attachments ahead of optional inline images in the email size budget', function () {
    Storage::fake('local');

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'image',
                'name' => 'Image',
                'type' => 'files',
                'required' => false,
            ],
        ],
    ]);
    $submission = $form->submissions()->create(['data' => []]);
    $template = PdfTemplate::create([
        'form_id' => $form->id,
        'name' => 'Selected PDF',
        'filename' => 'selected.pdf',
        'original_filename' => 'Selected.pdf',
        'file_path' => "pdf-templates/{$form->id}/selected.pdf",
        'file_size' => 11 * 1024 * 1024,
        'page_count' => 1,
    ]);
    $pdfPath = 'tmp/pdf-output/selected.pdf';
    Storage::put($pdfPath, str_repeat('p', 11 * 1024 * 1024));
    $cacheService = Mockery::mock(PdfCacheService::class);
    $cacheService->shouldReceive('getOrGenerateFromTemplate')->once()->andReturn($pdfPath);
    app()->instance(PdfCacheService::class, $cacheService);

    $imageFileName = 'budget-image_' . Str::uuid() . '.png';
    $imageContents = emailTinyPngBytes();
    $imageContents .= str_repeat("\0", (5 * 1024 * 1024) - strlen($imageContents));
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $imageFileName), $imageContents);

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'New submission',
        'include_submission_data' => true,
        'embed_uploaded_images' => true,
        'pdf_template_ids' => [$template->id],
    ]);
    $notification = new FormEmailNotification(
        new \App\Events\Forms\FormSubmitted($form, [
            'submission_id' => $submission->id,
            'image' => [$imageFileName],
        ]),
        $integrationData
    );

    $mailMessage = $notification->toMail(new AnonymousNotifiable());
    $html = html_entity_decode($mailMessage->render());
    $symfonyMessage = (new Email())->from('sender@example.com')->to('recipient@example.com')->html($html);
    foreach ($mailMessage->callbacks as $callback) {
        $callback($symfonyMessage);
    }

    expect($mailMessage->rawAttachments)->toHaveCount(1)
        ->and($mailMessage->rawAttachments[0]['name'])->toEndWith('.pdf')
        ->and($html)->not->toContain('src="cid:')
        ->and($html)->toContain('🖼️ budget-image.png')
        ->and($symfonyMessage->getAttachments())->toHaveCount(0);
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
    $user = $this->actingAsBusinessUser();
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
    $user = $this->actingAsBusinessUser();
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

it('resolves mentions for hidden fields in email content when hidden submission data is excluded', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $hiddenField = [];
    $form->properties = collect($form->properties)->map(function ($property) use (&$hiddenField) {
        if ($property['type'] == 'email') {
            $property['hidden'] = true;
            $property['required'] = false;
            $hiddenField = $property;
        }
        return $property;
    })->toArray();
    $form->update();

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => '<p>Token: <span mention="true" mention-field-id="' . $hiddenField['id'] . '" mention-field-name="Secret token" mention-fallback=""></span></p>',
        'include_submission_data' => true,
        'include_hidden_fields_submission_data' => false,
        'reply_to' => null,
    ]);

    $formData = [
        $hiddenField['id'] => 'hidden-value-xyz'
    ];

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData, 'mail');
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);
    $renderedMail = $mailable->toMail($notifiable);

    expect(trim($renderedMail->render()))->toContain('hidden-value-xyz');
});

it('renders rich text field values as html in submission data', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $richTextField = [
        'id' => 'rich-text-sample',
        'name' => 'Rich Tex Sample',
        'type' => 'rich_text',
    ];
    $form->properties = array_merge($form->properties, [$richTextField]);
    $form->update();

    $integrationData = $this->createFormIntegration('email', $form->id, [
        'send_to' => $user->email,
        'sender_name' => 'OpnForm',
        'subject' => 'New form submission',
        'email_content' => 'Hello there',
        'include_submission_data' => true,
    ]);

    $richTextValue = '<p>Test <em>Sample </em><u>Underline </u><strong>Bold </strong><span style="color:rgb(230,0,0);">Colored</span></p><p><br /></p><p>Line break</p>';
    $formData = [
        $richTextField['id'] => $richTextValue,
    ];

    $event = new \App\Events\Forms\FormSubmitted($form, $formData);
    $mailable = new FormEmailNotification($event, $integrationData);
    $notifiable = new AnonymousNotifiable();
    $notifiable->route('mail', $user->email);

    $html = trim($mailable->toMail($notifiable)->render());

    expect($html)->toContain('<strong');
    expect($html)->toContain('Bold </strong>');
    expect($html)->toContain('<em');
    expect($html)->toContain('Sample </em>');
    expect($html)->not->toContain('&lt;p&gt;Test &lt;em&gt;Sample');
});

function emailTinyPngBytes(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAQEAAP8AAAAASUVORK5CYII=',
        true
    ) ?: '';
}
