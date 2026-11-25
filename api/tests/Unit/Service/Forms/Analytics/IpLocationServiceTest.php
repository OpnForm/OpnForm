<?php

namespace Tests\Unit;

use App\Service\Forms\Analytics\IpLocationService;
use App\Service\Forms\Analytics\IpLocation\IpLocationHttpClient;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IpLocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(IpLocationHttpClient::class)->preventStrayRequests();
        Cache::flush(); // Clear cache before each test
    }

    protected function tearDown(): void
    {
        Cache::flush(); // Clean up after each test
        parent::tearDown();
    }

    public static function ineligibleAddresses(): array
    {
        return [
            'private' => ['192.168.1.1'],
            'private 10/8' => ['10.0.0.1'],
            'shared' => ['100.64.0.1'],
            'loopback' => ['127.0.0.1'],
            'link-local' => ['169.254.1.1'],
            'unspecified' => ['0.0.0.0'],
            'broadcast' => ['255.255.255.255'],
            'reserved' => ['240.0.0.1'],
            'documentation' => ['192.0.2.1'],
            'deprecated 6to4 relay' => ['192.88.99.1'],
            'multicast' => ['224.0.0.1'],
            'multicast SSDP' => ['239.255.255.250'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unique local' => ['fc00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 documentation' => ['2001:db8::1'],
            'IPv6 additional documentation' => ['3fff::1'],
            'IPv6 retired 6bone' => ['3ffe::1'],
            'IPv6 reserved address space' => ['4000::1'],
            'IPv6 local translation' => ['64:ff9b:1::a00:1'],
            'IPv6 well-known translation' => ['64:ff9b::a00:1'],
            'IPv6 dummy prefix' => ['100:0:0:1::1'],
            'IPv6 segment routing' => ['5f00::1'],
            'IPv6 multicast' => ['ff02::1'],
            'IPv6 global multicast' => ['ff0e::1'],
            'IPv4-mapped private' => ['::ffff:192.168.1.1'],
            'IPv4-mapped shared' => ['::ffff:100.64.0.1'],
            'IPv4-mapped multicast' => ['::ffff:224.0.0.1'],
            'not an IP' => ['not-an-ip'],
            'empty' => [''],
        ];
    }

    #[DataProvider('ineligibleAddresses')]
    public function test_ineligible_address_is_never_sent(string $ip)
    {
        Config::set('services.ip_location.provider', 'internetdata');
        app(IpLocationHttpClient::class)->fake();

        $this->assertNull(IpLocationService::lookupAddress($ip));
        $this->assertNull($this->service()->lookup($ip));
        app(IpLocationHttpClient::class)->assertNothingSent();
    }

    public static function eligibleAddresses(): array
    {
        return [
            'IPv4' => ['8.8.8.8', '8.8.8.8'],
            'IPv4-mapped' => ['::ffff:8.8.8.8', '8.8.8.8'],
            'IPv4-mapped hex' => ['::FFFF:808:808', '8.8.8.8'],
            'IPv6' => ['2001:4860:4860::8888', '2001:4860:4860::8888'],
        ];
    }

    #[DataProvider('eligibleAddresses')]
    public function test_public_address_is_looked_up_in_canonical_form(string $ip, string $sent)
    {
        Config::set('services.ip_location.provider', 'internetdata');
        app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(self::internetDataAnswer(), 200)]);

        $this->assertSame($sent, IpLocationService::lookupAddress($ip));
        $this->assertSame('United States', $this->service()->lookup($ip)?->country);
        app(IpLocationHttpClient::class)->assertSentCount(1);
        app(IpLocationHttpClient::class)->assertSent(fn ($request) => $request->url() === "https://api.internetdata.io/{$sent}");
    }

    public function test_no_lookup_when_ipinfo_has_no_token()
    {
        Config::set('services.ipinfo.token', null);
        app(IpLocationHttpClient::class)->fake();

        $this->assertNull($this->service()->lookup('8.8.8.8'));
        $this->assertFalse($this->service()->enabled());
        app(IpLocationHttpClient::class)->assertNothingSent();
    }

    public function test_ipinfo_lite_answer()
    {
        Config::set('services.ipinfo.token', 'test-token');
        app(IpLocationHttpClient::class)->fake(['api.ipinfo.io/*' => Http::response(self::ipinfoAnswer(), 200)]);

        $location = $this->service()->lookup('8.8.8.8');

        $this->assertSame('US', $location->countryCode);
        $this->assertSame('United States', $location->country);
        app(IpLocationHttpClient::class)->assertSent(fn ($request) => $request->url() === 'https://api.ipinfo.io/lite/8.8.8.8?token=test-token');
    }

    public function test_internetdata_answer()
    {
        Config::set('services.ip_location.provider', 'internetdata');
        app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(self::internetDataAnswer(), 200)]);

        $location = $this->service()->lookup('8.8.8.8');

        $this->assertSame('US', $location->countryCode);
        $this->assertSame('United States', $location->country);
    }

    public function test_provider_labels_are_normalized_to_the_same_country()
    {
        Config::set('services.ipinfo.token', 'test-token');
        app(IpLocationHttpClient::class)->fake([
            'api.ipinfo.io/*' => Http::response(array_merge(self::ipinfoAnswer(), ['country_code' => 'KR', 'country' => 'South Korea'])),
            'api.internetdata.io/*' => Http::response(self::internetDataAnswer(['country' => 'KR', 'country_name' => 'Republic of Korea'])),
        ]);

        Config::set('services.ip_location.provider', 'ipinfo');
        $this->assertSame('South Korea', $this->service()->lookup('8.8.8.8')->country);
        Config::set('services.ip_location.provider', 'internetdata');
        $this->assertSame('South Korea', $this->service()->lookup('8.8.8.8')->country);
    }

    public function test_lookup_does_not_emit_global_http_events_with_the_visitor_ip()
    {
        $events = 0;
        Event::listen([RequestSending::class, ResponseReceived::class], function () use (&$events) {
            $events++;
        });
        Http::fake();
        Http::get('https://example.test');
        $this->assertGreaterThan(0, $events);
        $events = 0;

        Config::set('services.ip_location.provider', 'internetdata');
        app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(self::internetDataAnswer())]);

        $this->assertSame('US', $this->service()->lookup('8.8.8.8')->countryCode);
        $this->assertSame(0, $events);
    }

    public function test_caches_successful_api_response()
    {
        Config::set('services.ipinfo.token', 'test-token');
        Config::set('services.ip_location.cache_ttl_hours', 1);
        app(IpLocationHttpClient::class)->fake(['api.ipinfo.io/*' => Http::response(self::ipinfoAnswer(), 200)]);

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(1);

        $this->travel(61)->minutes();

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(2);
    }

    public static function answersWithoutCountry(): array
    {
        return [
            // IPinfo Lite's answer for an address it has no country for
            'ipinfo' => ['ipinfo', 'api.ipinfo.io/*', ['ip' => '8.8.8.8', 'bogon' => true]],
            'ipinfo invalid code' => ['ipinfo', 'api.ipinfo.io/*', array_merge(self::ipinfoAnswer(), ['country_code' => 'USA'])],
            'ipinfo unassigned code' => ['ipinfo', 'api.ipinfo.io/*', array_merge(self::ipinfoAnswer(), ['country_code' => 'ZZ'])],
            'ipinfo retired code' => ['ipinfo', 'api.ipinfo.io/*', array_merge(self::ipinfoAnswer(), ['country_code' => 'AN'])],
            // InternetData's answer for an address it has no location for
            'internetdata' => ['internetdata', 'api.internetdata.io/*', self::internetDataAnswer([
                'continent' => '', 'continent_name' => '', 'country' => '', 'country_name' => '', 'timezone' => '',
                'currency_code' => '', 'currency_name' => '', 'currency_symbol' => '', 'flag_emoji' => '', 'flag_unicode' => '',
            ])],
            'internetdata no name' => ['internetdata', 'api.internetdata.io/*', self::internetDataAnswer(['country_name' => ''])],
            'not JSON' => ['internetdata', 'api.internetdata.io/*', '<html>OK</html>'],
        ];
    }

    #[DataProvider('answersWithoutCountry')]
    public function test_answer_without_valid_country_uses_failure_ttl(string $provider, string $url, array|string $answer)
    {
        Config::set('services.ipinfo.token', 'test-token');
        Config::set('services.ip_location.provider', $provider);
        Config::set('services.ip_location.failure_cache_ttl_minutes', 5);
        app(IpLocationHttpClient::class)->fake([$url => Http::response($answer, 200)]);

        $this->assertNull($this->service()->lookup('8.8.8.8'));
        $this->assertNull($this->service()->lookup('8.8.8.8'));
        app(IpLocationHttpClient::class)->assertSentCount(1);

        $this->travel(6)->minutes();

        $this->assertNull($this->service()->lookup('8.8.8.8'));
        app(IpLocationHttpClient::class)->assertSentCount(2);
    }

    public function test_failed_lookup_is_cached_briefly()
    {
        Config::set('services.ip_location.provider', 'internetdata');
        Config::set('services.ip_location.failure_cache_ttl_minutes', 5);

        app(IpLocationHttpClient::class)->fake([
            'api.internetdata.io/*' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push(self::internetDataAnswer(), 200)
        ]);

        $this->assertNull($this->service()->lookup('8.8.8.8'));
        $this->assertNull($this->service()->lookup('8.8.8.8'));
        app(IpLocationHttpClient::class)->assertSentCount(1);

        $this->travel(6)->minutes();

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(2);
    }

    public function test_failure_logs_carry_no_ip_or_exception_message()
    {
        Config::set('services.ip_location.provider', 'internetdata');
        Log::spy();

        app(IpLocationHttpClient::class)->fake([
            'api.internetdata.io/8.8.8.8' => Http::response('Service Unavailable for 8.8.8.8', 503),
            'api.internetdata.io/1.1.1.1' => Http::failedConnection(),
        ]);
        $this->assertNull($this->service()->lookup('8.8.8.8'));
        $this->assertNull($this->service()->lookup('1.1.1.1'));

        Log::shouldHaveReceived('warning')->with('IP location lookup failed', ['provider' => 'internetdata', 'status' => 503])->once();
        Log::shouldHaveReceived('warning')->with('IP location lookup failed', ['provider' => 'internetdata', 'error' => 'connection'])->once();
    }

    public function test_cache_clear_functionality()
    {
        Config::set('services.ipinfo.token', 'test-token');

        app(IpLocationHttpClient::class)->fake([
            'api.ipinfo.io/*' => Http::sequence()
                ->push(self::ipinfoAnswer(), 200)
                ->push(array_merge(self::ipinfoAnswer(), ['country_code' => 'CA', 'country' => 'Canada']), 200)
        ]);

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(1);

        $this->assertTrue($this->service()->clearCache('8.8.8.8'));

        $this->assertSame('Canada', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(2);
    }

    public function test_cache_statistics()
    {
        Config::set('services.ipinfo.token', 'test-token');
        app(IpLocationHttpClient::class)->fake(['api.ipinfo.io/*' => Http::response(self::ipinfoAnswer(), 200)]);

        $ips = ['8.8.8.8', '1.1.1.1', '208.67.222.222'];
        $this->service()->lookup($ips[0]);

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
        app(IpLocationHttpClient::class)->fake(['api.ipinfo.io/*' => Http::response(self::ipinfoAnswer(), 200)]);

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);

        $this->travel(47)->hours();
        $this->service()->lookup('8.8.8.8');
        app(IpLocationHttpClient::class)->assertSentCount(1);

        $this->travel(2)->hours();
        $this->service()->lookup('8.8.8.8');
        app(IpLocationHttpClient::class)->assertSentCount(2);
    }

    public function test_self_hosted_uses_internetdata_without_token()
    {
        Config::set('app.self_hosted', true);
        Config::set('services.ipinfo.token', null);
        app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(self::internetDataAnswer(), 200)]);

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSent(fn ($request) => $request->url() === 'https://api.internetdata.io/8.8.8.8');
    }

    public function test_self_hosted_uses_ipinfo_when_token_configured()
    {
        Config::set('app.self_hosted', true);
        Config::set('services.ipinfo.token', 'test-token');
        app(IpLocationHttpClient::class)->fake(['api.ipinfo.io/*' => Http::response(self::ipinfoAnswer(), 200)]);

        $this->assertSame('US', $this->service()->lookup('8.8.8.8')->countryCode);
        app(IpLocationHttpClient::class)->assertSentCount(1);
    }

    public function test_provider_setting_overrides_default()
    {
        Config::set('services.ip_location.provider', 'internetdata');
        Config::set('services.ipinfo.token', 'test-token');
        app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(self::internetDataAnswer(['country' => 'CA', 'country_name' => 'Canada']), 200)]);

        $this->assertSame('Canada', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(1);
    }

    public function test_provider_none_disables_external_lookups()
    {
        Config::set('app.self_hosted', true);
        Config::set('services.ip_location.provider', 'none');
        Config::set('services.ipinfo.token', 'test-token');
        app(IpLocationHttpClient::class)->fake();

        $this->assertFalse($this->service()->enabled());
        $this->assertNull($this->service()->lookup('8.8.8.8'));
        app(IpLocationHttpClient::class)->assertNothingSent();
    }

    public function test_switching_provider_ignores_warm_cache()
    {
        Config::set('services.ipinfo.token', 'test-token');

        app(IpLocationHttpClient::class)->fake([
            'api.ipinfo.io/*' => Http::response(self::ipinfoAnswer(), 200),
            'api.internetdata.io/*' => Http::response(self::internetDataAnswer(['country' => 'CA', 'country_name' => 'Canada']), 200),
        ]);

        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);

        Config::set('services.ip_location.provider', 'internetdata');
        $this->assertSame('Canada', $this->service()->lookup('8.8.8.8')->country);

        Config::set('services.ip_location.provider', 'ipinfo');
        $this->assertSame('United States', $this->service()->lookup('8.8.8.8')->country);
        app(IpLocationHttpClient::class)->assertSentCount(2);
    }

    private function service(): IpLocationService
    {
        return app(IpLocationService::class);
    }

    /**
     * IPinfo Lite's answer for 8.8.8.8, as its docs show it
     */
    private static function ipinfoAnswer(): array
    {
        return [
            'ip' => '8.8.8.8',
            'asn' => 'AS15169',
            'as_name' => 'Google LLC',
            'as_domain' => 'google.com',
            'country_code' => 'US',
            'country' => 'United States',
            'continent_code' => 'NA',
            'continent' => 'North America',
        ];
    }

    /**
     * InternetData's keyless answer for 8.8.8.8, with its location overridden
     */
    private static function internetDataAnswer(array $location = []): array
    {
        return [
            'ip' => '8.8.8.8',
            'is_bogon' => false,
            'is_tor' => false,
            'is_relay' => false,
            'is_bot' => false,
            'is_anycast' => true,
            'is_carrier' => false,
            'is_satellite' => false,
            'is_ssh' => false,
            'is_smtp' => false,
            'is_web' => true,
            'is_dns_resolver' => true,
            'is_bittorrent' => false,
            'location' => array_merge([
                'continent' => 'NA',
                'continent_name' => 'North America',
                'country' => 'US',
                'country_name' => 'United States',
                'timezone' => 'America/Los_Angeles',
                'currency_code' => 'USD',
                'currency_name' => 'Dollar',
                'currency_symbol' => '$',
                'flag_emoji' => "\u{1F1FA}\u{1F1F8}",
                'flag_unicode' => 'U+1F1FA U+1F1F8',
            ], $location),
            'tor' => [],
            'relay' => [],
            'asn' => [
                'asn' => 15169,
                'name' => 'Google LLC',
                'domain' => 'google.com',
                'type' => 'hosting',
                'prefix' => '8.8.8.0/24',
                'country' => 'US',
                'rpki_status' => 'valid',
            ],
            'company' => ['name' => 'Google LLC', 'domain' => 'google.com'],
            'hostname' => 'dns.google',
            'abuse' => ['email' => 'network-abuse@google.com'],
        ];
    }
}
