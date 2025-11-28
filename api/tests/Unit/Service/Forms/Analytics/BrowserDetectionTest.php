<?php

use App\Service\Forms\Analytics\BrowserDetection;

afterEach(function () {
    \Mockery::close();
});

it('detects Chrome browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Chrome');
    expect($result['browser_version'])->toBe(91);
    expect($result['browser_chrome_original'])->toBe(1);
    expect($result['browser_chromium_version'])->toBe(91);
});

it('detects Firefox browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Firefox');
    expect($result['browser_version'])->toBe(89);
    expect($result['browser_firefox_original'])->toBe(1);
    expect($result['browser_gecko_version'])->toBe(89);
});

it('detects Safari browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Safari');
    expect($result['browser_version'])->toBe(14.1);
    expect($result['browser_safari_original'])->toBe(1);
    expect($result['browser_webkit_version'])->toBe(605.1);
});

it('detects Edge browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Edge');
    expect($result['browser_version'])->toBe(91.0);
});

it('detects Windows OS correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    $result = $detector->getOS($userAgent);

    expect($result['os_name'])->toBe('Windows');
    expect($result['os_version'])->toBe('10');
    expect($result['os_family'])->toBe('windows');
    expect($result['os_type'])->toBe('desktop');
    expect($result['64bits_mode'])->toBe(1);
});

it('detects macOS correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';

    $result = $detector->getOS($userAgent);

    expect($result['os_name'])->toBe('MacOS');
    expect($result['os_family'])->toBe('macintosh');
    expect($result['os_type'])->toBe('desktop');
    expect($result['64bits_mode'])->toBe(1);
});

it('detects Android OS correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36';

    $result = $detector->getOS($userAgent);

    expect($result['os_name'])->toBe('Android');
    expect($result['os_version'])->toBe(11.0);
    expect($result['os_family'])->toBe('android');
    expect($result['os_type'])->toBe('mobile');
});

it('detects iOS correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15';

    $result = $detector->getOS($userAgent);

    expect($result['os_name'])->toBe('iOS');
    expect($result['os_version'])->toBe(14.6);
    expect($result['os_family'])->toBe('macintosh');
    expect($result['os_type'])->toBe('mobile');
    expect($result['64bits_mode'])->toBe(1);
});

it('detects Linux OS correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';

    $result = $detector->getOS($userAgent);

    expect($result['os_name'])->toBe('Ubuntu');
    expect($result['os_family'])->toBe('linux');
    expect($result['os_type'])->toBe('desktop');
    expect($result['64bits_mode'])->toBe(1);
});

it('detects desktop device type', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    $result = $detector->getDevice($userAgent);

    expect($result['device_type'])->toBe('desktop');
});

it('detects mobile device type', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15';

    $result = $detector->getDevice($userAgent);

    expect($result['device_type'])->toBe('mobile');
});

it('detects tablet device type', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15';

    $result = $detector->getDevice($userAgent);

    expect($result['device_type'])->toBe('mobile'); // iPads are classified as mobile
});

it('detects TV device type', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (SmartTV; Linux; Tizen 6.0) AppleWebKit/537.36 TV Safari/537.36';

    $result = $detector->getDevice($userAgent);

    expect($result['device_type'])->toBe('tv');
});

it('detects console device type', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (PlayStation 4 3.11) AppleWebKit/537.73';

    $result = $detector->getDevice($userAgent);

    expect($result['device_type'])->toBe('console');
});

it('detects mobile Safari correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Safari Mobile');
    expect($result['browser_version'])->toBe(14.1);
    expect($result['browser_ios_webview'])->toBe(0);
});

it('detects Chrome Mobile correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Chrome');
    expect($result['browser_version'])->toBe(91);
    expect($result['browser_chrome_original'])->toBe(1);
});

it('detects Samsung Browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Samsung Browser');
    expect($result['browser_version'])->toBe(14.2);
});

it('detects Opera browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 OPR/77.0.4054.277';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Opera');
    expect($result['browser_version'])->toBe(77.0);
});

it('detects Android WebView correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/91.0.4472.120 Mobile Safari/537.36';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_android_webview'])->toBe(1);
});

it('detects iOS WebView correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('WKWebView');
    expect($result['browser_ios_webview'])->toBe(1);
});

it('returns all data with getAll method', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    $result = $detector->getAll($userAgent);

    expect($result)->toHaveKeys([
        'os_type',
        'os_family',
        'os_name',
        'os_version',
        'os_title',
        'device_type',
        'browser_name',
        'browser_version',
        'browser_title',
        'browser_chrome_original',
        'browser_firefox_original',
        'browser_safari_original',
        'browser_chromium_version',
        'browser_gecko_version',
        'browser_webkit_version',
        'browser_android_webview',
        'browser_ios_webview',
        'browser_desktop_mode',
        '64bits_mode'
    ]);

    expect($result['browser_name'])->toBe('Chrome');
    expect($result['os_name'])->toBe('Windows');
    expect($result['device_type'])->toBe('desktop');
});

it('returns JSON format when requested', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    $result = $detector->getAll($userAgent, 'json');

    expect($result)->toBeString();

    $decoded = json_decode($result, true);
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKey('browser_name');
    expect($decoded)->toHaveKey('os_name');
});

it('handles unknown user agents gracefully', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Unknown/1.0';

    $result = $detector->getAll($userAgent);

    expect($result['browser_name'])->toBe('unknown');
    expect($result['os_name'])->toBe('unknown');
    expect($result['device_type'])->toBe('unknown');
});

it('handles empty user agent', function () {
    $detector = new BrowserDetection();
    $userAgent = '';

    $result = $detector->getAll($userAgent);

    expect($result['browser_name'])->toBe('unknown');
    expect($result['os_name'])->toBe('unknown');
    expect($result['device_type'])->toBe('unknown');
});

it('detects Facebook App correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/325.0.0.44.273;]';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Facebook App');
    expect($result['browser_version'])->toBe(325.0);
});

it('detects Instagram App correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 190.0.0.32.124';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Instagram App');
    expect($result['browser_version'])->toBe(190.0);
});

it('detects touch support mode', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    // Set touch support and get result
    $detector->setTouchSupport();
    $result = $detector->getAll($userAgent);

    // This should detect desktop mode on mobile when touch support is enabled
    expect($result)->toHaveKey('browser_desktop_mode');
});

it('detects Yandex Browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 YaBrowser/21.6.0.616 Safari/537.36';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Yandex Browser');
    expect($result['browser_version'])->toBe(21.6);
});

it('detects Internet Explorer correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('Internet Explorer');
    expect($result['browser_version'])->toBe(11);
});

it('detects UC Browser correctly', function () {
    $detector = new BrowserDetection();
    $userAgent = 'Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36 UCBrowser/13.4.0.1306';

    $result = $detector->getBrowser($userAgent);

    expect($result['browser_name'])->toBe('UC Browser');
    expect($result['browser_version'])->toBe(13.4);
});
