<?php

namespace App\Service\Integrations;

use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use App\Models\User;
use App\Service\AI\Prompts\Integration\CheckSpamEmailIntegrationPrompt;
use App\Service\UserActionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailIntegrationSpamService
{
    public function __construct(protected UserActionService $userActionService)
    {
    }

    public function checkForSpam(Form $form, FormIntegration $integration): ?array
    {
        if (!$this->shouldCheck($form, $integration)) {
            return null;
        }

        try {
            $result = CheckSpamEmailIntegrationPrompt::run($form, $integration);

            if ($result['is_spam'] ?? false) {
                $this->userActionService->block(
                    $form->creator,
                    $result['reason'] ?? 'Your email integration was detected as spam/phishing',
                    null
                );
            } elseif ($result['needs_admin_review'] ?? false) {
                $this->logAdminReview($integration, $result['reason'] ?? 'Email integration flagged for admin review');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to check email integration for spam.', [
                'form_id' => $form->id,
                'integration_id' => $integration->id,
                'user_id' => $form->creator->id,
                'user_email' => $form->creator->email,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function shouldCheck(Form $form, FormIntegration $integration): bool
    {
        // Skip if globally disabled
        if (!config('spam.enabled')) {
            return false;
        }

        // Get creator from form
        $creator = $form->creator;

        // Check if creator exists before accessing its properties
        if (!$creator) {
            return false;
        }

        if ($creator->is_blocked || $creator->admin || $creator->moderator) {
            return false;
        }

        // Skip if integration is not active
        if ($integration->status !== FormIntegration::STATUS_ACTIVE) {
            return false;
        }

        // Skip if send to is not the creator's email
        $data = (array) ($integration->data ?? (object) []);
        if (!$data || !isset($data['send_to']) || $data['send_to'] !== $creator->email) {
            return false;
        }

        // Skip if user account is too old (user is established)
        if ($creator->created_at->diffInMonths(now()) > 3) {
            return false;
        }

        // Skip checking users who were manually unblocked by admin in the past 7 days
        // This saves AI costs and prevents immediate re-blocking of reviewed users
        if ($this->wasRecentlyManuallyUnblocked($creator, 7)) {
            return false;
        }

        // Check integration content for obvious spam keywords first (cost optimization)
        if ($this->containsKeywords($integration)) {
            return true;
        }

        // Check risky users (unsubscribed + new)
        if ($this->isRiskyUser($creator)) {
            return true;
        }

        // Random check for other users
        return (rand(1, 100) <= config('spam.random_check_percentage', 0));
    }

    private function isRiskyUser(User $user): bool
    {
        // Free user registered in the last N days
        return !$user->is_subscribed && $user->created_at->diffInDays(now()) <= config('spam.risky_user_days', 7);
    }

    private function containsKeywords(FormIntegration $integration): bool
    {
        $data = (array) ($integration->data ?? (object) []);
        $content = strtolower(
            json_encode($data) . ' ' .
                ($data['subject'] ?? '') . ' ' .
                ($data['email_content'] ?? '')
        );

        $keywords = config('spam.keywords', []);

        foreach ($keywords as $keyword) {
            if (str_contains($content, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function wasRecentlyManuallyUnblocked(User $user, int $days = 7): bool
    {
        $history = $user->meta['blocking_history'] ?? [];
        if (empty($history)) {
            return false;
        }

        $recentDate = now()->subDays($days);

        foreach (array_reverse($history) as $block) {
            // Check if this block was manually unblocked
            if (
                !is_null($block['unblocked_by']) &&
                !is_null($block['unblocked_at']) &&
                \Carbon\Carbon::parse($block['unblocked_at'])->isAfter($recentDate)
            ) {
                return true;
            }
        }

        return false;
    }

    private function logAdminReview(FormIntegration $integration, string $reason): void
    {
        $cacheKey = "integration_admin_review_flagged_{$integration->id}";

        // Check if we've already flagged this integration in the last 24 hours
        if (Cache::has($cacheKey)) {
            Log::info('Skipping duplicate admin review alert for integration', [
                'integration_id' => $integration->id,
                'reason' => 'Already flagged within 24 hours',
            ]);
            return;
        }

        // Cache this integration as flagged for 24 hours
        Cache::put($cacheKey, true, now()->addDay());

        Log::channel('slack_alerts')->info('🚨 Email integration flagged for admin review 🚨', [
            'integration_id' => $integration->id,
            'form_id' => $integration->form_id,
            'form_title' => $integration->form->title,
            'user_id' => $integration->form->creator->id,
            'user_email' => $integration->form->creator->email,
            'user_registered_days_ago' => $integration->form->creator->created_at->diffInDays(now()),
            'is_subscribed' => $integration->form->creator->is_subscribed,
            'total_forms' => $integration->form->creator->forms()->count(),
            'reason' => $reason,
            'actions' => [
                'View Form' => front_url('/forms/' . $integration->form->slug),
                'Admin Panel' => front_url('/admin?user_id=' . $integration->form->creator->id),
            ],
        ]);

        Log::info('Email integration flagged for admin review', [
            'integration_id' => $integration->id,
            'form_id' => $integration->form_id,
            'user_id' => $integration->form->creator->id,
            'reason' => $reason,
        ]);
    }
}
