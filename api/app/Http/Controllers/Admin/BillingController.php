<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Service\Admin\AdminBilling;

class BillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('moderator');
    }

    public function getCustomer(User $user)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminBilling::class)->getCustomer($user));
    }

    public function updateCustomer(Request $request)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminBilling::class)->updateCustomer($request));
    }

    public function getSubscriptions(User $user)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminBilling::class)->getSubscriptions($user));
    }

    public function getPayments(User $user)
    {
        return \App\Service\Admin\AdminStripe::withTimeout(fn () => app(AdminBilling::class)->getPayments($user));
    }

}
