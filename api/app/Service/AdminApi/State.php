<?php

namespace App\Service\AdminApi;

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;

class State
{
    public function user(string $identifier): array
    {
        $user = is_numeric($identifier) ? User::find($identifier) : User::where('email', $identifier)->first();
        $user ??= Form::where('slug', $identifier)->first()?->creator;
        abort_unless($user, 404);
        abort_if($user->admin, 403, 'Admin accounts cannot be targeted.');
        return [
            'id' => $user->id, 'email' => $user->email, 'name' => $user->name,
            'blocked' => $user->is_blocked, 'blocked_at' => $user->blocked_at?->toIso8601String(),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'workspaces' => $user->workspaces()->orderBy('workspaces.id')->get()->map(fn ($workspace) => [
                'id' => $workspace->id, 'name' => $workspace->name,
                'plan' => $workspace->is_trialing ? 'trialing' : $workspace->plan_tier,
            ])->all(),
        ];
    }

    public function workspace(int $id): array
    {
        $workspace = Workspace::findOrFail($id);
        return ['id' => $workspace->id, 'name' => $workspace->name, 'plan' => $workspace->is_trialing ? 'trialing' : $workspace->plan_tier, 'forms_count' => $workspace->forms()->count()];
    }

    public function target(string $operation, array $input): string
    {
        Operations::scope($operation);
        if ($operation === 'create-template') {
            return 'templates';
        }
        if ($operation === 'restore-form') {
            $form = Form::withTrashed()->where('slug', $input['slug'] ?? '')->firstOrFail();
            abort_if($form->creator?->admin, 403);
            return 'user:'.$form->creator->id;
        }
        $user = User::findOrFail($input['user_id'] ?? null);
        abort_if($user->admin, 403, 'Admin accounts cannot be targeted.');
        return 'user:'.$user->id;
    }

    public function refundReceiptSnapshot(\App\Models\AdminApiAction $action): array
    {
        $target = $this->target($action->operation, $action->payload);
        $state = $this->user((string) $action->payload['user_id']) + ['target' => $target, 'invoice_id' => $action->payload['invoice_id']];
        return ['target' => $target, 'snapshot_hash' => hash('sha256', Operations::canonical($state)), 'state' => $state];
    }

    public function snapshot(string $operation, array $input): array
    {
        $target = $this->target($operation, $input);
        if ($operation === 'create-template') {
            $state = ['target' => $target];
        } elseif ($operation === 'restore-form') {
            $form = Form::withTrashed()->where('slug', $input['slug'])->firstOrFail();
            $state = ['target' => $target, 'id' => $form->id, 'slug' => $form->slug, 'deleted_at' => $form->deleted_at?->toIso8601String(), 'updated_at' => $form->updated_at?->toIso8601String()];
        } else {
            $user = User::findOrFail($input['user_id']);
            $state = $this->user((string) $user->id) + ['target' => $target];
            if (str_starts_with(Operations::scope($operation), 'admin:billing:')) {
                abort_unless($user->hasStripeId(), 422, 'No billing customer.');
                $customer = $user->asStripeCustomer();
                $state['billing'] = [
                    'customer_id' => $user->stripe_id, 'name' => $customer->name, 'email' => $customer->email,
                    'subscriptions' => app(\App\Service\Admin\AdminStripeState::class)->subscriptions($user),
                    'discount_coupon_id' => config('pricing.discount_coupon_id'),
                    'local_subscriptions' => $user->subscriptions()->orderBy('id')->get()->map(fn ($sub) => $sub->only(['id', 'stripe_id', 'stripe_status', 'trial_ends_at', 'ends_at']))->all(),
                ];
                if ($operation === 'refund-payment') {
                    $invoice = $user->invoices()->first();
                    $remote = $invoice?->asStripeInvoice();
                    $state['billing']['latest_invoice'] = $remote ? [
                        'id' => $remote->id, 'amount_paid' => $remote->amount_paid, 'currency' => $remote->currency,
                        'payment' => app(\App\Service\Admin\AdminStripeState::class)->refundablePayment($user, $remote),
                    ] : null;
                }
            }
            if (in_array($operation, ['block-user', 'unblock-user'], true)) {
                $hash = hash_init('sha256');
                $count = $notDraft = $pendingRestore = 0;
                foreach ($user->forms()->orderBy('id')->select(['id', 'visibility', 'tags', 'updated_at'])->cursor() as $form) {
                    hash_update($hash, Operations::canonical($form->only(['id', 'visibility', 'tags', 'updated_at'])));
                    $count++;
                    $notDraft += $form->visibility !== 'draft' ? 1 : 0;
                    $pendingRestore += collect($form->tags ?? [])->contains(fn ($tag) => str_starts_with($tag, 'previous-status-')) ? 1 : 0;
                }
                $state['forms'] = ['count' => $count, 'hash' => hash_final($hash), 'non_draft_count' => $notDraft, 'pending_restore_count' => $pendingRestore];
            }
        }
        return ['target' => $target, 'snapshot_hash' => hash('sha256', Operations::canonical($state)), 'state' => $state];
    }
}
