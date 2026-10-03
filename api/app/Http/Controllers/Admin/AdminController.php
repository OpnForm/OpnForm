<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserBlockRequest;
use App\Service\UserActionService;
use Illuminate\Http\Request;
use App\Service\Admin\AdminOperations;

class AdminController extends Controller
{
    public const ADMIN_LOG_PREFIX = AdminOperations::ADMIN_LOG_PREFIX;

    public function __construct()
    {
        $this->middleware('moderator');
    }

    public function receipt(Request $request, string $actionId)
    {
        $request->merge(['action_id' => $actionId]);
        $input = app(\App\Http\Requests\AdminApi\ActionRequest::class)->validated();
        $receipt = \App\Models\AdminApiAction::find($actionId);
        if (!$receipt) {
            return response()->json(['action_id' => $actionId, 'status' => 'not_started']);
        }
        abort_unless($receipt->actor_id === $request->user()->id
            && $receipt->token_id === $request->user()->currentAccessToken()->id
            && $receipt->operation === $request->route()->defaults['operation'], 404);
        abort_unless(hash_equals($receipt->request_hash, \App\Models\AdminApiAction::contentHash($receipt->operation, $input)), 409, 'Receipt belongs to different action content.');
        return response()->json(['action_id' => $actionId, 'status' => $receipt->status, 'result' => $receipt->result]);
    }

    public function createTemplate(Request $request)
    {
        return app(AdminOperations::class)->createTemplate($request);
    }

    public function fetchUser($identifier)
    {
        return app(AdminOperations::class)->fetchUser($identifier);
    }

    public function blockUser(UserBlockRequest $request, UserActionService $userActionService)
    {
        return app(AdminOperations::class)->blockUser($request, $userActionService);
    }

    public function unblockUser(UserBlockRequest $request, UserActionService $userActionService)
    {
        return app(AdminOperations::class)->unblockUser($request, $userActionService);
    }

    public function applyDiscount(Request $request)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminOperations::class)->applyDiscount($request));
    }

    public function extendTrial(Request $request)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminOperations::class)->extendTrial($request));
    }

    public function cancelSubscription(Request $request)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminOperations::class)->cancelSubscription($request));
    }

    public function sendPasswordResetEmail(Request $request)
    {
        return app(AdminOperations::class)->sendPasswordResetEmail($request);
    }

    public function refundPayment(Request $request)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminOperations::class)->refundPayment($request));
    }

    public function disableTwoFactorAuthentication(Request $request)
    {
        return app(AdminOperations::class)->disableTwoFactorAuthentication($request);
    }

    public function clearUserCache(Request $request)
    {
        return app(AdminOperations::class)->clearUserCache($request);
    }

    public static function log($message, $data = [])
    {
        return AdminOperations::log($message, $data);
    }

}
