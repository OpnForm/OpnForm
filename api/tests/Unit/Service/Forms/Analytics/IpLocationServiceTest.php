<?php

namespace Tests\Unit;

use App\Service\Forms\Analytics\IpLocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpLocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush(); // Clear cache before each test
    }

    protected function tearDown(): void
    {
        Cache::flush(); // Clean up after each test
        parent::tearDown();
    }

    public function test_returns_default_data_for_private_ip()
    {
        $privateIp = '192.168.1.1';
        $result = $this->service()->getLocationData($privateIp);

        $this->assertEquals('Unknown', $result['country']);
        $this->assertNull($result['region']);
        $this->assertNull($result['city']);
    }

    public function test_returns_default_data_when_no_token_configured()
    {
        Config::set('services.ipinfo.token', null);

        $publicIp = '8.8.8.8';
        $result = $this->service()->getLocationData($publicIp);

        $this->assertEquals('Unknown', $result['country']);
        Http::assertNothingSent();
    }

    public function test_caches_successful_api_response()
    {
        Config::set('services.ipinfo.token', 'test-token');
        Config::set('services.ip_location.cache_ttl_hours', 1);

        $publicIp = '8.8.8.8';
        $mockResponse = [
            'country' => 'US',
            'region' => 'California',
            'city' => 'Mountain View',
            'timezone' => 'America/Los_Angeles',
            'org' => 'AS15169 Google LLC',
        ];

        // Mock successful API response
        Http::fake([
            'api.ipinfo.io/*' => Http::response($mockResponse, 200)
        ]);

        // First call should hit API
        $result1 = $this->service()->getLocationData($publicIp);

        // Second call should hit cache (no API call)
        Http::fake([]); // Clear HTTP fakes to ensure no API call
        $result2 = $this->service()->getLocationData($publicIp);

        $this->assertEquals($result1, $result2);
        $this->assertEquals('US', $result1['country']);
        $this->assertEquals('California', $result1['region']);
    }

    public function test_handles_api_failure_gracefully()
    {
        Config::set('services.ipinfo.token', 'test-token');

        $publicIp = '8.8.8.8';

        // Mock failed API response
        Http::fake([
            'api.ipinfo.io/*' => Http::response('Server Error', 500)
        ]);

        $result = $this->service()->getLocationData($publicIp);

        $this->assertEquals('Unknown', $result['country']);
        $this->assertNull($result['region']);
    }

    public function test_cache_clear_functionality()
    {
        Config::set('services.ipinfo.token', 'test-token');

        $publicIp = '8.8.8.8';
        $callCount = 0;

        // Mock API to return different responses on sequential calls
        Http::fake([
            'api.ipinfo.io/*' => function () use (&$callCount) {
                $callCount++;
                return Http::response([
                    'country' => $callCount === 1 ? 'US' : 'CA'
                ], 200);
            }
        ]);

        // First call should cache 'US'
        $result1 = $this->service()->getLocationData($publicIp);
        $this->assertEquals('US', $result1['country']);

        // Second call should return cached 'US' (no API call)
        $result2 = $this->service()->getLocationData($publicIp);
        $this->assertEquals('US', $result2['country']);
        $this->assertEquals(1, $callCount); // Still only 1 API call

        // Clear the cache
        $cleared = $this->service()->clearCache($publicIp);
        $this->assertTrue($cleared);

        // Next call should hit API again and get 'CA'
        $result3 = $this->service()->getLocationData($publicIp);
        $this->assertEquals('CA', $result3['country']);
        $this->assertEquals(2, $callCount); // Now 2 API calls
    }

    public function test_cache_statistics()
    {
        Config::set('services.ipinfo.token', 'test-token');

        $ips = ['8.8.8.8', '1.1.1.1', '208.67.222.222'];

        // Cache one IP
        Http::fake([
            'api.ipinfo.io/*' => Http::response(['country' => 'US'], 200)
        ]);
        $this->service()->getLocationData($ips[0]);

        // Get cache stats
        $stats = $this->service()->getCacheStats($ips);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['cached']);
        $this->assertEquals(2, $stats['missing']);
    }

    public function test_respects_custom_configuration()
    {
        Config::set('services.ipinfo.token', 'test-token');
        Config::set('services.ip_location.request_timeout', 10);
        Config::set('services.ip_location.cache_ttl_hours', 48);

        $publicIp = '8.8.8.8';

        Http::fake([
            'api.ipinfo.io/*' => function ($request) {
                // Verify the request uses custom configuration
                $this->assertStringContainsString('test-token', $request->url());
                return Http::response(['country' => 'US'], 200);
            }
        ]);

        $result = $this->service()->getLocationData($publicIp);
        $this->assertEquals('US', $result['country']);
    }

    public function test_self_hosted_uses_internetdata_without_token()
    {
        Config::set('app.self_hosted', true);
        Config::set('services.ipinfo.token', null);

        Http::fake([
            'api.internetdata.io/*' => Http::response($this->internetDataAnswer('United States'), 200)
        ]);

        $result = $this->service()->getLocationData('8.8.8.8');

        $this->assertEquals('United States', $result['country']);
        $this->assertEquals('America/Los_Angeles', $result['timezone']);
        $this->assertNull($result['city']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.internetdata.io/8.8.8.8');
    }

    public function test_self_hosted_uses_ipinfo_when_token_configured()
    {
        Config::set('app.self_hosted', true);
        Config::set('services.ipinfo.token', 'test-token');

        Http::fake([
            'api.ipinfo.io/*' => Http::response(['country' => 'US'], 200)
        ]);

        $result = $this->service()->getLocationData('8.8.8.8');

        $this->assertEquals('US', $result['country']);
        Http::assertSentCount(1);
    }

    public function test_provider_setting_overrides_default()
    {
        Config::set('services.ip_location.provider', 'internetdata');
        Config::set('services.ipinfo.token', 'test-token');

        Http::fake([
            'api.internetdata.io/*' => Http::response($this->internetDataAnswer('Canada'), 200)
        ]);

        $result = $this->service()->getLocationData('8.8.8.8');

        $this->assertEquals('Canada', $result['country']);
        Http::assertSentCount(1);
    }

    public function test_provider_none_disables_external_lookups()
    {
        Config::set('app.self_hosted', true);
        Config::set('services.ip_location.provider', 'none');
        Config::set('services.ipinfo.token', 'test-token');

        Http::fake([
            'api.ipinfo.io/*' => Http::response(['country' => 'US'], 200)
        ]);

        $result = $this->service()->getLocationData('8.8.8.8');

        $this->assertEquals('Unknown', $result['country']);
        Http::assertNothingSent();
    }

    public function test_switching_provider_ignores_warm_cache()
    {
        Config::set('services.ipinfo.token', 'test-token');

        Http::fake([
            'api.ipinfo.io/*' => Http::response(['country' => 'US'], 200),
            'api.internetdata.io/*' => Http::response($this->internetDataAnswer('United States'), 200),
        ]);

        $this->assertEquals('US', $this->service()->getLocationData('8.8.8.8')['country']);

        Config::set('services.ip_location.provider', 'internetdata');
        $this->assertEquals('United States', $this->service()->getLocationData('8.8.8.8')['country']);

        Config::set('services.ip_location.provider', 'ipinfo');
        $this->assertEquals('US', $this->service()->getLocationData('8.8.8.8')['country']);
        Http::assertSentCount(2);
    }

    public function test_failed_lookup_is_cached_briefly()
    {
        Config::set('services.ip_location.provider', 'internetdata');
        Config::set('services.ip_location.failure_cache_ttl_minutes', 5);

        Http::fake([
            'api.internetdata.io/*' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push($this->internetDataAnswer('United States'), 200)
        ]);

        $this->assertEquals('Unknown', $this->service()->getLocationData('8.8.8.8')['country']);
        $this->assertEquals('Unknown', $this->service()->getLocationData('8.8.8.8')['country']);
        Http::assertSentCount(1);

        $this->travel(6)->minutes();

        $this->assertEquals('United States', $this->service()->getLocationData('8.8.8.8')['country']);
        Http::assertSentCount(2);
    }

    private function service(): IpLocationService
    {
        return app(IpLocationService::class);
    }

    private function internetDataAnswer(string $country): array
    {
        return [
            'ip' => '8.8.8.8',
            'location' => [
                'country' => 'US',
                'country_name' => $country,
                'timezone' => 'America/Los_Angeles',
            ],
        ];
    }
}
