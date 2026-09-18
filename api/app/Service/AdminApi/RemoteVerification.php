<?php

namespace App\Service\AdminApi;

use App\Models\AdminApiAction;
use App\Models\Template;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Cashier\Cashier;

class RemoteVerification
{
    public function reconcile(AdminApiAction $action, array $snapshot): bool
    {
        $confirmed = $this->confirmed($action, $snapshot);
        if ($confirmed && $action->status === 'verifying') {
            \Illuminate\Support\Facades\DB::transaction(function () use ($action) {
                if (AdminApiAction::whereKey($action->id)->where('status', 'verifying')->update(['status' => 'completed'])) {
                    \Illuminate\Support\Facades\DB::table('admin_api_action_locks')->where('action_id', $action->id)->delete();
                }
            });
            $action->refresh();
        }
        return $confirmed;
    }

    public function confirmed(AdminApiAction $action, array $snapshot): bool
    {
        $input = $action->payload;
        $state = $snapshot['state'];
        return match ($action->operation) {
            'block-user' => $state['blocked'] === true && $state['forms']['non_draft_count'] === 0,
            'unblock-user' => $state['blocked'] === false && $state['forms']['pending_restore_count'] === 0,
            'disable-two-factor-authentication' => $state['two_factor_enabled'] === false,
            'restore-form' => $state['deleted_at'] === null,
            'create-template' => isset($action->result['template_slug']) && Template::where('slug', $action->result['template_slug'])->exists(),
            'update-customer' => $state['billing']['name'] === $input['billing_name'] && $state['billing']['email'] === $input['billing_email'],
            'apply-discount' => collect($state['billing']['subscriptions'])->contains(fn ($sub) => $sub['id'] === ($action->result['subscription_id'] ?? null) && in_array($sub['status'], ['active', 'trialing'], true) && isset($action->result['coupon_id']) && in_array($action->result['coupon_id'], $sub['discounts'], true)),
            'extend-trial' => collect($state['billing']['subscriptions'])->contains(fn ($sub) => $sub['id'] === ($action->result['subscription_id'] ?? null) && $sub['trial_end'] === Carbon::parse($input['trial_ends_at'])->timestamp),
            'cancel-subscription' => $this->cancellationConfirmed($input, $state),
            'refund-payment' => $this->refundConfirmed($action),
            // These are command acknowledgments, not claims of email delivery or persistent cache state.
            'send-password-reset-email', 'clear-user-cache' => true,
            default => false,
        };
    }

    private function refundConfirmed(AdminApiAction $action): bool
    {
        if (!isset($action->result['refund_id'])) {
            return false;
        }
        $refund = Cashier::stripe()->refunds->retrieve($action->result['refund_id']);
        return $refund->status === 'succeeded' && $refund->charge === ($action->result['charge_id'] ?? null) && $refund->amount === $action->payload['expected_amount']
            && $refund->currency === $action->payload['expected_currency'];
    }

    private function cancellationConfirmed(array $input, array $state): bool
    {
        $subscription = User::findOrFail($input['user_id'])->subscriptions()->findOrFail($input['subscription_id']);
        return collect($state['billing']['subscriptions'])->contains(fn ($sub) => $sub['id'] === $subscription->stripe_id && ($sub['cancel_at_period_end'] || $sub['status'] === 'canceled'));
    }
}
