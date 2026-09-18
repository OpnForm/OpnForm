<?php

namespace App\Service\AdminApi;

use App\Http\Requests\UserBlockRequest;
use App\Models\AdminApiAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ActionExecutor
{
    public function execute(string $id): void
    {
        // A durable claim happens BEFORE any side effect. Retried jobs never run a write again.
        if (!AdminApiAction::whereKey($id)->where('status', 'queued')->update(['status' => 'running', 'updated_at' => now()])) {
            return;
        }
        $action = AdminApiAction::findOrFail($id);
        $hasLock = false;
        $started = false;
        $originalRequest = app('request');
        $originalActor = Auth::user();
        try {
            $actor = User::find($action->actor_id);
            $token = PersonalAccessToken::find($action->token_id);
            abort_unless($actor && $actor->moderator && !$actor->is_blocked && $token && $token->tokenable_id === $actor->id && $token->tokenable_type === $actor->getMorphClass(), 403);
            abort_if(!$token->expires_at || $token->expires_at->isPast(), 403);
            $expiration = config('sanctum.expiration');
            abort_if($expiration && $token->created_at->lte(now()->subMinutes($expiration)), 403);
            abort_unless(in_array(Operations::TOKEN_MARKER, $token->abilities, true) && in_array(Operations::scope($action->operation), $token->abilities, true), 403);
            if ($action->operation === 'extend-trial') {
                validator($action->payload, ['trial_ends_at' => 'required|date|after:now|before_or_equal:'.now()->addDays(14)->toIso8601String()])->validate();
            }
            $hasLock = DB::table('admin_api_action_locks')->insertOrIgnore(['target' => $action->target, 'action_id' => $id]) === 1;
            abort_unless($hasLock, 409, 'Another action holds this target.');
            $payload = $action->payload;
            $snapshot = app(State::class)->snapshot($action->operation, $payload);
            abort_unless(hash_equals($payload['expected_state'], $snapshot['snapshot_hash']), 409, 'Target state changed.');
            $requestClass = in_array($action->operation, ['block-user', 'unblock-user'], true) ? UserBlockRequest::class : Request::class;
            $request = $requestClass::create('/external/admin/v1/actions/'.$action->operation, 'POST', $payload);
            $request->setUserResolver(fn () => $actor);
            Auth::setUser($actor);
            $request->attributes->set('admin_api_action_id', $id);
            app()->instance('request', $request);
            [$service, $method] = Operations::ACTIONS[$action->operation];
            $subscriptionId = null;
            if (in_array($action->operation, ['apply-discount', 'extend-trial'], true)) {
                $local = collect($snapshot['state']['billing']['local_subscriptions']);
                $selected = $action->operation === 'extend-trial'
                    ? $local->firstWhere('stripe_status', 'trialing')
                    : $local->filter(fn ($sub) => in_array($sub['stripe_status'], ['active', 'trialing'], true))->sole();
                abort_unless($selected, 422, 'No subscription selected.');
                $subscriptionId = $selected['stripe_id'];
            }
            $started = true;
            $response = $action->operation === 'restore-form'
                ? app($service)->$method($payload['slug'])
                : app()->call([app($service), $method], ['request' => $request]);
            $data = $response->getData(true);
            if ($response->getStatusCode() >= 400) {
                // A service may have performed a partial side effect before reporting an error.
                $action->update(['status' => 'uncertain', 'result' => ['code' => 'operation_failed', 'http_status' => $response->getStatusCode()]]);
                return;
            }
            $result = array_intersect_key($data, array_flip(['message', 'template_slug', 'refund_id', 'charge_id']));
            if ($subscriptionId) {
                $result['subscription_id'] = $subscriptionId;
                if ($action->operation === 'apply-discount') {
                    $result['coupon_id'] = $snapshot['state']['billing']['discount_coupon_id'];
                }
            }
            if ($action->operation === 'send-password-reset-email') {
                $result['delivery'] = 'accepted_by_mailer';
            }
            // Persist the receipt before read-back: transient reads never turn into a repeated write.
            $action->update(['status' => 'verifying', 'result' => $result]);
            $after = $action->operation === 'refund-payment' ? app(State::class)->refundReceiptSnapshot($action) : app(State::class)->snapshot($action->operation, $payload);
            app(RemoteVerification::class)->reconcile($action, $after);
        } catch (Throwable $exception) {
            if ($action->fresh()->status === 'verifying') {
                return;
            }
            $status = $started ? 'uncertain' : 'rejected';
            $action->update(['status' => $status, 'result' => ['code' => $started ? 'execution_uncertain' : 'precondition_or_authorization_failed']]);
            if (!$started && $hasLock) {
                DB::table('admin_api_action_locks')->where('action_id', $id)->delete();
            }
            // Do not report remote exception text: it may contain tokens or customer data.
        } finally {
            app()->instance('request', $originalRequest);
            if ($originalActor) {
                Auth::setUser($originalActor);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
