<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserBlockRequest;
use App\Jobs\Template\GenerateTemplateJob;
use App\Models\Forms\Form;
use App\Models\User;
use App\Service\UserActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Laravel\Cashier\Cashier;

class AdminController extends Controller
{
    public const ADMIN_LOG_PREFIX = '[admin_action] ';

    public function __construct()
    {
        $this->middleware('moderator');
    }

    public function createTemplate(Request $request)
    {
        $request->validate([
            'template_prompt' => 'required|string|max:4000'
        ]);

        $job = new GenerateTemplateJob($request->template_prompt);
        $job->handle();

        return $this->success([
            'template_slug' => $job->generatedTemplate?->slug ?? null
        ]);
    }

    public function fetchUser($identifier)
    {
        $user = null;
        if (is_numeric($identifier)) {
            $user = User::find($identifier);
        } elseif (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::whereEmail($identifier)->first();
        } else {
            // Find by form slug
            $form = Form::whereSlug($identifier)->first();
            if ($form) {
                $user = $form->creator;
            }
        }

        if (!$user) {
            return $this->error([
                'message' => 'User not found.'
            ]);
        } elseif ($user->admin) {
            return $this->error([
                'message' => 'You cannot fetch an admin.'
            ]);
        }

        $user->makeVisible('meta');

        // Get two-factor authentication status
        $user->two_factor_enabled = $user->hasTwoFactorEnabled();

        $workspaces = $user->workspaces()
            ->withCount('forms')
            ->get()
            ->map(function ($workspace) {
                $plan = 'free';
                if ($workspace->is_trialing) {
                    $plan = 'trialing';
                }
                if ($workspace->is_pro) {
                    $plan = 'pro';
                }
                if ($workspace->is_enterprise) {
                    $plan = 'enterprise';
                }
                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'plan' => $plan,
                    'forms_count' => $workspace->forms_count
                ];
            });
        return $this->success([
            'user' => $user,
            'workspaces' => $workspaces
        ]);
    }

    public function blockUser(UserBlockRequest $request, UserActionService $userActionService)
    {
        $user = User::findOrFail($request->get('user_id'));

        if ($user->admin) {
            return $this->error([
                'message' => 'You cannot block an admin.'
            ]);
        }

        $user = $userActionService->block(
            $user,
            $request->get('reason'),
            request()->user()->id
        );

        return $this->success([
            "message" => "User has been blocked.",
            "user" => $user->makeVisible('meta'),
        ]);
    }

    public function unblockUser(UserBlockRequest $request, UserActionService $userActionService)
    {
        $user = User::findOrFail($request->get('user_id'));

        $user = $userActionService->unblock(
            $user,
            $request->get('reason'),
            request()->user()->id
        );

        return $this->success([
            "message" => "User has been unblocked.",
            "user" => $user->makeVisible('meta'),
        ]);
    }

    public function applyDiscount(Request $request)
    {
        $request->validate([
            'user_id' => 'required'
        ]);
        $user = User::find($request->get("user_id"));

        $activeSubscriptions = $user->subscriptions()->where(function ($q) {
            $q->where('stripe_status', 'trialing')
                ->orWhere('stripe_status', 'active');
        })->get();

        if ($activeSubscriptions->count() != 1) {
            return $this->error([
                "message" => "The user has more than one active subscriptions or doesn't have one."
            ]);
        }

        $couponId = config('pricing.discount_coupon_id');
        if (is_null($couponId)) {
            return $this->error([
                "message" => "Coupon id not defined."
            ]);
        }

        $subscription = $activeSubscriptions->first();
        Cashier::stripe()->subscriptions->update($subscription->stripe_id, [
            'coupon' => $couponId
        ]);

        self::log('Applying NGO/Student discount to sub', [
            'user_id' => $user->id,
            'subcription_id' => $subscription->id,
            'coupon_id' => $couponId,
            'subscription_stripe_id' => $subscription->stripe_id,
        ]);

        return $this->success([
            "message" => "40% Discount applied for the next 12 months."
        ]);
    }

    public function extendTrial(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'number_of_day' => 'required|numeric|max:14'
        ]);
        $user = User::find($request->get("user_id"));

        $subscription = $user->subscriptions()
            ->where('stripe_status', 'trialing')
            ->firstOrFail();

        $trialEndDate = now()->addDays($request->get('number_of_day'));
        $subscription->extendTrial($trialEndDate);

        self::log('Trial extended', [
            'user_id' => $user->id,
            'subcription_id' => $subscription->id,
            'nb_days' => $request->get('number_of_day'),
            'subscription_stripe_id' => $subscription->stripe_id,
        ]);

        return $this->success([
            "message" => "Subscription trial extend until the " . $trialEndDate->format('d/m/Y')
        ]);
    }

    public function cancelSubscription(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'subscription_id' => 'required',
            'cancellation_reason' => 'required'
        ]);
        $user = User::find($request->get("user_id"));
        $subscription = $user->subscriptions()->find($request->get("subscription_id"));

        if (! $subscription) {
            return $this->error([
                "message" => "Subscription not found."
            ], 404);
        }

        if ($subscription && !in_array($subscription->stripe_status, ['active', 'trialing'])) {
            return $this->error([
                "message" => "The subscription is not active or trialing."
            ]);
        }

        // Cancel the subscription
        $subscription->cancel();

        self::log('Cancel Subscription', [
            'user_id' => $user->id,
            'cancel_reason' => $request->get('cancellation_reason'),
            'subcription_id' => $subscription->id,
            'subscription_stripe_id' => $subscription->stripe_id
        ]);

        return $this->success([
            "message" => "The subscription cancellation has been successfully completed."
        ]);
    }

    public function sendPasswordResetEmail(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error([
                'message' => "Password reset email failed to send"
            ]);
        }

        self::log('Sent password reset email', [
            'user_id' => $user->id,
        ]);

        return $this->success([
            'message' => "Password reset email has been sent to the user's email address"
        ]);
    }

    public function refundPayment(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'invoice_id' => 'required',
            'refund_reason' => 'required'
        ]);

        $user = User::findOrFail($request->get('user_id'));
        $latestInvoice = $user->invoices()->first();

        if (!$latestInvoice) {
            return $this->error(['message' => 'No invoices found for this user.'], 404);
        }

        if ($latestInvoice->id !== $request->get('invoice_id')) {
            return $this->error(['message' => 'You can only refund the last invoice.'], 422);
        }

        try {
            // Get the Stripe invoice to find the payment
            $stripeInvoice = Cashier::stripe()->invoices->retrieve($latestInvoice->id);
            if (!$stripeInvoice->charge) {
                return $this->error(['message' => 'It\'s trial period invoice so can not refund.'], 404);
            }

            $refund = Cashier::stripe()->refunds->create([
                'charge' => $stripeInvoice->charge,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'refund_reason' => $request->get('refund_reason'),
                    'moderator_id' => request()->user()->id,
                    'user_id' => $user->id
                ]
            ]);

            self::log('Refund Payment', [
                'user_id' => $user->id,
                'invoice_id' => $latestInvoice->id,
                'stripe_refund_id' => $refund->id,
                'refund_reason' => $request->get('refund_reason'),
            ]);
        } catch (\Exception $e) {
            self::log('Refund Error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error(['message' => 'An error occurred while processing the refund.'], 500);
        }

        return $this->success([
            'message' => "The payment has been successfully refunded."
        ]);
    }

    public function disableTwoFactorAuthentication(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'reason' => 'required'
        ]);

        $user = User::findOrFail($request->get('user_id'));

        if ($user->admin) {
            return $this->error([
                'message' => 'You cannot disable 2FA for an admin.'
            ]);
        }

        if (!$user->hasTwoFactorEnabled()) {
            return $this->error([
                'message' => "Two-factor authentication is not enabled."
            ]);
        }

        // Disable 2FA
        $user->disableTwoFactorAuth();

        self::log('Disable Two-Factor Authentication ', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reason' => $request->get('reason')
        ]);

        $user->two_factor_enabled = false;

        return $this->success([
            'message' => "Two-factor authentication has been disabled successfully.",
            'user' => $user->makeVisible('meta')
        ]);
    }

    public static function log($message, $data = [])
    {
        $moderator = request()->user();

        // Always include moderator information
        $baseData = [
            'moderator' => $moderator->email . ' (' . $moderator->id . ')',
        ];

        // Add action button for admin panel if user_id or target_id is present
        if (isset($data['user_id'])) {
            $baseData['actions'] = [
                'Open Admin' => front_url('/settings/admin?user_id=' . $data['user_id'])
            ];
        } elseif (isset($data['target_id'])) {
            $baseData['actions'] = [
                'Open Admin' => front_url('/settings/admin?user_id=' . $data['target_id'])
            ];
        }

        // Merge with provided data (provided data takes precedence)
        $logData = array_merge($baseData, $data);

        Log::warning(self::ADMIN_LOG_PREFIX . $message, $data);
        Log::channel('slack_admin')->warning($message, $logData);
    }
}
