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
        return app(AdminOperations::class)->applyDiscount($request);
    }

    public function extendTrial(Request $request)
    {
        return app(AdminOperations::class)->extendTrial($request);
    }

    public function cancelSubscription(Request $request)
    {
        return app(AdminOperations::class)->cancelSubscription($request);
    }

    public function sendPasswordResetEmail(Request $request)
    {
        return app(AdminOperations::class)->sendPasswordResetEmail($request);
    }

    public function refundPayment(Request $request)
    {
        return app(AdminOperations::class)->refundPayment($request);
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
