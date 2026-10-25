<?php

namespace App\Http\Middleware;

use App\Http\Requests\AdminApi\ActionRequest;
use App\Models\AdminApiAction;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class AdminRequestReceipt
{
    public function handle(Request $request, Closure $next)
    {
        // Request::get() in shared UI services prefers query values over JSON input.
        abort_if($request->query->count() > 0, 422, 'Action parameters must be supplied only in the JSON body.');
        $input = app(ActionRequest::class)->validated();
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && strlen($key) >= 8 && strlen($key) <= 200, 422, 'Idempotency-Key required.');
        if (isset($input['user_id'])) {
            abort_if(User::findOrFail($input['user_id'])->admin, 403);
        }
        $hash = AdminApiAction::contentHash($request->route()->defaults['operation'], $input);
        $keyHash = hash('sha256', $key);
        $tokenId = $request->user()->currentAccessToken()->id;
        try {
            $receipt = AdminApiAction::create([
                'id' => $input['action_id'], 'actor_id' => $request->user()->id, 'token_id' => $tokenId,
                'operation' => $request->route()->defaults['operation'],
                'target' => isset($input['user_id']) ? 'user:'.$input['user_id'] : 'templates',
                'request_hash' => $hash, 'idempotency_hash' => $keyHash,
                'status' => 'running',
            ]);
        } catch (QueryException $e) {
            $receipt = AdminApiAction::where('id', $input['action_id'])->orWhere(fn ($q) => $q->where('token_id', $tokenId)->where('idempotency_hash', $keyHash))->first();
            if (!$receipt) {
                throw $e;
            }
            abort_unless($receipt->id === $input['action_id'] && $receipt->token_id === $tokenId
                && $receipt->actor_id === $request->user()->id && hash_equals($receipt->request_hash, $hash)
                && hash_equals($receipt->idempotency_hash, $keyHash), 409, 'Conflicting request identity.');
            return response()->json(['action_id' => $receipt->id, 'status' => $receipt->status, 'result' => $receipt->result], $receipt->status === 'completed' ? 200 : 409);
        }
        $request->attributes->set('admin_api_action_id', $receipt->id);
        try {
            $response = $next($request);
            $status = $response->getStatusCode() < 300 ? 'completed' : 'uncertain';
            if ($response->getStatusCode() === 202) {
                $status = 'running';
            }
            $receipt->refresh();
            if ($receipt->status === 'running') {
                $receipt->update(['status' => $status, 'result' => $status === 'completed' ? array_merge(['accepted' => true], \Illuminate\Support\Arr::only(json_decode($response->getContent(), true) ?? [], ['refund_id', 'charge_id', 'template_slug'])) : $receipt->result]);
            }
            return response()->json(['action_id' => $receipt->id, 'status' => $receipt->status, 'result' => $receipt->result], $response->getStatusCode());
        } catch (\Throwable $e) {
            $receipt->update(['status' => 'uncertain']);
            throw $e;
        }
    }
}
