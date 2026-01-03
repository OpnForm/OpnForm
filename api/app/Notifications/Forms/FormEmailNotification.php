<?php

namespace App\Notifications\Forms;

use App\Events\Forms\FormSubmitted;
use App\Open\MentionParser;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class FormEmailNotification extends Notification
{
    public FormSubmitted $event;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(FormSubmitted $event, private $integrationData)
    {
        $this->event = $event;
    }

    private function getMailer(): string
    {
        $workspace = $this->event->form->workspace;
        $emailSettings = $workspace->settings['email_settings'] ?? [];

        if (
            $workspace->is_pro
            && $emailSettings
            && !empty($emailSettings['host'])
            && !empty($emailSettings['port'])
            && !empty($emailSettings['username'])
            && !empty($emailSettings['password'])
        ) {
            config([
                'mail.mailers.custom_smtp.host' => $emailSettings['host'],
                'mail.mailers.custom_smtp.port' => $emailSettings['port'],
                'mail.mailers.custom_smtp.username' => $emailSettings['username'],
                'mail.mailers.custom_smtp.password' => $emailSettings['password']
            ]);
            return 'custom_smtp';
        }

        return config('mail.default');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->mailer($this->getMailer())
            ->replyTo($this->getReplyToEmail($this->event->form->creator->email))
            ->from($this->getFromEmail(), $this->getSenderName())
            ->subject($this->getSubject())
            ->withSymfonyMessage(function (Email $message) {
                $this->addCustomHeaders($message);
            })
            ->markdown('mail.form.email-notification', $this->getMailData());
    }

    private function formatSubmissionData($createLinks = true): array
    {
        $formatter = (new FormSubmissionFormatter($this->event->form, $this->event->data))
            ->outputStringsOnly()
            ->useSignedUrlForFiles();

        if ($createLinks) {
            $formatter->createLinks();
        }
        if ($this->integrationData->include_hidden_fields_submission_data ?? false) {
            $formatter->showHiddenFields();
        }

        return $formatter->getFieldsWithValue();
    }

    private function getFromEmail(): string
    {
        $workspace = $this->event->form->workspace;
        $emailSettings = $workspace->settings['email_settings'] ?? [];

        if (
            $workspace->is_pro
            && $emailSettings
            && !empty($emailSettings['sender_address'])
        ) {
            return $emailSettings['sender_address'];
        }

        if (
            config('app.self_hosted')
            && isset($this->integrationData->sender_email)
            && $this->validateEmail($this->integrationData->sender_email)
        ) {
            return $this->integrationData->sender_email;
        }

        $baseEmail = config('mail.from.address');
        if (!config('app.self_hosted')) {
            // Insert timestamp before the @ to prevent email grouping
            $parts = explode('@', $baseEmail);
            return $parts[0] . '+' . time() . '@' . $parts[1];
        }

        return $baseEmail;
    }

    private function getSenderName(): string
    {
        $parser = new MentionParser($this->integrationData->sender_name ?? config('app.name'), $this->formatSubmissionData(false));
        return $parser->parseAsText();
    }

    private function getReplyToEmail($default): string
    {
        $replyTo = $this->integrationData->reply_to ?? null;
        if ($replyTo) {
            $parsedReplyTo = $this->parseReplyTo($replyTo);
            if ($parsedReplyTo && $this->validateEmail($parsedReplyTo)) {
                return $parsedReplyTo;
            }
        }
        return $default;
    }

    private function parseReplyTo(string $replyTo): ?string
    {
        $parser = new MentionParser($replyTo, $this->formatSubmissionData(false));
        return $parser->parseAsText();
    }

    private function getSubject(): string
    {
        $defaultSubject = 'New form submission';
        $parser = new MentionParser($this->integrationData->subject ?? $defaultSubject, $this->formatSubmissionData(false));
        return $parser->parseAsText();
    }

    private function addCustomHeaders(Email $message): void
    {
        $formId = $this->event->form->id;
        $submissionId = $this->event->data['submission_id'] ?? 'unknown';
        $hashedSubmissionId = md5($submissionId);
        $domain = Str::after(config('app.url'), '://');

        // Create a unique Message-ID for each submission (without timestamp)
        $messageId = "<form-{$formId}-submission-{$hashedSubmissionId}@{$domain}>";

        // Add our custom Message-ID as X-Custom-Message-ID
        $message->getHeaders()->addTextHeader('X-Custom-Message-ID', $messageId);

        // Add X-Entity-Ref-ID header for Google+ notifications
        $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', $messageId);

        // Add References header with the same value as Message-ID
        // This ensures emails are only threaded by submission ID, not by form ID
        $message->getHeaders()->addTextHeader('References', $messageId);

        // Add a unique Thread-Index to further prevent grouping
        $threadIndex = base64_encode(pack('H*', md5($formId . $submissionId)));
        $message->getHeaders()->addTextHeader('Thread-Index', $threadIndex);
    }

    private function getMailData(): array
    {
        return [
            'emailContent' => $this->getEmailContent(),
            'fields' => $this->formatSubmissionData(),
            'form' => $this->event->form,
            'integrationData' => $this->integrationData,
            'noBranding' => $this->event->form->no_branding,
            'submission_id' => $this->getEncodedSubmissionId(),
        ];
    }

    private function getEmailContent(): string
    {
        $parser = new MentionParser($this->integrationData->email_content ?? '', $this->formatSubmissionData());
        return $parser->parse();
    }

    private function getEncodedSubmissionId(): ?string
    {
        $submissionId = $this->event->data['submission_id'] ?? null;
        if (!$submissionId) {
            return null;
        }

        return SubmissionUrlService::getSubmissionIdentifierById($this->event->form, $submissionId);
    }

    public static function validateEmail($email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
