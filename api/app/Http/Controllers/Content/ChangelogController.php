<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChangelogController extends Controller
{
    public const CANNY_ENDPOINT = 'https://canny.io/api/v1/';

    public function index()
    {
        return Cache::remember('changelog_entries', now()->addHour(), function () {
            $response = Http::post(self::CANNY_ENDPOINT.'entries/list', [
                'apiKey' => config('services.canny.api_key'),
                'limit' => 3,
            ]);

            return $response->json('entries');
        });
    }
}
