<?php

namespace App\Service\AI\Prompts\Form;

use App\Models\Forms\Form;
use App\Service\AI\Prompts\Prompt;
use Illuminate\Support\Str;

class CheckSpamFormPrompt extends Prompt
{
    protected ?float $temperature = 0.2;
    protected ?int $maxTokens = 500;
    protected string $model = 'gpt-4.1-mini';

    public const PROMPT_TEMPLATE = <<<'EOD'
        {blockingConsiderations}

        Analyze this form for spam/phishing. Focus on CONTENT RISK, not user characteristics.

        **CRITICAL: Focus on FORM FIELD CONTENT, not the title!**
        - Form titles are often generic/default and users frequently forget to change them
        - Titles like "Untitled Form", "New Form", or template names are COMMON and NOT suspicious
        - Pay attention to actual field names, placeholders, field types, and descriptions
        - The real spam indicators are in WHAT fields are being collected, not the form title

        <formContent>
        {formContent}
        </formContent>

        <userInformation>
        User registered: {userRegisteredSince} days ago.
        User is subscribed: {isSubscribed}
        Total forms created: {totalFormsCreated}
        </userInformation>

        **BLOCK IMMEDIATELY (is_spam: true):**
        - Login forms impersonating major services (Gmail, PayPal, Facebook, Outlook, etc.)
        - Forms asking for existing passwords/credentials to "verify" accounts or for fake login
        - Clear brand impersonation with sensitive data collection

        **FLAG FOR ADMIN REVIEW (needs_admin_review: true) - RARE CASES ONLY:**
        - Forms with suspicious brand impersonation but unclear intent
        - Requests for highly sensitive data (SSN, banking details) without clear business purpose
        - Forms that appear to be testing system vulnerabilities

        **ALLOW (both false) - DEFAULT FOR MOST FORMS:**
        - Business inquiry forms, contact forms, surveys
        - Forms in any language (Arabic, Spanish, etc. are legitimate)
        - Forms from new users (being new is NOT suspicious)
        - Adult content related forms (unless clearly illegal)
        - Vague or simple forms (common for legitimate use cases)
        - New account creation forms (password setup normal)
        - Surveys, event codes, referral forms
        - General contact/support forms

        **IMPORTANT GUIDELINES:**
        - **IGNORE generic/default titles** - "Untitled Form", "New Form", template names are normal
        - **Focus on FIELD CONTENT** - field names, placeholders, and what data is being collected
        - User age/registration date is NOT a spam indicator
        - Non-English content is NOT suspicious
        - Adult content is acceptable unless clearly illegal
        - Vague or generic titles are common and legitimate - do NOT flag based on title alone
        - Login forms = phishing, Registration forms = often legitimate
        - Subscribed users get benefit of doubt
        - When uncertain, prefer allowing over flagging
        - Title alone should NEVER be the deciding factor - always examine actual form fields

        Classify as: SPAM (block), ADMIN REVIEW (flag), or LEGITIMATE (allow).
        Respond with JSON: {"is_spam": boolean, "needs_admin_review": boolean, "reason": "explanation"}
    EOD;

    protected ?array $jsonSchema = [
        'type' => 'object',
        'required' => ['is_spam', 'needs_admin_review', 'reason'],
        'additionalProperties' => false,
        'properties' => [
            'is_spam' => [
                'type' => 'boolean',
                'description' => 'True if the form is considered spam/scam, false otherwise.'
            ],
            'needs_admin_review' => [
                'type' => 'boolean',
                'description' => 'True if the form is borderline suspicious and needs admin review but should not be automatically blocked. Only set when is_spam is false.'
            ],
            'reason' => [
                'type' => 'string',
                'description' => 'A brief explanation for your decision.'
            ]
        ]
    ];

