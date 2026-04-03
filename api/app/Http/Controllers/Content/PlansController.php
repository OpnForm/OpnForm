<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class PlansController extends Controller
{
    public function index()
    {
        $plans = Cache::remember('plans_catalog', 3600, function () {
            return [
                'tiers' => config('plans.tiers', []),
            ];
        });

        return response()->json($plans);
    }
}
