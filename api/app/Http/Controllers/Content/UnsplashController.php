<?php

namespace App\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class UnsplashController extends Controller
{
    public function index(Request $request)
    {
        $accessKey = config('services.unsplash.access_key');

        if (!$accessKey) {
            return response()->json([]);
        }

        $term = trim((string) $request->get('term', ''));

        // If a search term is provided, fetch fresh results from the search endpoint (no cache)
        if ($term !== '') {
            $photos = $this->fetchUnsplash(
                'https://api.unsplash.com/search/photos',
                [
                    'client_id' => $accessKey,
                    'query' => $term,
                    'per_page' => 9,
                ],
                fn ($json) => $json['results'] ?? []
            );

            return response()->json($photos);
        }

        // No search term: return cached curated/latest photos
        $photos = Cache::remember('unsplash_images', 60 * 60, function () use ($accessKey) {
            return $this->fetchUnsplash(
                'https://api.unsplash.com/photos',
                [
                    'client_id' => $accessKey,
                    'per_page' => 9,
                ]
            );
        });

        return response()->json($photos);
    }

    public function download(Request $request)
    {
        $request->validate([
            'download_location' => 'required|url|regex:#^https://api\.unsplash\.com/photos/#',
        ]);

        $accessKey = config('services.unsplash.access_key');

        if (!$accessKey) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $downloadLocation = $request->input('download_location');

        // Trigger download tracking with proper headers and authorization
        $response = Http::withHeaders([
            'Accept-Version' => 'v1',
            'User-Agent' => 'OpnForm (https://opnform.com)',
        ])->get($downloadLocation, [
            'client_id' => $accessKey,
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to track download', 'response' => $response->json()], 400);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Perform Unsplash HTTP GET and normalize response.
     *
     * @param string $url
     * @param array $params
     * @param callable|null $transform Receives decoded JSON and returns array
     * @return array
     */
    private function fetchUnsplash(string $url, array $params = [], ?callable $transform = null): array
    {
        $response = Http::get($url, $params);

        if (!$response->successful()) {
            return [];
        }

        $json = $response->json();

        if (isset($json['errors'])) {
            return [];
        }

        $photos = $transform ? $transform($json) : (is_array($json) ? $json : []);
        return collect($photos)->map(function ($photo) {
            return [
                'id' => $photo['id'],
                'url' => $photo['urls']['regular'] ?? null,
                'alt_text' => $photo['alt_description'] ?? null,
                'photographer_name' => $photo['user']['name'] ?? null,
                'photographer_username' => $photo['user']['username'] ?? null,
                'photographer_url' => $photo['user']['links']['html'] ?? null,
                'download_location' => $photo['links']['download_location'] ?? null,
            ];
        })->filter(function ($photo) {
            return $photo['url'] !== null;
        })->take(9)->toArray();
    }
}