    public function __construct(
        public Form $form
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
        $formContent = $this->extractFormContent();
        $userRegisteredSince = $this->form->creator->created_at->diffInDays(now());
        $isSubscribed = $this->form->creator->is_subscribed ? 'yes' : 'no';
        $totalFormsCreated = $this->form->creator->forms()->count();

        // Check if user has blocking history
        $hasBlockingHistory = !empty($this->form->creator->meta['blocking_history'] ?? []);
        $blockingConsiderations = '';

        if ($hasBlockingHistory) {
            $history = $this->form->creator->meta['blocking_history'];
            $lastBlock = end($history);
            $daysSinceUnblock = null;
            $recentUnblockInfo = '';

            // Check if user was recently manually unblocked
            if ($lastBlock && !is_null($lastBlock['unblocked_by']) && !is_null($lastBlock['unblocked_at'])) {
                $daysSinceUnblock = \Carbon\Carbon::parse($lastBlock['unblocked_at'])->diffInDays(now());

                if ($daysSinceUnblock <= 30) {
                    $recentUnblockInfo = "
**⚠️ RECENT MANUAL UNBLOCK ALERT:**
This user was manually unblocked {$daysSinceUnblock} days ago by admin ID {$lastBlock['unblocked_by']}.
Reason for unblock: \"{$lastBlock['unblock_reason']}\"

**EXTREMELY HIGH threshold required for re-blocking!**
";
                }
            }

            $blockingHistory = $this->extractBlockingHistory();

            $blockingConsiderations = "**🚨 CRITICAL: BLOCKING HISTORY OVERRIDE RULES 🚨**

THIS USER HAS BLOCKING HISTORY - READ CAREFULLY:

**ABSOLUTE RULES FOR PREVIOUSLY UNBLOCKED USERS:**
1. ⛔ **NEVER re-block unless EXTREME violation** (login credential theft, clear brand impersonation with malicious intent)
2. 🔒 **Manual admin unblocks are SACRED** - an admin already reviewed and approved this user
3. ⚖️ **When in doubt, use 'needs_admin_review' instead of 'is_spam'** - let humans decide
4. 🎯 **Only block for OBVIOUS phishing** - password collection, fake login pages, credential harvesting

**DECISION PRIORITY:**
1. 🟢 ALLOW (both false) - Default for previously unblocked users
2. 🟡 ADMIN REVIEW (needs_admin_review: true) - Only if genuinely suspicious but not clearly malicious  
3. 🔴 BLOCK (is_spam: true) - ONLY for extreme violations that endanger other users

{$recentUnblockInfo}

**USER BLOCKING HISTORY:**
{$blockingHistory}

";
        }

        return Str::of($template)
            ->replace('{blockingConsiderations}', $blockingConsiderations)
            ->replace('{formContent}', $formContent)
            ->replace('{userRegisteredSince}', $userRegisteredSince)
            ->replace('{isSubscribed}', $isSubscribed)
            ->replace('{totalFormsCreated}', $totalFormsCreated)
            ->toString();
    }

    private function extractFormContent(): string
    {
        $content = [];
        $content[] = "Title: " . $this->form->title;
        $content[] = "Description: " . $this->form->description;

        foreach ($this->form->properties as $field) {
            $content[] = "- Field Name: " . ($field['name'] ?? 'N/A');
            $content[] = "  Field Type: " . ($field['type'] ?? 'N/A');
            $content[] = "  Placeholder: " . ($field['placeholder'] ?? 'N/A');
        }

        return implode("\n", $content);
    }

    private function extractBlockingHistory(): string
    {
        $history = $this->form->creator->meta['blocking_history'] ?? [];

        if (empty($history)) {
            return "No previous blocking history - clean record.";
        }

        $totalBlocks = count($history);
        $manualUnblocks = collect($history)->filter(fn ($block) => !is_null($block['unblocked_by']))->count();

        $summary = [];
        $summary[] = "📊 BLOCKING SUMMARY:";
        $summary[] = "• Total blocks: {$totalBlocks}";
        $summary[] = "• Manual admin unblocks: {$manualUnblocks}";

        if ($manualUnblocks > 0) {
            $summary[] = "🔓 CRITICAL: {$manualUnblocks} manual admin review(s) - user was APPROVED after human review";
        }

        // Get the most recent block details
        $lastBlock = end($history);
        if ($lastBlock) {
            $summary[] = "";
            $summary[] = "📋 MOST RECENT BLOCK:";
            $summary[] = "• Block reason: " . ($lastBlock['reason'] ?? 'N/A');
            $summary[] = "• Blocked at: " . ($lastBlock['blocked_at'] ?? 'N/A');

            if ($lastBlock['unblocked_by']) {
                $summary[] = "• 🔓 MANUALLY UNBLOCKED by admin ID: " . $lastBlock['unblocked_by'];
                $summary[] = "• Unblock reason: " . ($lastBlock['unblock_reason'] ?? 'N/A');
                $summary[] = "• Unblocked at: " . ($lastBlock['unblocked_at'] ?? 'N/A');
                $summary[] = "🟢 STATUS: User was manually reviewed and APPROVED by admin";
            } else {
                $summary[] = "🔴 STATUS: Block is still active or auto-resolved";
            }
        }

        return implode("\n", $summary);
    }
}
