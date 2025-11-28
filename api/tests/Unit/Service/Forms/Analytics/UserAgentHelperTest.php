<?php

use App\Service\Forms\Analytics\IpLocationService;
use App\Service\Forms\Analytics\UserAgentHelper;
use Illuminate\Http\Request;

afterEach(function () {
    \Mockery::close();
});

it('constructs with default IP location service when none provided', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');

    $helper = new UserAgentHelper($mockRequest);

    expect($helper)->toBeInstanceOf(UserAgentHelper::class);
});

it('constructs with provided IP location service', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);

    expect($helper)->toBeInstanceOf(UserAgentHelper::class);
});

it('handles null user agent gracefully', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn(null);

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);

    expect($helper)->toBeInstanceOf(UserAgentHelper::class);
});

it('returns Direct when no referer header', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn(null);
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Direct');
});

it('returns Direct when referer matches origin', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://example.com/page');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Direct');
});

it('detects Google as traffic source', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://www.google.com/search?q=test');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Google');
});

it('detects Facebook as traffic source', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://www.facebook.com/');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Facebook');
});

it('detects Twitter as traffic source', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://t.co/abc123');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Twitter');
});

it('detects LinkedIn as traffic source', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://www.linkedin.com/feed/');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Linkedin');
});

it('returns domain name for unknown traffic sources', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://some-unknown-site.com/page');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('some-unknown-site.com');
});

it('removes www prefix from domain names', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('https://www.unknown-site.com/page');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('unknown-site.com');
});

it('returns Unknown for invalid referer URLs', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn('invalid-url');
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn('https://example.com');

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $source = $helper->getTrafficSource();

    expect($source)->toBe('Unknown');
});

it('delegates location requests to IP location service', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('ip')->andReturn('8.8.8.8');

    $expectedLocation = [
        'country' => 'US',
        'region' => 'California',
        'city' => 'Mountain View'
    ];

    $mockIpLocationService->shouldReceive('getLocationData')
        ->with('8.8.8.8')
        ->andReturn($expectedLocation);

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $location = $helper->getLocation();

    expect($location)->toBe($expectedLocation);
});

it('returns hashed IP in metadata for privacy', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('ip')->andReturn('8.8.8.8');
    $mockRequest->shouldReceive('header')->with('Referer')->andReturn(null);
    $mockRequest->shouldReceive('header')->with('Origin')->andReturn(null);

    $mockIpLocationService->shouldReceive('getLocationData')
        ->with('8.8.8.8')
        ->andReturn(['country' => 'US']);

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);

    // Mock the browser detection
    $mockBrowser = \Mockery::mock(\App\Service\Forms\Analytics\BrowserDetection::class);
    $mockBrowser->shouldReceive('getAll')->andReturn([
        'device_type' => 'Desktop',
        'browser_name' => 'Chrome',
        'os_name' => 'Windows'
    ]);

    // Use reflection to inject the mock browser
    $reflection = new ReflectionClass($helper);
    $browserProperty = $reflection->getProperty('browser');
    $browserProperty->setAccessible(true);
    $browserProperty->setValue($helper, $mockBrowser);

    $metadata = $helper->getMetadata();

    // The IP should be hashed, not the raw IP
    expect($metadata['ip'])->not()->toBe('8.8.8.8');
    expect($metadata['ip'])->toBeString();
    expect(strlen($metadata['ip']))->toBe(64); // SHA256 hash length
});

it('handles empty location data', function () {
    $mockRequest = \Mockery::mock(Request::class);
    $mockIpLocationService = \Mockery::mock(IpLocationService::class);

    $mockRequest->shouldReceive('userAgent')->andReturn('Mozilla/5.0');
    $mockRequest->shouldReceive('ip')->andReturn('192.168.1.1');

    $mockIpLocationService->shouldReceive('getLocationData')
        ->with('192.168.1.1')
        ->andReturn([]);

    $helper = new UserAgentHelper($mockRequest, $mockIpLocationService);
    $location = $helper->getLocation();

    expect($location)->toBe([]);
});
