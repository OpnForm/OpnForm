<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminApi\ActionRequest;
use App\Jobs\AdminApi\ExecuteAdminAction;
use App\Models\AdminApiAction;
use App\Models\User;
use App\Service\Admin\AdminBilling;
use App\Service\Admin\AdminForms;
use App\Service\AdminApi\Operations;
use App\Service\AdminApi\State;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminApiController extends Controller
{
    public function user(string $identifier, State $state)
    {
        return response()->json($state->user($identifier));
    }

    public function workspace(int $id, State $state)
    {
        return response()->json($state->workspace($id));
    }

    public function billing(User $user, string $resource, State $state, AdminBilling $billing)
    {
        $state->user((string) $user->id);
        $method = match ($resource) {
            'customer' => 'getCustomer', 'subscriptions' => 'getSubscriptions', 'payments' => 'getPayments',
            default => abort(404),
        };
        return $billing->$method($user);
    }

    public function deletedForms(User $user, State $state, AdminForms $forms)
    {
        $state->user((string) $user->id);
        return $forms->getDeletedForms($user);
    }

    public function actionStatus(Request $request, string $actionId, State $state)
    {
        abort_unless(Str::isUuid($actionId), 422);
        $input = $request->validate([
            'operation' => 'required|string|max:64',
            'user_id' => 'sometimes|integer', 'slug' => 'sometimes|string|max:255',
        ]);
        $operation = $input['operation'];
        $action = AdminApiAction::find($actionId);
        if ($action) {
            abort_unless($action->token_id === $request->user()->currentAccessToken()->id && $action->operation === $operation, 404);
        }
        $snapshot = $action && $operation === 'refund-payment' && in_array($action->status, ['completed', 'verifying'], true)
            ? $state->refundReceiptSnapshot($action) : $state->snapshot($operation, $input);
        if ($action) {
            abort_unless($action->token_id === $request->user()->currentAccessToken()->id && $action->operation === $operation && $action->target === $snapshot['target'], 404);
            abort_unless($state->target($operation, $input) === $action->target, 404);
            // Form slugs sharing a user target must still match the original request.
            abort_unless(($action->payload['slug'] ?? null) === ($input['slug'] ?? null), 404);
        }
        $confirmed = !in_array($action?->status, ['completed', 'verifying'], true)
            || app(\App\Service\AdminApi\RemoteVerification::class)->reconcile($action, $snapshot);
        return response()->json([
            'action_id' => $actionId, 'operation' => $operation,
            'status' => $action?->status === 'completed' && !$confirmed
                ? 'verification_pending' : ($action?->status ?? 'not_started'), 'result' => $action?->result,
        ] + $snapshot);
    }

    public function execute(ActionRequest $request, string $operation, State $state)
    {
        Operations::scope($operation);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[A-Za-z0-9._:-]{8,200}$/D', $key), 422, 'A valid Idempotency-Key is required.');
        $payload = $request->validated();
        $hash = hash('sha256', Operations::canonical(['operation' => $operation, 'payload' => $payload]));
        $tokenId = $request->user()->currentAccessToken()->id;
        $keyHash = hash('sha256', $key);
        $existing = AdminApiAction::where('token_id', $tokenId)->where('idempotency_hash', $keyHash)->first() ?? AdminApiAction::find($payload['action_id']);
        if ($existing) {
            return $this->replay($existing, $tokenId, $keyHash, $hash);
        }
        if ($operation === 'extend-trial') {
            validator($payload, ['trial_ends_at' => 'after:now|before_or_equal:'.now()->addDays(14)->toIso8601String()])->validate();
        }
        $snapshot = $state->snapshot($operation, $payload);
        abort_unless(hash_equals($payload['expected_state'], $snapshot['snapshot_hash']), 409, 'Target state changed.');
        if ($operation === 'refund-payment') {
            $invoice = $snapshot['state']['billing']['latest_invoice'];
            abort_unless($invoice && $invoice['id'] === $payload['invoice_id']
                && $invoice['payment']['amount_paid'] - $invoice['payment']['amount_refunded'] === $payload['expected_amount']
                && $invoice['payment']['currency'] === $payload['expected_currency'], 409, 'Approved refund amount or currency changed.');
        }
        // Synchronous drivers cannot meet the HTTP connector deadline, especially AI template generation.
        abort_if(in_array(config('queue.connections.'.config('queue.default').'.driver'), ['sync', 'null'], true), 503, 'Configure an asynchronous queue for admin actions.');
        try {
            $action = AdminApiAction::create([
                'id' => $payload['action_id'], 'actor_id' => $request->user()->id, 'token_id' => $tokenId,
                'operation' => $operation, 'target' => $snapshot['target'], 'idempotency_hash' => $keyHash,
                'request_hash' => $hash, 'payload' => $payload, 'status' => 'queued',
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $action = AdminApiAction::where('token_id', $tokenId)->where('idempotency_hash', $keyHash)->first() ?? AdminApiAction::findOrFail($payload['action_id']);
            return $this->replay($action, $tokenId, $keyHash, $hash);
        }
        try {
            ExecuteAdminAction::dispatch($action->id);
        } catch (\Throwable $exception) {
            AdminApiAction::whereKey($action->id)->where('status', 'queued')->update(['status' => 'uncertain', 'result' => json_encode(['code' => 'queue_dispatch_uncertain'])]);
            $action->refresh();
        }
        return response()->json(['action_id' => $action->id, 'status' => $action->status], 202);
    }

    private function replay(AdminApiAction $action, int $tokenId, string $keyHash, string $hash)
    {
        abort_unless($action->token_id === $tokenId && hash_equals($action->idempotency_hash, $keyHash) && hash_equals($action->request_hash, $hash), 409, 'Idempotency key or action ID already used for another request.');
        return response()->json(['action_id' => $action->id, 'status' => $action->status, 'result' => $action->result], $action->status === 'completed' ? 200 : 202);
    }
}
