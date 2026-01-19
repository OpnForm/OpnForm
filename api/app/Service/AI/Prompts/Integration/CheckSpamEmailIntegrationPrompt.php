<?php

namespace App\Service\AI\Prompts\Integration;

use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use App\Service\AI\Prompts\Prompt;
use Illuminate\Support\Str;

class CheckSpamEmailIntegrationPrompt extends Prompt
{
    protected ?float $temperature = 0.2;
    protected ?int $maxTokens = 500;
    protected string $model = 'gpt-4.1-mini';

    public const PROMPT_TEMPLATE = <<<'EOD'
        Analyze this email notification integration for spam/phishing or unrelated content.

        <emailIntegration>
        Subject: {subject}
        Body:
        {emailContent}
        Includes submission data: {includesSubmissionData}
        Reply-to: {replyTo}
        Link edit submission: {linkEditSubmission}
        </emailIntegration>

        <formContext>
        Form title: {formTitle}
        Form description: {formDescription}
        Form appears to be default/dummy: {isDefaultForm}
        </formContext>

        <userInformation>
        User registered: {userRegisteredSince} days ago.
        User is subscribed: {isSubscribed}
        </userInformation>

        {blockingHistory}

        **IMPORTANT: MENTIONS/PLACEHOLDERS ARE LEGITIMATE FEATURES**
        Email subjects and content may contain HTML mention tags like:
        <span mention-field-id="xxx" mention-field-name="Name">Name</span>
        These are LEGITIMATE form field placeholders that personalize emails with submitted data.
        Using mentions/placeholders in subjects is a NORMAL and EXPECTED feature - NOT a spam indicator!

        **CRITICAL ABUSE PATTERN - HIGH PRIORITY:**
        🚨 Watch for: Default/dummy forms (e.g., "Contact Form" with no description) paired with phishing emails.
        This is a common abuse pattern: attacker creates dummy form, then sets up phishing email integration to mass-send to random people.

        **BLOCK IMMEDIATELY (is_spam: true):**
        - Phishing or brand impersonation content (login pages, credential harvesting, fake support)
        - Clear mismatch: default/generic form with targeted phishing email containing malicious content
        - Emails designed to trick recipients into providing credentials or sensitive information

        **FLAG FOR ADMIN REVIEW (needs_admin_review: true) - RARE CASES ONLY:**
        - Emails with suspicious brand impersonation but unclear intent
        - Content that appears designed to deceive recipients about sender identity

        **ALLOW (both false) - DEFAULT FOR MOST INTEGRATIONS:**
        - Email content matches form context (e.g., contact form with contact confirmation email)
        - Emails using mentions/placeholders to personalize subject or content (this is a feature!)
        - New users or free users with normal email integrations
        - Generic notification emails, thank you emails, confirmation emails
        - Being unsubscribed or a new user is NOT a spam indicator by itself

        Respond with JSON: {"is_spam": boolean, "needs_admin_review": boolean, "reason": "brief explanation"}
    EOD;

    protected ?array $jsonSchema = [
        'type' => 'object',
        'required' => ['is_spam', 'needs_admin_review', 'reason'],
        'additionalProperties' => false,
        'properties' => [
            'is_spam' => [
                'type' => 'boolean',
            ],
            'needs_admin_review' => [
                'type' => 'boolean',
            ],
            'reason' => [
                'type' => 'string',
            ],
        ],
    ];

    public function __construct(
        public Form $form,
        public FormIntegration $integration
    ) {
        parent::__construct();
    }

    protected function getPromptTemplate(): string
    {
        return self::PROMPT_TEMPLATE;
    }

    protected function buildPrompt(): string
    {
        $template = $this->getPromptTemplate();
        $data = (array) ($this->integration->data ?? (object) []);

        $subject = (string) ($data['subject'] ?? '');
        $emailContent = (string) ($data['email_content'] ?? '');
        $includesSubmissionData = isset($data['include_submission_data']) && $data['include_submission_data'] ? 'yes' : 'no';
        $replyTo = (string) ($data['reply_to'] ?? '');
        $linkEditSubmission = isset($data['link_edit_submission']) && $data['link_edit_submission'] ? 'yes' : 'no';

        $userRegisteredSince = $this->form->creator->created_at->diffInDays(now());
        $isSubscribed = $this->form->creator->is_subscribed ? 'yes' : 'no';
        $isDefaultForm = $this->isDefaultDummyForm() ? 'yes' : 'no';

        $blockingHistory = $this->buildBlockingHistoryContext();

        return Str::of($template)
            ->replace('{subject}', $subject)
            ->replace('{emailContent}', $emailContent)
            ->replace('{includesSubmissionData}', $includesSubmissionData)
            ->replace('{replyTo}', $replyTo)
            ->replace('{linkEditSubmission}', $linkEditSubmission)
            ->replace('{formTitle}', (string) $this->form->title)
            ->replace('{formDescription}', (string) $this->form->description)
            ->replace('{userRegisteredSince}', (string) $userRegisteredSince)
            ->replace('{isSubscribed}', $isSubscribed)
            ->replace('{isDefaultForm}', $isDefaultForm)
            ->replace('{blockingHistory}', $blockingHistory)
            ->toString();
    }

    private function isDefaultDummyForm(): bool
    {
        // Detect if form appears to be a default/dummy form (hasn't been customized)
        $form = $this->form;

        // Check if title is the default contact form title and description is empty/default
        $isDefaultTitle = $form->title === 'Contact Form' || $form->title === 'Untitled Form';
        $hasNoDescription = empty($form->description) || $form->description === '';

        // Check if form has very few fields (just the default ones)
        $defaultFieldCount = count($form->properties ?? []) <= 4;

        return $isDefaultTitle && $hasNoDescription && $defaultFieldCount;
    }

    private function buildBlockingHistoryContext(): string
    {
        $blockingHistory = $this->form->creator->meta['blocking_history'] ?? [];

        if (empty($blockingHistory)) {
            return '';
        }

        $lastBlock = end($blockingHistory);
        if (!$lastBlock) {
            return '';
        }

        // Only mention if user was recently manually unblocked
        if (!is_null($lastBlock['unblocked_by']) && !is_null($lastBlock['unblocked_at'])) {
            $daysSinceUnblock = \Carbon\Carbon::parse($lastBlock['unblocked_at'])->diffInDays(now());

            if ($daysSinceUnblock <= 30) {
                return "
**⚠️ USER WAS MANUALLY UNBLOCKED BY ADMIN:**
This user was unblocked {$daysSinceUnblock} days ago. Respect prior admin decision - only re-block for EXTREME violations (obvious phishing/credential theft).
Use 'needs_admin_review' instead if borderline.
";
            }
        }

        return '';
    }
}
