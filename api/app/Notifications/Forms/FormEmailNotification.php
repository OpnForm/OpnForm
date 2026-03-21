<?php

namespace App\Notifications\Forms;

use App\Events\Forms\FormSubmitted;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
use App\Open\MentionParser;
use App\Service\Billing\Feature;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Forms\SubmissionUrlService;
use App\Service\Formulas\ComputedVariableEvaluator;
use App\Service\Pdf\PdfCacheService;
use App\Service\Pdf\PdfGeneratorService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class FormEmailNotification extends Notification
{
    private const MAX_PDF_ATTACHMENTS = 3;
    private const MAX_TOTAL_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    public FormSubmitted $event;
    private ?array $computedValues = null;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(FormSubmitted $event, private $integrationData)
    {
        $this->event = $event;
    }

    /**
     * Get computed variable values for this submission
     */
    private function getComputedValues(): array
    {
        if ($this->computedValues === null) {
            $this->computedValues = ComputedVariableEvaluator::evaluateForSubmission(
                $this->event->form,
                $this->event->data
            );
        }
        return $this->computedValues;
    }

    private function getMailer(): string
    {
        $workspace = $this->event->form->workspace;
        $emailSettings = $workspace->settings['email_settings'] ?? [];

        if (
            $workspace->hasFeature(Feature::CUSTOM_SMTP)
            && $emailSettings
            && !empty($emailSettings['host'])
            && !empty($emailSettings['port'])
            && !empty($emailSettings['username'])
            && !empty($emailSettings['password'])
        ) {
            config([
                'mail.mailers.custom_smtp.host' => $emailSettings['host'],
                'mail.mailers.custom_smtp.port' => $emailSettings['port'],
                'mail.mailers.custom_smtp.encryption' => array_key_exists('encryption', $emailSettings) ? $emailSettings['encryption'] : 'tls',
                'mail.mailers.custom_smtp.username' => $emailSettings['username'],
                'mail.mailers.custom_smtp.password' => $emailSettings['password']
            ]);

            // Laravel caches mailer instances (and their transport) in long-lived processes
            // like queue workers (Horizon). If workspace SMTP settings change, we must purge
            // the cached mailer so the next send uses the updated config.
            Mail::purge('custom_smtp');
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
        $mail = (new MailMessage())
            ->mailer($this->getMailer())
            ->replyTo($this->getReplyToEmail($this->event->form->creator->email))
            ->from($this->getFromEmail(), $this->getSenderName())
            ->subject($this->getSubject())
            ->withSymfonyMessage(function (Email $message) {
                $this->addCustomHeaders($message);
            })
            ->markdown('mail.form.email-notification', $this->getMailData());

        $this->attachPdfTemplatesIfRequested($mail);

        return $mail;
    }

    /**
     * Attach PDFs for selected templates when integration has pdf_template_ids.
     */
    private function attachPdfTemplatesIfRequested(MailMessage $mail): void
    {
        $form = $this->event->form;

        $templateIds = $this->integrationData->pdf_template_ids ?? [];
        if (! is_array($templateIds) || count($templateIds) === 0) {
            return;
        }
        $templateIds = array_values(array_unique(array_slice($templateIds, 0, self::MAX_PDF_ATTACHMENTS)));

        $submissionId = $this->event->data['submission_id'] ?? null;
        if (! $submissionId) {
            return;
        }

        $submission = FormSubmission::where('form_id', $form->id)->where('id', $submissionId)->first();
        if (! $submission) {
            return;
        }

        $cacheService = app(PdfCacheService::class);
        $generator = app(PdfGeneratorService::class);
        $totalBytes = 0;

        foreach ($templateIds as $templateId) {
            $template = PdfTemplate::where('form_id', $form->id)->find($templateId);
            if (! $template) {
                continue;
            }
            try {
                $pdfPath = $cacheService->getOrGenerateFromTemplate($form, $submission, $template, $generator);
                $content = Storage::get($pdfPath);
                if ($content !== false) {
                    $nextSize = strlen($content);
                    if (($totalBytes + $nextSize) > self::MAX_TOTAL_ATTACHMENT_BYTES) {
                        logger()->warning('Skipping PDF email attachment: total size limit exceeded', [
                            'form_id' => $form->id,
                            'submission_id' => $submission->id,
                            'template_id' => $template->id,
                            'limit_bytes' => self::MAX_TOTAL_ATTACHMENT_BYTES,
                        ]);
                        continue;
                    }

                    $filename = $template->resolveFilename($form, $submission);
                    $mail->attachData($content, $filename, ['mime' => 'application/pdf']);
                    $totalBytes += $nextSize;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
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
            $workspace->hasFeature(Feature::CUSTOM_SMTP)
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
        $parser = new MentionParser(
            $this->integrationData->sender_name ?? config('app.name'),
            $this->formatSubmissionData(false),
            $this->getComputedValues()
        );
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
        $parser = new MentionParser($replyTo, $this->formatSubmissionData(false), $this->getComputedValues());
        return $parser->parseAsText();
    }

    private function getSubject(): string
    {
        $defaultSubject = 'New form submission';
        $parser = new MentionParser(
            $this->integrationData->subject ?? $defaultSubject,
            $this->formatSubmissionData(false),
            $this->getComputedValues()
        );
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
        $form = $this->event->form;
        $hasAdvancedEmailCustomization = $form->workspace?->hasFeature(Feature::EMAIL_ADVANCED) ?? false;

        $emailAppearance = $hasAdvancedEmailCustomization ? [
            'logoUrl' => $this->integrationData->logo_url ?? null,
            'fontFamily' => $this->integrationData->font_family ?? null,
            'fontColor' => $this->integrationData->font_color ?? null,
            'outerBackgroundColor' => $this->integrationData->outer_background_color ?? null,
            'innerBackgroundColor' => $this->integrationData->inner_background_color ?? null,
        ] : [];

        return [
            'emailContent' => $this->getEmailContent(),
            'fields' => $this->formatSubmissionData(),
            'form' => $form,
            'integrationData' => $this->integrationData,
            'noBranding' => $form->no_branding,
            'submission_id' => $this->getEncodedSubmissionId(),
            'emailAppearance' => $emailAppearance,
        ];
    }

    private function getEmailContent(): string
    {
        $parser = new MentionParser(
            $this->integrationData->email_content ?? '',
            $this->formatSubmissionData(),
            $this->getComputedValues()
        );
        $html = $parser->parse();

        return $this->convertResizeAlignmentToInlineStyles($html);
    }

    /**
     * Convert quill-resize-module CSS classes to inline styles for email client compatibility.
     * Email clients often strip class-based styles; inline styles are required.
     */
    private function convertResizeAlignmentToInlineStyles(string $html): string
    {
        $alignments = [
            'ql-resize-style-left' => 'float:left; margin:0 1em 1em 0;',
            'ql-resize-style-right' => 'float:right; margin:0 0 1em 1em;',
            'ql-resize-style-center' => 'display:block; margin:auto; text-align:center;',
            'ql-resize-style-full' => 'width:100% !important;',
        ];

        foreach ($alignments as $class => $inlineStyle) {
            $html = preg_replace_callback(
                '/<([a-z]+)([^>]*\bclass\s*=\s*["\']([^"\']*' . preg_quote($class, '/') . '[^"\']*)["\'])([^>]*)>/i',
                function ($m) use ($class, $inlineStyle) {
                    $tagName = $m[1];
                    $beforeClass = $m[2];
                    $classes = $m[3];
                    $after = $m[4];

                    $newClasses = preg_replace('/\s*' . preg_quote($class, '/') . '\s*/', ' ', $classes);
                    $newClasses = trim(preg_replace('/\s+/', ' ', $newClasses));

                    $style = $inlineStyle;
                    if (preg_match('/style\s*=\s*["\']([^"\']*)["\']/', $beforeClass . $after, $sm)) {
                        $style = $inlineStyle . $sm[1];
                        $beforeClass = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/', '', $beforeClass);
                        $after = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/', '', $after);
                    }

                    $beforeClass = preg_replace('/\s*class\s*=\s*["\'][^"\']*["\']/', '', $beforeClass);
                    $classAttr = $newClasses !== '' ? ' class="' . $newClasses . '"' : '';

                    return '<' . $tagName . $beforeClass . $classAttr . ' style="' . $style . '"' . $after . '>';
                },
                $html
            );
        }

        return $html;
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
