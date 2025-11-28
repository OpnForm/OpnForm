<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\User;
use App\Service\AI\Prompts\Form\CheckSpamFormPrompt;
use App\Service\UserActionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FormSpamService
{
    public function __construct(protected UserActionService $userActionService)
    {
    }

    public function checkForm(Form $form): void
    {
        if (!config('spam.enabled') || !$this->shouldCheck($form)) {
            return;
        }

        try {
            $result = CheckSpamFormPrompt::run($form);

            if ($result['is_spam']) {
                $this->userActionService->block($form->creator, $result['reason'] ?? 'Your form was detected as spam', null);
            } elseif ($result['needs_admin_review'] ?? false) {
                $this->logAdminReview($form, $result['reason'] ?? 'Form flagged for admin review');
            }
        } catch (\Exception $e) {
            Log::error('Failed to check form for spam.', [
                'form_id' => $form->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function shouldCheck(Form $form): bool
    {
        // Check if creator exists before accessing its properties
        if (!$form->creator) {
            return false;
        }

        if ($form->creator->is_blocked || $form->creator->admin || $form->creator->moderator) {
            return false;
        }

        if ($form->creator->created_at->diffInMonths(now()) > 3) {
            return false;
        }

        // Skip checking users who were manually unblocked by admin in the past 7 days
        // This saves AI costs and prevents immediate re-blocking of reviewed users
        if ($this->wasRecentlyManuallyUnblocked($form->creator, 7)) {
            return false;
        }

        if ($this->containsKeywords($form)) {
            return true;
        }

        if ($this->isRiskyUser($form->creator)) {
            return true;
        }

        // Random check for other users
        return (rand(1, 100) <= config('spam.random_check_percentage', 0));
    }

    private function isRiskyUser(User $user): bool
    {
        // Example: Free user registered in the last N days
        return !$user->is_subscribed && $user->created_at->diffInDays(now()) <= config('spam.risky_user_days', 7);
    }

    private function containsKeywords(Form $form): bool
    {
        $content = json_encode($form->fields) . ' ' . $form->title . ' ' . $form->description;
        $content = strtolower($content);
        $keywords = config('spam.keywords', []);

        foreach ($keywords as $keyword) {
            if (str_contains($content, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user was manually unblocked by admin within the specified days
     */
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

    private function logAdminReview(Form $form, string $reason): void
    {
        $cacheKey = "form_admin_review_flagged_{$form->id}";

        // Check if we've already flagged this form in the last 24 hours
        if (Cache::has($cacheKey)) {
            Log::info('Skipping duplicate admin review alert for form', [
                'form_id' => $form->id,
                'reason' => 'Already flagged within 24 hours',
            ]);
            return;
        }

        // Cache this form as flagged for 24 hours
        Cache::put($cacheKey, true, now()->addDay());

        Log::channel('slack_alerts')->info('🚨 Form flagged for admin review 🚨', [
            'form_id' => $form->id,
            'form_title' => $form->title,
            'user_id' => $form->creator->id,
            'user_email' => $form->creator->email,
            'user_registered_days_ago' => $form->creator->created_at->diffInDays(now()),
            'is_subscribed' => $form->creator->is_subscribed,
            'total_forms' => $form->creator->forms()->count(),
            'reason' => $reason,
            'actions' => [
                'View Form' => front_url('/forms/' . $form->slug),
                'Admin Panel' => front_url('/admin?user_id=' . $form->creator->id),
            ],
        ]);

        Log::info('Form flagged for admin review', [
            'form_id' => $form->id,
            'form_title' => $form->title,
            'user_id' => $form->creator->id,
            'reason' => $reason,
        ]);
    }
}
