<?php

namespace App\Service\Forms\Analytics;

use Illuminate\Http\Request;

class UserAgentHelper
{
    private $browser;
    private $request;
    private $userAgent;
    private $ipLocationService;

    public function __construct(Request $request, ?IpLocationService $ipLocationService = null)
    {
        $this->browser = new BrowserDetection();
        $this->request = $request;
        $this->userAgent = $request->userAgent() ?? '';
        $this->ipLocationService = $ipLocationService ?? new IpLocationService();
    }

    public function getMetadata(): array
    {
        // Get browser metadata
        $result = $this->browser->getAll($this->userAgent);

        // Get location info
        $location = $this->getLocation();

        // Return metadata
        return [
            'ip' => $this->getHashedIp(),
            'source' => ucfirst($this->getTrafficSource()),
            'device' => $result['device_type'],
            'browser' => $result['browser_name'],
            'os' => $result['os_name'],
            'country' => $location['country'] ?? 'Unknown'
        ];
    }

    // Get Traffic Source from Referer
    public function getTrafficSource(): string
    {
        $referer = $this->request->header('Referer');
        $origin = $this->request->header('Origin');
        if (!$referer) {
            return 'Direct';
        }

        // Guard against null/empty origin to avoid errors in str_starts_with
        if (is_string($origin) && $origin !== '' && str_starts_with($referer, $origin)) {
            return 'Direct';
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (!$host) {
            return 'Unknown';
        }

        // Match common sources
        $patterns = [
            'google' => '/google\./i',
            'facebook' => '/facebook\.|fb\./i',
            'twitter' => '/twitter\.|t\.co/i',
            'linkedin' => '/linkedin\./i',
            'instagram' => '/instagram\.|ig\./i',
            'youtube' => '/youtube\./i',
            'bing' => '/bing\./i',
            'yahoo' => '/yahoo\./i',
        ];

        foreach ($patterns as $source => $pattern) {
            if (preg_match($pattern, $host)) {
                return ucfirst($source);
            }
        }

        // If no match found, return the domain as the source
        return preg_replace('/^www\./', '', $host);
    }

    // Get Location from IP (cached)
    public function getLocation(): array
    {
        return $this->ipLocationService->getLocationData($this->request->ip());
    }

    // Get hashed IP with daily rotating salt for privacy
    private function getHashedIp(): string
    {
        $ip = $this->request->ip();
        $dailySalt = date('Y-m-d'); // Rotates daily
        return hash('sha256', $ip . $dailySalt);
    }
}
