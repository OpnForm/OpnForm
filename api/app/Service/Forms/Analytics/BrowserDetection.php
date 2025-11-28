<?php

/**
 *
 * PHP Browser Detection Class
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2020-2024 Artem Murugov <murugov@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @version 2.8
 * @last-modified September 3, 2024
 * @link https://github.com/foroco/php-browser-detection
 */

namespace App\Service\Forms\Analytics;

class BrowserDetection
{
    public $useragent;
    private $get_mode;
    private $touch_support_mode;
    private $real_os_name;
    private $macos_version_minor;
    private $result_ios;
    private $result_mobile;
    private $result_os_type;
    private $result_os_family;
    private $result_os_name;
    private $result_os_version;
    private $result_os_title;
    private $result_device_type;
    private $result_browser_name;
    private $result_browser_version;
    private $result_browser_title;
    private $result_browser_chromium_version;
    private $result_browser_gecko_version;
    private $result_browser_webkit_version;
    private $result_browser_chrome_original;
    private $result_browser_firefox_original;
    private $result_browser_safari_original;
    private $result_browser_android_webview;
    private $result_browser_ios_webview;
    private $result_browser_desktop_mode;
    private $result_64bits_mode;

    /**
     * Reset all common defined properties method
     *
     * @return null
     */

    private function resetProperties()
    {
        $this->real_os_name = '';
        $this->result_ios = false;
        $this->result_mobile = 0;
        $this->macos_version_minor = 0;
        $this->result_os_type = 'unknown';
        $this->result_os_family = 'unknown';
        $this->result_os_name = 'unknown';
        $this->result_os_version = 0;
        $this->result_os_title = 'unknown';
        $this->result_device_type = 'unknown';
        $this->result_browser_name = 'unknown';
        $this->result_browser_version = 0;
        $this->result_browser_title = 'unknown';
        $this->result_browser_chromium_version = 0;
        $this->result_browser_gecko_version = 0;
        $this->result_browser_webkit_version = 0;
        $this->result_browser_chrome_original = 0;
        $this->result_browser_firefox_original = 0;
        $this->result_browser_safari_original = 0;
        $this->result_browser_android_webview = 0;
        $this->result_browser_ios_webview = 0;
        $this->result_browser_desktop_mode = 0;
        $this->result_64bits_mode = 0;
        return null;
    }

    /**
     * Common User-Agent matching
     *
     * @param string $data The string to data search for
     * @param boolean $case_s Determines if we do a case-sensitive search (false) or a case insensitive (true)
     * @return boolean Returns true if $data found in $useragent property, false otherwise.
     */

    private function match_ua($data, $case_s = false)
    {
        if (empty($data)) {
            return false;
        }

        if (substr($data, 0, 1) === '/' && substr($data, -1) === '/') {
            if ($case_s == true) {
                $data = $data . 'i';
            }
            if (preg_match($data, $this->useragent, $matches)) {
                if (!isset($matches[0])) {
                    $matches[0] = 0;
                }
                if (!isset($matches[1])) {
                    $matches[1] = 0;
                }
                if (!isset($matches[2])) {
                    $matches[2] = 0;
                }
                return $matches;
            } else {
                return false;
            }
        } else {
            $data_a = explode('|', $data);
            foreach ($data_a as $v) {
                if ($case_s == false) {
                    if (strpos($this->useragent, $v) !== false) {
                        return true;
                    }
                } else {
                    if (stripos($this->useragent, $v) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Method to call User-Agent matching method if matching data is case insensitive
     *
     * @param string $data The string to data search for
     * @return boolean Returns true if case insensitive $data found in $useragent property, false otherwise.
     */

    private function matchi_ua($data)
    {
        return $this->match_ua($data, true);
    }

    /**
     * Detect iOS
     * The method will try to recognize iOS signs from $useragent property and will placed true in result_ios property if iOS found, false otherwise. Also returns true if iOS found, false otherwise
     *
     * @return bool
     */

    private function match_ios()
    {
        if ($this->match_ua('iPhone|iphone|iPad;|iPod') && !$this->match_ua('x86_64|i386')) {
            $this->result_ios = true;
            return true;
        }
        return false;
    }

    /**
     * Detect mobile OS
     * The method will try to recognize mobile OS signs from $useragent property and will placed true in result_mobile property if mobile OS found, false otherwise.
     *
     * @return null
     */

    private function match_mobile()
    {
        // Match 64 bits Windows Desktop OS

        if ($this->match_ua('WOW64|Win64')) {
            $this->result_64bits_mode = 1;
            return null;
        }

        // Match Windows Desktop OS

        if ($this->match_ua('Windows NT')) {
            return null;
        }

        // Match Qt embedded system

        if ($this->match_ua('QtEmbedded;')) {
            return null;
        }

        // Match Android OS

        if ($this->match_ua('Android')) {
            $this->result_mobile = 1;
            return null;
        }

        // Match iOS

        if ($this->match_ios()) {
            $this->result_mobile = 1;
            return null;
        }

        // Match iOS browsers in Desktop Mode

        if ($this->match_ua('Mac OS X')) {
            if ($this->match_ua('/Mac\sOS\sX.*iOS/')) {
                $this->result_mobile = 1;
            }
            return null;
        }

        // Match Android browsers in Desktop Mode

        if ($this->match_ua('/X11\;(?:[U\;\s]+)?\sLinux/') && $this->match_ua('Version/4.0 Chrome/|SamsungBrowser|Miui|XiaoMi|EdgA|Puffin|UCBrowser|JioPages|Ecosia android')) {
            $this->result_mobile = 1;
            return null;
        }

        // Match other mobile signs

        if ($this->matchi_ua('mobile|tablet') || $this->match_ua('BlackBerry|BB10;|MIDP|PlayBook|Windows Phone|Windows Mobile|Windows CE|IEMobile|Opera Mini|OPiOS|Opera Mobi|CrKey armv|Kindle|Silk/|Bada|Tizen|Lumia|Symbian|SymbOS|(Series|PalmOS|PalmSource|Dolfin|Crosswalk|Obigo|MQQBrowser|CriOS|WhatsApp/') || $this->matchi_ua('nokia|playstation|watch')) {
            $this->result_mobile = 1;
        }
        return null;
    }

    /**
     * Convert MacOS numeric version to MacOS codename
     *
     * @param int $version The given MacOS version
     * @return string Returns MacOS codename string
     */

    private function macos_codename($version)
    {
        switch ($version) {
            case 0:
                $result_codename = 'Cheetah';
                break;
            case 1:
                $result_codename = 'Puma';
                break;
            case 2:
                $result_codename = 'Jaguar';
                break;
            case 3:
                $result_codename = 'Panther';
                break;
            case 4:
                $result_codename = 'Tiger';
                break;
            case 5:
                $result_codename = 'Leopard';
                break;
            case 6:
                $result_codename = 'Snow Leopard';
                break;
            case 7:
                $result_codename = 'Lion';
                break;
            case 8:
                $result_codename = 'Mountain Lion';
                break;
            case 9:
                $result_codename = 'Mavericks';
                break;
            case 10:
                $result_codename = 'Yosemite';
                break;
            case 11:
                $result_codename = 'El Capitan';
                break;
            case 12:
                $result_codename = 'Sierra';
                break;
            case 13:
                $result_codename = 'High Sierra';
                break;
            case 14:
                $result_codename = 'Mojave';
                break;
            case 15:
                $result_codename = 'Catalina';
                break;
            case 16:
                $result_codename = 'Big Sur';
                break;
            case 17:
                $result_codename = 'Monterey';
                break;
            case 18:
                $result_codename = 'Ventura';
                break;
            default:
                $result_codename = 'New';
                break;
        }
        return $result_codename;
    }

    /**
     * Common User-Agent parsing
     * The method will try to recognize environment data from $useragent property and will placed it in defined properties.
     *
     * @return null
     */

    private function getResult()
    {
        // Detect mobile device

        $this->match_mobile();

        // Detect mobile browser Desktop Mode

        if ($this->touch_support_mode == true) {
            // Android Desktop Mode

            if ($this->match_ua('X11; Linux x86_64|X11; Linux aarch64|X11; U; U; Linux x86_64|X11; Linux; U;|X11; Linux zbov') && !$this->match_ua('kded/|kioclient|queror|Goanna|Epiphany|Brick|Iceweasel|SeaMonkey|Thunderbird|Qt|Arora|Ubuntu|Debian|Fedora|Linux Mint|elementary|Raspbian|Slackware|ArchLinux|Gentoo')) {
                $this->real_os_name = 'Android';
                $this->result_mobile = 1;
                $this->result_browser_desktop_mode = 1;
            }

            // iOS Desktop Mode

            if ($this->match_ua('Intel Mac OS X')) {
                $this->real_os_name = 'iOS';
                $this->result_mobile = 1;
                $this->result_browser_desktop_mode = 1;
            }
        }

        // Windows Phone Desktop Mode

        if ($this->match_ua('WPDesktop;')) {
            $this->real_os_name = 'Windows Phone';
            $this->result_mobile = 1;
            $this->result_browser_desktop_mode = 1;
        }

        // Android Desktop Mode without touch support check

        if ($this->result_mobile == 1 && $this->result_os_type === 'unknown') {
            if ($this->match_ua('/X11\;(?:[U\;\s]+)?\sLinux/') && $this->match_ua('Version/4.0 Chrome/|SamsungBrowser|Miui|XiaoMi|EdgA|Puffin|UCBrowser|JioPages|Ecosia android')) {
                $this->real_os_name = 'Android';
                $this->result_browser_desktop_mode = 1;
            }
        }

        // iOS Desktop Mode without touch support check

        if ($this->result_mobile == 1 && $this->result_ios == false && $this->result_os_type === 'unknown') {
            if ($this->match_ua('/Mac\sOS\sX.*iOS/')) {
                $this->real_os_name = 'iOS';
                $this->result_browser_desktop_mode = 1;
            }
        }

        /*
        --------------
         OS DETECTION
        --------------

        --------------
         Mixed OS(es)
        --------------
        */

        $os_need_continue = true;

        // Windows OS

        if ($this->get_mode !== 'browser' && $this->match_ua('Windows|Win32') && !$this->match_ua('Windows Phone|Windows Mobile|Windows CE|WPDesktop')) {
            $this->result_os_name = 'Windows';
            $matches = $this->match_ua('/Windows ([ .a-zA-Z0-9]+)[;\\)]/');
            $version = is_array($matches) ? $matches[1] : 0;
            if ($version === 'NT 11.0') {
                $this->result_os_version = '11';
            }
            if ($version === 'NT 10.1') {
                $this->result_os_version = '11';
            }
            if ($version === 'NT 10.0') {
                $this->result_os_version = '10';
            }
            if ($version === 'NT 6.4') {
                $this->result_os_version = '10';
            }
            if ($version === 'NT 6.3') {
                $this->result_os_version = '8.1';
            }
            if ($version === 'NT 6.2') {
                $this->result_os_version = '8';
            }
            if ($version === 'NT 6.1') {
                $this->result_os_version = '7';
            }
            if ($version === 'NT 6.0') {
                $this->result_os_version = 'Vista';
            }
            if ($version === 'NT 6') {
                $this->result_os_version = 'NT';
            }
            if ($version === 'NT 5.2') {
                $this->result_os_version = 'XP';
            }
            if ($version === 'NT 5.1') {
                $this->result_os_version = 'XP';
            }
            if ($version === 'NT 5.01') {
                $this->result_os_version = '2000';
            }
            if ($version === 'NT 5.0') {
                $this->result_os_version = '2000';
            }
            if ($version === 'NT 5') {
                $this->result_os_version = '2000';
            }
            if ($version === 'NT 4.0') {
                $this->result_os_version = 'NT 4.0';
            }
            if ($version === 'NT4.0') {
                $this->result_os_version = 'NT 4.0';
            }
            if ($version === 'NT') {
                $this->result_os_version = 'NT';
            }
            if ($version === '98') {
                $this->result_os_version = '98';
            }
            if ($version === '95') {
                $this->result_os_version = '95';
            }
            if ($version === 'ME') {
                $this->result_os_version = 'ME';
            }
            if (empty($this->result_os_version)) {
                if ($this->match_ua('/Win16/')) {
                    $this->result_os_version = '3.1';
                }
                if ($this->match_ua('/(Windows\s95|Win95|Windows_95)/')) {
                    $this->result_os_version = '95';
                }
                if ($this->match_ua('/(Windows\s98|Win98)/')) {
                    $this->result_os_version = '98';
                }
                if ($this->match_ua('/Windows\s2000/')) {
                    $this->result_os_version = '2000';
                }
                if ($this->match_ua('/Win\sNT\s5\.0/')) {
                    $this->result_os_version = '2000';
                }
                if ($this->match_ua('/Windows\sXP/')) {
                    $this->result_os_version = 'XP';
                }
                if ($this->match_ua('/WinNT4\.0/')) {
                    $this->result_os_version = 'NT 4.0';
                }
                if ($this->match_ua('/Windows\sVista/')) {
                    $this->result_os_version = 'Vista';
                }
                if ($this->match_ua('/Windows\s7/')) {
                    $this->result_os_version = '7';
                }
                if ($this->match_ua('/Windows\s8/')) {
                    $this->result_os_version = '8';
                }
                if ($this->match_ua('/Windows\s8.1/')) {
                    $this->result_os_version = '8.1';
                }
                if ($this->match_ua('/Windows\s10/')) {
                    $this->result_os_version = '10';
                }
                if ($this->match_ua('/Windows\s11/')) {
                    $this->result_os_version = '11';
                }
            }
            if (!empty($this->result_os_version)) {
                $this->result_os_title = 'Windows ' . $this->result_os_version;
            } else {
                $this->result_os_title = 'Windows (unknown version)';
            }
            if (intval($this->result_os_version) < 7 || intval($this->result_os_version) > 10) {
                $this->result_os_type = 'desktop';
            } else {
                $this->result_os_type = 'mixed';
            }
            $this->result_os_family = 'windows';
            $os_need_continue = false;
        }

        /*
        -------------
         Desktop OS
        -------------
        */

        if ($this->get_mode !== 'browser' && $this->result_mobile == 0) {
            $this->result_os_type = 'desktop';

            // Mac OS (X) / macOS

            if ($os_need_continue && $this->match_ua('Mac OS X|Mac_PowerPC|Macintosh|Mac_68K') && !$this->match_ua('AmigaOS')) {
                $this->result_os_version = 0;
                $this->result_os_name = 'MacOS';
                if ($this->match_ua('Mac OS X')) {
                    $matches = $this->match_ua('/Mac OS X (\d+)[_.](\d+)(?:[_.](\d+)|)/');

                    $version = isset($matches[1]) ? $matches[1] : 0;
                    $version_minor = isset($matches[2]) ? $matches[2] : 0;
                    $version_revision = isset($matches[3]) ? $matches[3] : -1;

                    // macOS version to minor version conversion (needs since Big Sur)
                    if ($version == 10 && $version_minor == 0) {
                        $version_minor = 16;
                    }
                    if ($version == 11) {
                        $version_minor = 16;
                    }
                    if ($version == 12) {
                        $version_minor = 17;
                    }

                    // macOS with a particular major/minor/revision version structure (needs since Big Sur)
                    if ($version == 10 && $version_minor == 15 && $version_revision == 7) {
                        $version_minor = 16;
                    }
                    if ($version == 10 && $version_minor == 16 && $version_revision == 0) {
                        $version_minor = 17;
                    }

                    if (!empty($version_minor)) {
                        if ($version >= 10) {
                            $this->result_os_version = $this->macos_codename($version_minor);
                        }
                        $this->macos_version_minor = $version_minor;
                    }

                    if (!empty($this->result_os_version)) {
                        $this->result_os_title = 'MacOS ' . $this->result_os_version;
                    } else {
                        $this->result_os_title = 'MacOS (unknown version)';
                    }
                } else {
                    $this->result_os_title = 'MacOS';
                }
                $this->result_os_family = 'macintosh';
                $os_need_continue = false;
            }

            // Linux family OS(es)

            if ($os_need_continue) {
                $os_list[] = array('Ubuntu' => '/Ubuntu(?: )?(?:\/)?([0-9]+\.[0-9]+)/');
                $os_list[] = array('Kubuntu' => '/Kubuntu(?: )?(?:\/)?([0-9]+\.[0-9]+)/');
                $os_list[] = array('Linux Mint' => '/Linux Mint\/([.0-9]+)/');
                $os_list[] = array('CentOS' => '/CentOS\/([0-9]+\.[0-9]+)/');
                $os_list[] = array('OpenSUSE' => '/SUSE\/([0-9]+\.[0-9]+)/');
                $os_list[] = array('Red Hat' => '/Red\sHat\/([0-9]+\.[0-9]+)/');

                foreach ($os_list as $os_list_va) {
                    $k = key($os_list_va);
                    $v = $os_list_va[$k];

                    $matches = $this->match_ua($v);
                    if ($matches) {
                        $this->result_os_family = 'linux';
                        $this->result_os_name = $k;
                        $this->result_os_version = is_array($matches) ? (float)$matches[1] : 0;
                        $os_need_continue = false;
                        break;
                    }
                }
                $os_list = null;
            }

            // Other Desktop OS(es)

            if ($os_need_continue) {
                $other_os = array();

                $other_os[] = array('Chrome OS' => 'CrOS');
                $other_os[] = array('Chrome OS' => 'Chromium OS');
                $other_os[] = array('Linux Mint' => 'Linux Mint');
                $other_os[] = array('Kubuntu' => 'Kubuntu');
                $other_os[] = array('Ubuntu' => 'Ubuntu');
                $other_os[] = array('Ubuntu' => 'ubuntu');
                $other_os[] = array('Debian' => 'Debian');
                $other_os[] = array('CentOS' => 'CentOS');
                $other_os[] = array('Fedora' => 'Fedora');
                $other_os[] = array('Arch Linux' => 'ArchLinux');
                $other_os[] = array('OpenSUSE' => 'SUSE');
                $other_os[] = array('Red Hat' => 'Red Hat');
                $other_os[] = array('Elementary' => ' elementary');
                $other_os[] = array('OpenBSD' => 'OpenBSD');
                $other_os[] = array('NetBSD' => 'NetBSD');
                $other_os[] = array('FreeBSD' => 'FreeBSD');
                $other_os[] = array('SunOS' => 'SunOS');
                $other_os[] = array('RISC OS' => 'RISC OS');
                $other_os[] = array('Linux' => 'Linux');
                $other_os[] = array('Linux' => 'X11;');
                $other_os[] = array('Linux' => 'Mozilla/5.0 (x86_64)');
                $other_os[] = array('Linux' => 'Mozilla/5.0 (i686)');
                $other_os[] = array('Linux' => 'U; NETFLIX');
                $other_os[] = array('Linux' => 'GNU; ');
                $other_os[] = array('Linux' => 'QtEmbedded; U; Linux;');
                $other_os[] = array('AmigaOS' => 'AmigaOS');
                $other_os[] = array('Haiku' => 'Haiku');
                $other_os[] = array('Roku OS' => 'Roku/');
                $other_os[] = array('BeOS' => 'BeOS');

                foreach ($other_os as $other_os_va) {
                    $k = key($other_os_va);
                    $v = $other_os_va[$k];

                    if ($this->match_ua($v)) {
                        $this->result_os_family = 'linux';
                        if ($k === 'AmigaOS') {
                            $this->result_os_family = 'amiga';
                        }
                        if ($k === 'Haiku' || $k === 'BeOS') {
                            $this->result_os_family = 'beos';
                        }
                        if ($k === 'OpenBSD' || $k === 'NetBSD' || $k === 'FreeBSD' || $k === 'SunOS' || $k === 'RISC OS') {
                            $this->result_os_family = 'unix';
                        }
                        $this->result_os_version = 0;
                        $this->result_os_name = $k;
                        $this->result_os_title = $k;
                        $os_need_continue = false;
                        break;
                    }
                }
                $other_os = null;
            }

            // End of desktop OS detection
        }

        /*
        -------------
         Mobile OS
        -------------
        */

        if ($this->result_mobile == 1) {
            if ($this->result_os_type === 'unknown') {
                $this->result_os_type = 'mobile';
            }

            // Desktop Mode on generic mobile OS

            // Android Desktop Mode

            if ($os_need_continue && $this->result_browser_desktop_mode == 1 && $this->real_os_name === 'Android') {
                $this->result_os_version = 0;
                $this->result_os_name = 'Android';
                $this->result_os_family = 'android';
                $os_need_continue = false;
            }

            // iOS Desktop Mode

            if ($os_need_continue && $this->result_browser_desktop_mode == 1 && $this->real_os_name === 'iOS') {
                $this->result_os_version = 0;
                $this->result_os_name = 'iOS';
                $this->result_os_family = 'macintosh';
                $os_need_continue = false;
            }

            // WP Desktop Mode

            if ($os_need_continue && $this->result_browser_desktop_mode == 1 && $this->real_os_name === 'Windows Phone') {
                $this->result_os_version = 0;
                $this->result_os_name = 'Windows Phone';
                $this->result_os_family = 'windows';
                $os_need_continue = false;
            }

            // Android OS

            if ($os_need_continue && $this->match_ua('Android') && !$this->match_ua('Windows Phone|Windows Mobile|Tizen')) {
                $this->result_os_version = 0;
                $this->result_os_name = 'Android';
                $matches = $this->match_ua('/Android(?: |\-)([0-9]+\.[0-9]+)/');
                $this->result_os_version = is_array($matches) ? (float)$matches[1] : 0;
                if (empty($this->result_os_version)) {
                    $matches = $this->match_ua('/Android(?: |\-)(\d+)/');
                    $this->result_os_version = is_array($matches) ? (float)$matches[1] : 0;
                }
                $this->result_os_family = 'android';
                $os_need_continue = false;
            }

            // iOS OS

            if ($os_need_continue && $this->result_ios != false && !$this->match_ua('Windows Phone|Windows Mobile')) {
                $this->result_os_version = 0;
                $this->result_os_name = 'iOS';
                $matches = $this->match_ua('/\sOS\s(\d+)[_.](\d+)/');
                $version = is_array($matches) ? $matches[1] : 0;
                $version_minor = is_array($matches) ? $matches[2] : 0;
                if (!empty($version)) {
                    $this->result_os_version = floatval($version . '.' . $version_minor);
                }
                $this->result_os_family = 'macintosh';
                $os_need_continue = false;
            }

            // WhatsApp Android / iOS

            if ($os_need_continue && $this->match_ua('WhatsApp/')) {
                $matches = $this->match_ua('/WhatsApp\/([.0-9]+)\s(A|i)$/');
                $os_match = is_array($matches) ? $matches[2] : 0;

                if ($os_match === 'A') {
                    $this->result_os_name = 'Android';
                    $this->result_os_family = 'android';
                }

                if ($os_match === 'i') {
                    $this->result_os_name = 'iOS';
                    $this->result_os_family = 'macintosh';
                }
            }

            // Windows Phone OS, Tizen OS, Bada OS, Kindle OS, FIre OS, Java Platform

            if ($os_need_continue) {
                $os_list[] = array('Windows Phone' => '/Phone(?: OS)? ([.0-9]+)/');
                $os_list[] = array('Windows Mobile' => '/Windows Mobile ([.0-9]+)/');
                $os_list[] = array('Windows Mobile' => 'Windows Mobile');
                $os_list[] = array('Windows CE' => 'Windows CE');
                $os_list[] = array('Firefox OS' => '/Mozilla\/5\.0\s\((?:Mobile|Tablet);(?:.*;)?\srv:[.0-9]+\)\sGecko\/[.0-9]+\sFirefox\/[.0-9]+$/');
                $os_list[] = array('Tizen' => '/Tizen[\/\s]([.0-9]+)/');
                $os_list[] = array('Bada' => '/Bada[;\/]([.0-9]+)/');
                $os_list[] = array('Kindle' => '/Kindle\/([0-9]+\.[0-9]+)/');
                $os_list[] = array('Fire OS' => 'KFJW|Silk/');
                $os_list[] = array('Java Platform' => '/MIDP\-([0-9]+\.[0-9]+)/');
                $os_list[] = array('Java Platform' => '/MIDP/');
                $os_list[] = array('MAUI Platform' => '/MAUI\s|\sMAUI/');

                foreach ($os_list as $os_list_va) {
                    $k = key($os_list_va);
                    $v = $os_list_va[$k];

                    $matches = $this->match_ua($v);
                    if ($matches) {
                        $this->result_os_family = 'linux';
                        if (strpos($k, 'Windows') !== false) {
                            $this->result_os_family = 'windows';
                        }
                        $this->result_os_name = $k;
                        $this->result_os_version = is_array($matches) ? (float)$matches[1] : 0;
                        $os_need_continue = false;

                        // J2ME/MIDP or MAUI

                        if (strpos($k, 'Java Platform') !== false || strpos($k, 'MAUI Platform') !== false) {
                            $this->result_os_family = 'unknown';
                            $os_need_continue = true;
                        }
                        break;
                    }
                }
                $os_list = null;
            }

            // Other Mobile OS(es)

            if ($os_need_continue) {
                $other_os = array();

                $other_os[] = array('Android' => 'CrKey armv');
                $other_os[] = array('Android' => 'SpreadTrum;');
                $other_os[] = array('BlackBerry' => 'BlackBerry');
                $other_os[] = array('BlackBerry' => 'BB10;');
                $other_os[] = array('BlackBerry' => 'RIM Tablet');
                $other_os[] = array('Symbian' => 'Symbian');
                $other_os[] = array('Symbian' => '(Series');
                $other_os[] = array('Symbian' => 'SymbOS');
                $other_os[] = array('WatchOS' => 'Watch');
                $other_os[] = array('KaiOS' => 'KAIOS');
                $other_os[] = array('RemixOS' => 'Remix Mini');
                $other_os[] = array('RemixOS' => 'RemixOS');
                $other_os[] = array('Sailfish OS' => 'Sailfish');
                $other_os[] = array('LiveArea' => 'PlayStation Vita');
                $other_os[] = array('PalmOS' => 'PalmOS');
                $other_os[] = array('PalmOS' => 'PalmSource');
                $other_os[] = array('Maemo' => 'Maemo');
                $other_os[] = array('PlayStation Platform' => 'PlayStation');
                $other_os[] = array('PlayStation Platform' => 'PLAYSTATION');

                foreach ($other_os as $other_os_va) {
                    $k = key($other_os_va);
                    $v = $other_os_va[$k];

                    if ($this->match_ua($v)) {
                        if ($k === 'Android') {
                            $this->result_os_family = 'android';
                        }
                        if ($k === 'BlackBerry') {
                            $this->result_os_family = 'blackberry';
                        }
                        if ($k === 'Symbian') {
                            $this->result_os_family = 'symbian';
                        }
                        if ($k === 'WatchOS') {
                            $this->result_os_family = 'macintosh';
                        }
                        if ($k === 'Sailfish OS' || $k === 'KaiOS' || $k === 'Maemo') {
                            $this->result_os_family = 'linux';
                        }
                        if ($k === 'RemixOS') {
                            $this->result_os_family = 'android';
                        }
                        if ($k === 'LiveArea') {
                            $this->result_os_family = 'livearea';
                        }
                        if ($k === 'PalmOS') {
                            $this->result_os_family = 'palm';
                        }
                        if ($k === 'PlayStation Platform') {
                            $this->result_os_family = 'unix';
                        }
                        $this->result_os_version = 0;
                        $this->result_os_name = $k;
                        $this->result_os_title = $k;
                        $os_need_continue = false;
                        break;
                    }
                }

                $other_os = null;
            }

            // End of mobile OS detection
        }

        // Other mixed-type linux-family OS(es)

        if ($this->get_mode !== 'browser' && ($this->result_os_family === 'linux' || $this->result_os_family === 'unknown')) {
            $other_os = array();

            $other_os[] = array('WebOS' => 'hpwOS');
            $other_os[] = array('WebOS' => 'Web0S');
            $other_os[] = array('WebOS' => 'WebOS');
            $other_os[] = array('WebOS' => 'webOS');

            foreach ($other_os as $other_os_va) {
                $k = key($other_os_va);
                $v = $other_os_va[$k];

                if ($this->match_ua($v)) {
                    $this->result_os_family = 'linux';
                    $this->result_os_type = 'mixed';
                    $this->result_os_version = 0;
                    $this->result_os_name = $k;
                    $this->result_os_title = $k;
                    break;
                }
            }
            $other_os = null;
        }

        // Darwin (MacOS and iOS)

        if ($os_need_continue && $this->match_ua(' Darwin') && !$this->match_ua('X11;')) {
            $this->result_os_family = 'macintosh';

            if ($this->match_ua('x86_64|i386|Mac')) {
                $this->result_os_type = 'desktop';
                $this->result_os_name = 'MacOS';
            } else {
                $this->result_os_type = 'mobile';
                $this->result_mobile = 1;
                $this->result_os_name = 'iOS';
                $this->result_ios = true;
            }

            $this->result_os_version = 0;
            $darwin_os_version = 0;

            $matches = $this->match_ua('/\sDarwin(\s|\/)([0-9]+\.[0-9]+)/');
            if (!empty($matches[2])) {
                $darwin_os_version = (float)$matches[2];
            }

            $darwin_macos_map = array('1.3' => '0', '1.4' => '1', '5.1' => '1', '5.5' => '1', '6.0' => '2', '6.8' => '2', '7.0' => '3', '7.9' => '3', '8.0' => '4', '8.1' => '4', '9.0' => '5', '9.8' => '5', '10.0' => '6', '10.8' => '6', '11.0' => '7', '11.4' => '7', '12.0' => '8', '12.6' => '8', '13.0' => '9', '13.4' => '9', '14.0' => '10', '14.5' => '10', '15.0' => '11', '15.6' => '11', '16.0' => '12', '16.6' => '12', '17.0' => '13', '17.7' => '13', '18.0' => '14', '18.2' => '14', '19.0' => '15', '19.6' => '15', '20.0' => '16', '20.6' => '16', '21.0' => '17', '21.6' => '17', '22.0' => '18', '22.3' => '18');
            $darwin_ios_map = array('9.0' => '1', '9.8' => '1', '10.0' => '4', '10.8' => '4', '11.0' => '5', '11.4' => '5', '12.0' => '6', '13.0' => '7', '14.0' => '8', '15.0' => '9', '15.6' => '9', '16.0' => '10', '16.6' => '10', '17.0' => '11', '17.7' => '11', '18.0' => '12', '18.2' => '12', '19.0' => '13', '19.6' => '13', '20.0' => '14', '20.6' => '14', '21.0' => '15', '21.6' => '15', '22.0' => '16', '22.3' => '16');

            if ($this->result_os_name === 'MacOS') {
                $darwin_map = $darwin_macos_map;
            } else {
                $darwin_map = $darwin_ios_map;
            }

            foreach ($darwin_map as $k => $v) {
                $darwin_map_min[$k] = abs($k - $darwin_os_version);
            }

            asort($darwin_map_min);
            $darwin_map_key = key($darwin_map_min);
            $this->result_os_version = $darwin_map[$darwin_map_key];

            if ($this->result_os_name === 'MacOS') {
                $this->result_os_version = $this->macos_codename($darwin_map[$darwin_map_key]);
                if (!empty($this->result_os_version)) {
                    $this->result_os_title = 'MacOS ' . $this->result_os_version;
                } else {
                    $this->result_os_title = 'MacOS';
                }
            }

            $darwin_map = null;
            $darwin_map_min = null;
            $darwin_macos_map = null;
            $darwin_ios_map = null;
            $os_need_continue = false;
        }

        // Set OS title and type

        if ($this->get_mode !== 'browser' && $this->result_os_name !== 'MacOS') {
            if (!empty($this->result_os_version)) {
                $this->result_os_title = $this->result_os_name . ' ' . $this->result_os_version;
            } else {
                if ($this->result_os_name !== 'Windows') {
                    $this->result_os_title = $this->result_os_name;
                } else {
                    $this->result_os_title = $this->result_os_name . ' (unknown version)';
                }
            }
            if (strpos($this->result_os_name, 'unknown') !== false) {
                $this->result_os_type = 'unknown';
            }
            if ($this->result_os_version == null) {
                $this->result_os_version = 0;
            }
        }

        /*
        ---------------------
         Detect 64bits mode
        ---------------------
        */

        if ($this->result_os_name === 'iOS' && $this->result_os_version >= 11) {
            $this->result_64bits_mode = 1;
        }
        if ($this->result_os_name === 'MacOS' && $this->macos_version_minor >= 8) {
            $this->result_64bits_mode = 1;
        }

        if ($this->result_64bits_mode == 0) {
            if ($this->match_ua('64') && $this->match_ua('x64;|x86_64|x86-64|arm64|arm_64|aarch64|Linux x64|mips64|amd64|AMD64|ia64|IRIX64|ppc64|sparc64|x64_64')) {
                $this->result_64bits_mode = 1;
            }
        }

        if ($this->result_os_name === 'Android' && $this->result_browser_desktop_mode == 1) {
            $this->result_64bits_mode = 0;
        }
        if ($this->result_64bits_mode == 1 && $this->match_ua('86') && $this->match_ua('i386|i686')) {
            $this->result_64bits_mode = 0;
        }

        // If mode 'os' only

        if ($this->get_mode === 'os') {
            return null;
        }

        /*
        -------------------
         BROWSER DETECTION
        -------------------

        ---------------------
         Mixed-type Browsers
        ---------------------
        */

        $browser_need_continue = true;
        if ($this->get_mode === 'device') {
            $browser_need_continue = false;
        }

        // Chrome / Chromium

        if ($browser_need_continue && $this->match_ua('Chrome/|Chromium/|CriOS/|CrMo/')) {
            $this->result_browser_version = 0;
            $matches = $this->match_ua('/(Chrome|Chromium|CriOS|CrMo)\/([0-9]+)\.?/');
            $this->result_browser_name = 'Chrome';
            if (!empty($matches[1]) && $matches[1] === 'Chromium') {
                $this->result_browser_name = 'Chromium';
            }
            if (!empty($matches[2])) {
                $this->result_browser_version = (int)$matches[2];
            }
            $this->result_browser_chromium_version = $this->result_browser_version;
            if ($this->match_ua('CriOS/')) {
                $this->result_browser_chromium_version = 0;
            }
            if ($this->match_ua('/Gecko\)\s(Chrome|CrMo)\/(\d+\.\d+\.\d+\.\d+)\s(?:Mobile)?(?:\/[.0-9A-Za-z]+\s|\s)?Safari\/[.0-9]+$/') && !$this->match_ua('SalamWeb| Valve | GOST')) {
                $this->result_browser_chrome_original = 1;
            }
        }

        // Firefox

        if ($browser_need_continue && $this->result_browser_chromium_version == 0 && $this->match_ua('Firefox/') && !$this->match_ua('Focus/|FxiOS|Waterfox|Ghostery:')) {
            $this->result_browser_version = 0;
            $matches = $this->match_ua('/Firefox\/([0-9]+)\./');
            $this->result_browser_name = 'Firefox';
            if (!empty($matches[1])) {
                $this->result_browser_version = (int)$matches[1];
            }
            if ($this->match_ua('/\)\sGecko\/[.0-9]+\sFirefox\/[.0-9]+$/')) {
                $this->result_browser_firefox_original = 1;
            }
        }

        // Cross-device browsers (Yandex, Edge, Firefox, Opera, Puffin, Vivaldi, QQ Browser, Whale, Brave etc)

        if ($browser_need_continue && $this->result_browser_chrome_original == 0 && $this->result_browser_firefox_original == 0) {
            $browser_list[] = array('Yandex Browser', 'YaBrowser', '/YaBrowser\/([0-9]+\.[0-9]+)/', '1', 'YaApp_');
            $browser_list[] = array('Edge', 'Edg', '/Edg(|e|A|iOS)\/([0-9]+)\./', '2', '');
            $browser_list[] = array('Opera', ' OPR/', '/OPR\/(\d+)/', '1', 'Opera Mini|OPiOS|OPT/|OPRGX/|AlohaBrowser');
            $browser_list[] = array('Opera', 'Opera', '/Opera.*Version\/([0-9]+\.[0-9]+)/', '1', 'Opera Mini|OPiOS|OPT/|InettvBrowser/');
            $browser_list[] = array('Opera', 'Opera', '/Opera(\s|\/)([0-9]+\.[0-9]+)/', '2', 'Opera Mini|OPiOS|OPT/|InettvBrowser/');
            $browser_list[] = array('UC Browser', 'UBrowser|UCBrowser|UCMini', '/(UBrowser|UCBrowser|UCMini)\/([0-9]+\.[0-9]+)/', '2', 'UCTurbo|AliApp');
            $browser_list[] = array('UC Browser Turbo', 'UCTurbo/', '/UCTurbo\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Puffin', 'Puffin/', '/Puffin\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Vivaldi', 'Vivaldi/', '/Vivaldi\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('QQ Browser', 'QQBrowser/', '/QQBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Coc Coc', 'coc_coc_browser', '/coc_coc_browser\/([0-9]+)/', '1', '');
            $browser_list[] = array('Whale', 'Whale/', '/Whale\/([0-9]+\.[0-9]+)/', '1', 'NAVER(inapp;');
            $browser_list[] = array('Brave', 'Brave', '/Brave(?: Chrome)?\/([0-9]+)/', '1', '');
            $browser_list[] = array('Maxthon', 'Maxthon/', '/Maxthon\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Maxthon', 'MxBrowser/', '/MxBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('2345 Explorer', '2345Explorer|2345Browser', '/(2345Explorer|2345Browser)(?: |\/)?([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('IceCat', 'IceCat/', '/IceCat\/([0-9]+)/', '1', '');
            $browser_list[] = array('Lunascape', 'Lunascape', '/Lunascape(?: |\/)?([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Seznam Browser', 'Seznam.cz/|SznProhlizec/', '/(Seznam\.cz|SznProhlizec)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Sleipnir', 'Sleipnir/', '/Sleipnir\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Sputnik', 'SputnikBrowser/', '/SputnikBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Oculus Browser', 'OculusBrowser/', '/OculusBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('PaleMoon', 'PaleMoon', '/PaleMoon\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('SalamWeb', 'SalamWeb|Salam Browser', '/SalamWeb\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Swing', 'Swing/|Swing(And)/', '/Swing(?:\(And\))?\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Safe Exam Browser', 'SEB/', '/SEB\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Colibri', 'Colibri/', '/Colibri\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Opera GX', 'OPRGX', '/OPRGX\/([0-9]+)/', '1', '');
            $browser_list[] = array('Opera GX', 'OPX/', '/OPX\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Xvast', 'Xvast/', '/Xvast\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Atom', 'Atom/', '/Atom\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('NetCast', 'SmartTV/', '/SmartTV\/([0-9]+)/', '1', '');
            $browser_list[] = array('LG Browser', ' LG Browser/', '/\sLG\sBrowser\/([0-9]+)/', '1', '');
            $browser_list[] = array('Ghostery Browser', 'Ghostery:', '/Ghostery\:([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Aloha Browser', 'AlohaBrowser/', '/AlohaBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Lenovo Browser', 'SLBrowser/', '/SLBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Samsung Browser TV', '/SamsungBrowser\/[.0-9]+\sTV\s/', '/SamsungBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('WeChat App', 'MicroMessenger/', '/MicroMessenger\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('WKWebView', '/Intel\sMac\sOS\sX\s\d+.*\)\sAppleWebKit\/[.0-9]+.*Gecko\)$/', 'AppleWebKit', '1', '');
            $browser_list[] = array('Sber Browser', 'SberBrowser/', '/SberBrowser\/([0-9]+\.[0-9]+)/', '1', '');

            foreach ($browser_list as $browser_list_va) {
                $match = $browser_list_va[1];
                $match_exclude = $browser_list_va[4];

                if ($this->match_ua($match) && !$this->match_ua($match_exclude)) {
                    $this->result_browser_version = 0;
                    $this->result_browser_name = $browser_list_va[0];
                    $matches = $this->match_ua($browser_list_va[2]);
                    $matches_n = intval($browser_list_va[3]);
                    $this->result_browser_version = is_array($matches) ? (float)$matches[$matches_n] : 0;
                    if (!empty($this->result_browser_version)) {
                        $browser_need_continue = false;
                        break;
                    }
                }
            }

            $browser_list = null;
        }

        // Gecko engine detection

        if ($this->get_mode !== 'device' && $this->result_browser_chromium_version == 0 && $this->match_ua('Gecko/|ArtisBrowser/') && ($this->match_ua('/\srv:[.0-9a-z]+(?:\;\s.*|)\)\sGecko\/[.0-9]+\s/') || $this->match_ua('QwantMobile/|ArtisBrowser/'))) {
            $this->result_browser_gecko_version = 0;
            $match = $this->match_ua('/\srv:([0-9]+\.[0-9]+)(?:[.0-9a-z]+)?(?:\;\s.*|)\)\sGecko\/[.0-9]+\s/');

            if (is_array($match)) {
                if ($match[1] >= 5) {
                    $match[1] = intval($match[1]);
                } else {
                    $match[1] = (float)$match[1];
                }
            }

            if (!empty($match[1])) {
                $this->result_browser_gecko_version = $match[1];
            } else {
                $match = $this->match_ua('/\sGecko\/([.0-9]+)\s.*QwantMobile\/[.0-9]+$/');
                if (!empty($match[1])) {
                    $this->result_browser_gecko_version = intval($match[1]);
                } else {
                    $match = $this->match_ua('/\srv:([0-9]+\.[0-9]+)\)\s.*ArtisBrowser\/[.0-9]+$/');
                    if (!empty($match[1])) {
                        $this->result_browser_gecko_version = intval($match[1]);
                    }
                }
            }

            // Gecko >= 109 issue

            if ($this->result_browser_gecko_version >= 109 && $this->result_browser_gecko_version < 120) {
                $match = $this->match_ua('/\srv:[.0-9a-z]+(?:\;\s.*|)\)\sGecko\/[.0-9]+\s.*(?:Firefox|MaxBrowser)\/([0-9]+)\./');
                if (!empty($match[1])) {
                    $this->result_browser_gecko_version = intval($match[1]);
                }
            }
        }

        // WebKit engine detection

        if ($this->get_mode !== 'device' && $this->result_browser_chromium_version == 0 && $this->result_browser_gecko_version == 0 && $this->matchi_ua('AppleWebKit/')) {
            $this->result_browser_webkit_version = 0;
            $match = $this->matchi_ua('/AppleWebKit\/([0-9]+\.[0-9]+)/');
            if (!empty($match[1])) {
                $this->result_browser_webkit_version = (float)$match[1];
            }
        }

        /*
        ------------------
         Desktop Browsers
        ------------------
        */

        if ($browser_need_continue && $this->result_browser_chrome_original == 0 && $this->result_browser_firefox_original == 0 && $this->result_mobile == 0) {
            // Safari

            $browser_list[] = array('Safari', '/AppleWebKit\/[.0-9]+.*Gecko\)\sSafari\/[.0-9A-Za-z]+$/', '/Safari\/(\d+)/', '1', 'Version/');
            $browser_list[] = array('Safari', '/Version\/([0-9]+\.[0-9]+).*Safari/', '/Version\/([0-9]+\.[0-9]+).*Safari/', '1', 'Epiphany|Arora/|Midori|midori|SlimBoat|Roccat');

            // IE

            $browser_list[] = array('Internet Explorer', 'MSIE', '/MSIE(?:\s|)([0-9]+)/', '1', 'Opera|IEMobile|Trident/');
            $browser_list[] = array('Internet Explorer', 'Trident/', '/Trident\/([0-9]+)/', '1', 'Opera|IEMobile');

            // Chromium based browsers

            $browser_list[] = array('Avast Browser', 'Avast/', '/Avast\/([0-9]+)/', '1', '');
            $browser_list[] = array('AVG Browser', 'AVG/', '/AVG\/([0-9]+)/', '1', '');
            $browser_list[] = array('CCleaner Browser', 'CCleaner/', '/CCleaner\/([0-9]+)/', '1', '');
            $browser_list[] = array('Comodo Dragon', 'Dragon/', '/Dragon\/([0-9]+)/', '1', 'IceDragon');
            $browser_list[] = array('Iridium', 'Iridium/', '/Iridium\/.*Safari.*Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('Maxthon Nitro', 'MxNitro|mxnitro', '/(MxNitro|mxnitro)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Iron', ' Iron ', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('Iron', 'Iron/', '/Iron\/([0-9]+)/', '1', '');
            $browser_list[] = array('Hola Browser', 'Hola/', '/Hola\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Sogou Explorer', '/Chrome.*Safari.*SE\s.*MetaSr/', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('360 browser', 'QIHU 360', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('Slimjet', 'Slimjet/', '/Slimjet\/([0-9]+)/', '1', '');
            $browser_list[] = array('FreeU', 'FreeU|Freeu', '/(FreeU|Freeu)\/([0-9]+)/', '2', '');
            $browser_list[] = array('Kinza', 'Kinza/', '/Kinza\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Station', 'Station/', '/Station\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Superbird', 'Superbird|SuperBird', '/(Superbird|SuperBird)\/([0-9]+)/', '2', '');
            $browser_list[] = array('Polypane', 'Polypane/', '/Polypane\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('OhHai Browser', 'OhHaiBrowser/', '/OhHaiBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Sizzy', 'Sizzy', '/Sizzy\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('AOL Desktop', 'ADG/', '/ADG\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Elements Browser', 'Elements Browser', '/Elements\sBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('115 Browser', '115Browser', '/115Browser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Falkon', 'Falkon/', '/Falkon\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('AOL Shield Pro', 'AOLShield', '/AOLShield\/([0-9]+)/', '1', '');
            $browser_list[] = array('Google Earth', 'Google Earth', '/Google Earth(?:|\sPro)\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Chedot', 'Chedot/', '/Chedot\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('SlimBoat', 'SlimBoat/', '/SlimBoat\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Rambler Browser', 'Nichrome/', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('Beaker Browser', 'BeakerBrowser/', '/BeakerBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Amigo', 'Amigo/', '/Amigo\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Opera Neon', ' MMS/', '/\sMMS\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('SmartTV Browser', 'Thano/', '/Thano\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Biscuit', 'Biscuit/', '/Biscuit\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Chromium GOST', 'Chromium GOST', '/Chrome\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Dashob', 'Dashob/', '/Dashob\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Dezor', 'Dezor/', '/Dezor\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Duoyu Browser', 'duoyu_business/', '/duoyu_business\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('iTop Browser', ' iTop', '/Chrome\/([0-9]+\.[0-9]+)*.+\siTop$/', '1', '');
            $browser_list[] = array('Polarity', 'Polarity/', '/Polarity\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Sielo', 'Sielo/', '/Sielo\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Norton Browser', 'Norton/', '/Norton\/([0-9]+)/', '1', '');
            $browser_list[] = array('Avira Browser', 'Avira/', '/Avira\/([0-9]+)/', '1', '');

            // Webkit, Gecko and other engine based browsers

            $browser_list[] = array('Cyberfox', 'Cyberfox/', '/Cyberfox\/([0-9]+)/', '1', '');
            $browser_list[] = array('SeaMonkey', 'SeaMonkey/', '/SeaMonkey\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('K-Meleon', 'K-Meleon', '/K\-Meleon\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Iceweasel', '/[iI]ce[wW]easel/', '/[iI]ce[wW]easel/', '1', '');
            $browser_list[] = array('IceApe', 'Iceape/', '/Iceape\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Comodo Ice Dragon', 'IceDragon/', '/IceDragon\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('QtWeb', 'QtWeb Internet Browser/', '/QtWeb\sInternet\sBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('QtWebEngine', 'QtWebEngine', '/QtWebEngine\/([0-9]+\.[0-9]+)/', '1', 'Konqueror');
            $browser_list[] = array('Qt', 'Qt/', '/Qt\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('WebKitGTK', 'WebKitGTK+/', '/WebKitGTK\+\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Konqueror', 'Konqueror', '/Konqueror\/([0-9]+\.[0-9]+)/', '1', 'QtWebEngine');
            $browser_list[] = array('Konqueror', 'konqueror', '/konqueror\/([0-9]+\.[0-9]+)/', '1', 'QtWebEngine');
            $browser_list[] = array('Konqueror', 'Konqueror', '/\sQtWebEngine\/([0-9]+\.[0-9]+)(|[.0-9]+)\sChrome\/[.0-9]+.*\sSafari\/[.0-9]+.*Konqueror\s/', '1', '');
            $browser_list[] = array('Epiphany', 'Epiphany', '/Epiphany\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('OmniWeb', 'OmniWeb/', '/OmniWeb\//', '1', '');
            $browser_list[] = array('iCab', 'iCab', '/iCab(\s|\/)([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Camino', 'Camino/', '/Camino\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Midori', 'Midori|midori', '/Midori\/([.0-9]+)/', '1', '');
            $browser_list[] = array('Otter', 'Otter/', '/Otter\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Kazehakase', 'Kazehakase/', '/Kazehakase\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('QupZilla', 'QupZilla/', '/QupZilla\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Waterfox', 'Waterfox/', '/Waterfox\/([0-9]+)/', '1', '');
            $browser_list[] = array('Waterfox', 'Waterfox', '/Firefox\/([0-9]+)/', '1', '');
            $browser_list[] = array('Basilisk', 'Basilisk/', '/\srv:([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Dooble', 'Dooble/', '/Dooble\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Fluid', 'Fluid/', '/Fluid\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Arora', 'Arora/', '/Arora\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Artis Browser', 'ArtisBrowser/', '/ArtisBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Steam Client', ' Valve ', '/Valve\s(|Steam\s)Client/', '1', 'Tenfoot');
            $browser_list[] = array('Steam Overlay', ' Valve ', '/Valve\sSteam\sGameOverlay/', '1', 'Tenfoot|Client/');
            $browser_list[] = array('Rekonq', ' rekonq', '/rekonq\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Odyssey Web Browser', 'Odyssey Web Browser', '/OWB\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Safari SDK', '/^Safari\/[.0-9]+\sCFNetwork\/[.0-9]+\sDarwin\/[.0-9]+/', '/Safari\//', '1', '');
            $browser_list[] = array('Internet TV Browser', 'InettvBrowser/', '/InettvBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Firefox', 'BonEcho/', '/BonEcho\/([0-9]+)/', '1', '');
            $browser_list[] = array('Firefox', 'GranParadiso/', '/GranParadiso\/([0-9]+)/', '1', '');
            $browser_list[] = array('Firefox', 'Shiretoko/', '/Shiretoko\/([0-9]+)/', '1', '');
            $browser_list[] = array('Firefox', 'Namoroka/', '/Namoroka\/([0-9]+)/', '1', '');
            $browser_list[] = array('iTunes App', 'iTunes/', '/iTunes\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Mypal Browser', 'Mypal/', '/Mypal\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Mercury', 'Mercury/', '/Firefox\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Arctic Fox', 'ArcticFox/', '/ArcticFox\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Roccat', ' Roccat/', '/Roccat\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Lunascape', ' lswebkit ', ' lswebkit ', '1', '');

            foreach ($browser_list as $browser_list_va) {
                $match = $browser_list_va[1];
                $match_exclude = $browser_list_va[4];

                if ($this->match_ua($match) && !$this->match_ua($match_exclude)) {
                    $this->result_browser_version = 0;
                    $this->result_browser_name = $browser_list_va[0];
                    $matches = $this->match_ua($browser_list_va[2]);
                    $matches_n = intval($browser_list_va[3]);
                    $this->result_browser_version = is_array($matches) ? (float)$matches[$matches_n] : 0;

                    // Gnome Web browser detection on Linux/Unix OS

                    if ($this->result_browser_name === 'Safari' && ($this->result_os_family === 'linux' || $this->result_os_family === 'unix')) {
                        $this->result_browser_name = 'Gnome Web';
                    }

                    // Safari old version conversion

                    if ($match === '/AppleWebKit\/[.0-9]+.*Gecko\)\sSafari\/[.0-9A-Za-z]+$/') {
                        $ev = intval($this->result_browser_version);
                        if (!empty($ev)) {
                            $this->result_browser_version = 1;
                        }
                        if ($ev > 400) {
                            $this->result_browser_version = 2;
                        }
                        if ($ev > 500) {
                            $this->result_browser_version = 3;
                        }
                        if ($ev > 527) {
                            $this->result_browser_version = 4;
                        }
                        if ($ev > 532) {
                            $this->result_browser_version = 5;
                        }
                        if ($ev > 535) {
                            $this->result_browser_version = 6;
                        }
                        $this->result_browser_safari_original = 1;
                    }

                    // IE Trident engine version conversion

                    if ($match === 'Trident/') {
                        if ($this->result_browser_version == 4) {
                            $this->result_browser_version = 8;
                        }
                        if ($this->result_browser_version == 5) {
                            $this->result_browser_version = 9;
                        }
                        if ($this->result_browser_version == 6) {
                            $this->result_browser_version = 10;
                        }
                        if ($this->result_browser_version == 7) {
                            $this->result_browser_version = 11;
                        }
                    }

                    if (!empty($this->result_browser_version)) {
                        $browser_need_continue = false;
                        break;
                    }
                }
            }

            $browser_list = null;

            // End of desktop browsers detection
        }

        /*
        -----------------
         Mobile Browsers
        -----------------
        */

        if ($browser_need_continue && $this->result_browser_chrome_original == 0 && $this->result_browser_firefox_original == 0 && $this->result_mobile == 1) {
            // Mobile browsers with detectable versions

            $browser_list[] = array('Safari Mobile', '/(iPhone|iphone|iPad;|iPod).*AppleWebKit\/[.0-9]+\s\(KHTML,\slike\sGecko\)\s.*Version\/[.,0-9]+\sMobile\//', '/Version\/([0-9]+\.[0-9]+)(|\.[0-9]+|\,[0-9]+)\sMobile\//', '1', 'RDDocuments|AlohaBrowser|DuckDuckGo|MiuiBrowser|Snapchat|NAVER(inapp;|1Password|OPT/|Ddg/');
            $browser_list[] = array('Safari Mobile', '/(Intel\sMac\sOS\sX).*AppleWebKit\/.*Version\/[.,0-9]+\s(?:|Mobile\/\w+\s)Safari\/[.0-9A-Za-z]+(|\/[0-9]+|\s\(.*\))+$/', '/Version\/([0-9]+\.[0-9]+)(|\.[0-9]+|\,[0-9]+)/', '1', 'CriOS|RDDocuments|AlohaBrowser|DuckDuckGo|MiuiBrowser|Snapchat|NAVER(inapp;|1Password|OPT/|Ddg/');
            $browser_list[] = array('Android Browser', '/Android.*Version\/[.0-9]+\s(?:Mobile\s)?Safari\/[.0-9]+(|\-[0-9]+)$/', '/Android.*Version\/([0-9]+\.[0-9]+)/', '1', 'Chrome/');
            $browser_list[] = array('Android Browser', 'Dalvik/', '/Dalvik\/([.0-9]+)\s\(Linux;\sU;\sAndroid\s/', '2', 'Chrome/');
            $browser_list[] = array('Samsung Browser', 'SamsungBrowser', '/SamsungBrowser\/([0-9]+\.[0-9]+)/', '1', 'CrossApp');
            $browser_list[] = array('Firefox Focus', 'Focus/', '/Focus\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Firefox iOS', 'FxiOS', '/FxiOS\/([0-9]*[.]?[0-9]+)/', '1', '');
            $browser_list[] = array('Opera Mini', 'Opera Mini|OPiOS', '/(Opera Mini|OPiOS)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Opera Touch', 'OPT/', '/OPT\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Huawei Browser', 'HuaweiBrowser/', '/HuaweiBrowser\/([0-9]+)/', '1', '');
            $browser_list[] = array('DuckDuckGo', 'DuckDuckGo/', '/DuckDuckGo\/([0-9]+)/', '1', '');
            $browser_list[] = array('DuckDuckGo', 'Ddg/', '/Ddg\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Mi Browser', 'MiuiBrowser/', '/MiuiBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Mint Browser', 'Mint Browser/', '/Mint\sBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Avast Browser', 'AvastSecureBrowser/', '/AvastSecureBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Google App', '/(iPhone|iphone|iPad;|iPod).*\)\sGSA\/([0-9]+)/', '/(iPhone|iphone|iPad;|iPod).*\)\sGSA\/([0-9]+)/', '2', '');
            $browser_list[] = array('Google App', '/\sGSA\//', '/\sGSA\/([0-9]+)/', '1', '');
            $browser_list[] = array('Facebook App', 'FBAV/|FBSV/', '/(FBAV|FBSV)\/([0-9]+)\./', '2', 'FBAN/Messenger|FB_IAB/MESSENGER');
            $browser_list[] = array('Instagram App', 'Instagram', '/Instagram\s([0-9]+)\./', '1', '');
            $browser_list[] = array('Facebook Messenger', 'FBAN/Messenger|FB_IAB/MESSENGER', '/(FBAV|FBSV)\/([0-9]+)\./', '2', '');
            $browser_list[] = array('Snapchat', 'Snapchat', '/\sSnapchat\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('WhatsApp', 'WhatsApp', '/WhatsApp\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Viber', 'Viber/', '/Viber\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Yandex App', 'YaApp_', '/YaApp_(Android|iOS)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Yandex App', 'YandexSearch/', '/YandexSearch\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Yandex App', 'YaSearchBrowser/', '/YaSearchBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Dolfin', 'Dolfin/', '/Dolfin\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Flipboard App', 'Flipboard/', '/Flipboard\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('FreeU', 'FreeU|Freeu', '/(FreeU|Freeu)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Iron', 'Iron Safari|MobileIron', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('Sogou Mobile Browser', 'SogouMobileBrowser/', '/SogouMobileBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Meizu Browser', 'MZBrowser/', '/MZBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('360 Mobile Browser', '360 Aphone', '/360\sAphone\sBrowser\s\(([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Adblock Browser', 'ABB/', '/ABB\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Ecosia Browser', 'Ecosia', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('HeyTap Browser', 'HeyTapBrowser/', '/HeyTapBrowser\/([0-9]+)/', '1', '');
            $browser_list[] = array('Oppo Browser', 'OppoBrowser/', '/OppoBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Silk', 'Silk/', '/Silk\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Surf Browser', 'SurfBrowser/', '/SurfBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Phoenix Browser', 'PHX/', '/PHX\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('CM Mobile', 'ACHEETAHI', '/Chrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('Bing App', ' BingWeb', '/BingWeb\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Firefox Klar', 'Klar/', '/Klar\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Super Fast Browser', 'SFBrowser|tssomas', '/(SFBrowser|tssomas)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Tenta Browser', 'Tenta/', '/Tenta\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Kiwi Browser', 'Kiwi Chrome/', '/Kiwi\sChrome\/([0-9]+)/', '1', '');
            $browser_list[] = array('JioPages', 'JioBrowser/', '/JioBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('JioPages', 'JioPages/', '/JioPages\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Qwant Mobile', 'QwantBrowser', '/QwantBrowser\/([0-9]+)/', '1', '');
            $browser_list[] = array('Qwant Mobile', 'QwantMobile', '/QwantMobile\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Cake Browser', '; Cake) ', '/\;\sCake\).*AppleWebKit.*Version\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('DU Browser', 'bdbrowser', '/bdbrowser(|_i18n)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('DU Browser', 'baidubrowser', '/baidubrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Baidu Search App', 'baiduboxapp/', '/baiduboxapp\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('DU Browser HD', 'BaiduHD/', '/BaiduHD\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Hi Browser', 'HiBrowser/', '/HiBrowser\/?(?:v)?([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('VmWare Browser', 'AirWatch Browser', '/AirWatch\sBrowser\sv([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Iron Mobile', 'MobileIron/', '/MobileIron\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('NineSky', 'Ninesky-android-mobile/', '/Ninesky\-android\-mobile\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Realme Browser', 'RealmeBrowser/', '/RealmeBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Vivo Browser', 'VivoBrowser/', '/VivoBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Web Explorer', 'Web Explorer/', '/Web\sExplorer\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('NTENT Browser', 'NTENTBrowser/', '/NTENTBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Nox Browser', 'NoxBrowser/', '/NoxBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Quark Browser', 'Quark/', '/Quark\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Yaani Browser', 'YaaniBrowser', '/YaaniBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Tizen Browser', '/Tizen(?: )?(?:\/)?[.0-9]+.*AppleWebKit\/.*(Version|SLP Browser|Tizen Browser|TizenBrowser)\/([0-9]+\.[0-9]+)/', '/Tizen(?: )?(?:\/)?[.0-9]+.*AppleWebKit\/.*(Version|SLP Browser|Tizen Browser|TizenBrowser)\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Internet Explorer Mobile', 'IEMobile', '/IEMobile(?: )?(?:\/)?([0-9]+)/', '1', '');
            $browser_list[] = array('Internet Explorer Mobile', 'Trident/', '/Trident\/[.0-9].*Touch;*.rv\:([0-9]+)/', '1', '');
            $browser_list[] = array('ZetaKey', 'Zetakey/', '/Zetakey\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Nokia Browser', 'NokiaBrowser/', '/NokiaBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('NetFront', 'NetFront/', '/NetFront\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('BlackBerry Browser', '/(BB10;|BlackBerry|PlayBook).*AppleWebKit.*Version\/([0-9]+\.[0-9]+)/', '/(BB10;|BlackBerry|PlayBook).*AppleWebKit.*Version\/([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('ONE Browser', 'OneBrowser/', '/OneBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Web Explorer', 'webexplorer/', '/webexplorer\/([0-9]+)/', '1', '');
            $browser_list[] = array('Start Internet Browser', 'Start/', '/Start\/([0-9]+)/', '1', '');
            $browser_list[] = array('EUI Browser', 'EUI Browser', '/EUI\sBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('iCab Mobile', 'iCabMobile', '/iCabMobile(\s|\/)([0-9]+\.[0-9]+)/', '2', '');
            $browser_list[] = array('Mercury', 'Mercury/', '/Mercury\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Samsung Browser', '; SAMSUNG S|; SAMSUNG G', '/\;\sSAMSUNG\s(S|G)+.*\sBuild\/.*\)\sAppleWebKit\/.*Version\/([0-9]+\.[0-9]+)\sChrome\/[.0-9]+.*\sSafari\/[.0-9]+$/', '2', 'SamsungBrowser');
            $browser_list[] = array('Documents App', 'RDDocuments/', '/RDDocuments\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Opera Coast', 'Coast/', '/Coast\/([0-9]+)/', '1', '');
            $browser_list[] = array('Android Browser', '/Android.*Version\/[.0-9]+\s(?:Mobile\s)?Safari(|\/[.0-9]+\sCyanogenMod.*)+$/', '/Android.*Version\/([0-9]+\.[0-9]+)/', '1', 'Chrome/');
            $browser_list[] = array('Playstation Browser', '/(PlayStation\s|PLAYSTATION\s)/', '/(PlayStation\s|PLAYSTATION\s)/', '1', '');
            $browser_list[] = array('Naver App', 'NAVER(inapp;', '/NAVER\(inapp;\ssearch;\s\d+;\s([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Line App', ' Line/', '/\sLine\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('QQ App', ' QQ/', '/\sQQ\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('GNews App', ' GNews ', '/\sGNews\s.*\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('1Password App', '1Password', '/\s1Password\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Pinterest App', 'Pinterest', '/Pinterest/', '1', '');
            $browser_list[] = array('Twitter App', ' Twitter', '/Twitter/', '1', '');
            $browser_list[] = array('Ali App', ' AliApp', '/\sAliApp/', '1', '');
            $browser_list[] = array('Alipay', 'AlipayClient', '/AlipayClient\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Samsung CrossApp', 'CrossApp/', '/CrossApp\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Diigo', 'DiigoBrowser', '/DiigoBrowser/', '1', '');
            $browser_list[] = array('Stargon', 'Stargon/', '/Stargon\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('You Browser', 'YouBrowser/', '/YouBrowser\/([0-9]+\.[0-9]+)/', '1', '');
            $browser_list[] = array('Max Browser', 'MaxBrowser/', '/MaxBrowser\/([0-9]+)/', '1', '');
            $browser_list[] = array('Soul Browser', 'Soul/', '/Soul\/([0-9]+)/', '1', '');

            foreach ($browser_list as $browser_list_va) {
                $match = $browser_list_va[1];
                $match_exclude = $browser_list_va[4];

                // Some OS specified browsers matching optimization

                $current_browser_continue = true;
                if ($this->get_mode !== 'browser') {
                    $current_browser = $browser_list_va[0];

                    // Some iOS specified browsers matching optimization

                    if ($current_browser === 'Safari Mobile' || $current_browser === 'Firefox iOS') {
                        if ($this->result_os_name !== 'iOS') {
                            $current_browser_continue = false;
                        }
                    }

                    // Some Android specified browsers matching optimization

                    if ($current_browser === 'Android Browser' || $current_browser === 'Samsung Browser' || $current_browser === 'Huawei Browser') {
                        if ($this->result_os_name !== 'Android') {
                            $current_browser_continue = false;
                        }
                    }

                    // WP specified browser matching optimization

                    if ($current_browser === 'Internet Explorer Mobile' && $this->result_os_name !== 'Windows Phone' && $this->result_os_name !== 'Windows Mobile') {
                        $current_browser_continue = false;
                    }

                    // Tizen specified browser matching optimization

                    if ($current_browser === 'Tizen Browser' && $this->result_os_name !== 'Tizen') {
                        $current_browser_continue = false;
                    }

                    // BlackBerry specified browser matching optimization

                    if ($current_browser === 'BlackBerry Browser' && $this->result_os_name !== 'BlackBerry') {
                        $current_browser_continue = false;
                    }

                    // Google App matching optimization

                    if ($current_browser === 'Google App' && !$this->match_ua(' GSA/')) {
                        $current_browser_continue = false;
                    }
                }

                if ($current_browser_continue == true && $this->match_ua($match) && !$this->match_ua($match_exclude)) {
                    $this->result_browser_version = 0;
                    $this->result_browser_name = $browser_list_va[0];
                    $matches = $this->match_ua($browser_list_va[2]);
                    $matches_n = intval($browser_list_va[3]);

                    if (is_array($matches)) {
                        if ($this->result_browser_name === 'Safari Mobile' && strpos($matches[$matches_n], '_') !== false) {
                            $matches[$matches_n] = str_replace('_', '.', $matches[$matches_n]);
                        }
                        $this->result_browser_version = (float)$matches[$matches_n];
                    } else {
                        $this->result_browser_version = 0;
                    }

                    if (!empty($this->result_browser_version)) {
                        $browser_need_continue = false;
                        break;
                    }
                }
            }

            $browser_list = null;

            // End of mobile browsers detection
        }

        // Other browsers without version specified

        if ($browser_need_continue) {
            $other_bsr = array();

            $other_bsr[] = array('Maple' => 'Maple');
            $other_bsr[] = array('Espial' => 'Espial');

            foreach ($other_bsr as $other_bsr_va) {
                $k = key($other_bsr_va);
                $v = $other_bsr_va[$k];

                if ($this->match_ua($v)) {
                    $this->result_browser_version = 0;
                    $this->result_browser_name = $k;
                    $this->result_browser_title = $k;
                    break;
                }
            }
            $other_bsr = null;
        }

        // MacOS and iOS Apps

        if ($this->result_browser_name === 'unknown' && $this->match_ua('CFNetwork/') && $this->match_ua('Darwin/') && !$this->match_ua('Safari/')) {
            $matches = $this->match_ua('/^(.*)(\/[.0-9]+|\s\(unknown\sversion\))(.*)\sCFNetwork\/[.0-9]+\sDarwin\/[.0-9]+/');
            if (!empty($matches[1])) {
                $matches[1] = str_replace('%20', ' ', $matches[1]);
                $matches[1] = str_replace('com.apple.Safari.', '', $matches[1]);
                $matches[1] = str_replace('com.apple.WebKit.', '', $matches[1]);
                $matches[1] = str_replace('com.apple.Notes.', '', $matches[1]);
                $matches[1] = str_replace('com.apple.mobilenotes.', '', $matches[1]);
                $this->result_browser_name = $matches[1] . ' App';
                if ($this->result_browser_name === 'Browser App') {
                    $this->result_browser_name = 'Darwin Browser';
                }
                $darwin_app = true;
            }
        }

        /*
        -------------------
         WebView detection
        -------------------
        */

        if ($this->result_mobile == 1) {
            // Android WebView

            if ($this->result_os_name === 'Android') {
                if ($this->match_ua('; wv|;FB|FB_IAB|OKApp|DuckDuckGo')) {
                    $this->result_browser_android_webview = 1;
                }
                if ($this->result_browser_chrome_original == 0 && $this->result_browser_chromium_version != 0 && $this->match_ua('/like\sGecko\)\sVersion\/[.0-9]+\sChrome\/[.0-9]+\s/')) {
                    $this->result_browser_android_webview = 1;
                }
            }

            // WKWebView

            if ($this->result_ios) {
                $webkit_webview = false;

                if ($this->match_ua('/\s\((iPhone|iphone|iPad|iPod);.*\)\sAppleWebKit\/[.0-9]+\s\(KHTML\,\slike Gecko\)\s(?!CriOS|FxiOS|OPiOS|EdgiOS)(?!Version).*Mobile\/([0-9A-Z]+)/')) {
                    $webkit_webview = true;
                }
                if ($this->result_browser_name === 'unknown' && $this->match_ua('MobileSafari/') && $this->match_ua('CFNetwork/')) {
                    $webkit_webview = true;
                }

                if ($webkit_webview) {
                    $this->result_browser_ios_webview = 1;

                    if ($this->result_browser_name === 'unknown') {
                        $this->result_browser_name = 'WKWebView';
                        $this->result_browser_version = 0;
                    }
                }
            }
        }

        /*
        -------------------------
         Detect Safari original
        -------------------------
        */

        if ($this->result_browser_name === 'Safari' || $this->result_browser_name === 'Safari Mobile') {
            if ($this->match_ua('/AppleWebKit\/[.0-9]+.*Gecko\)\sVersion\/[.0-9].*Safari\/[.0-9A-Za-z]+$/')) {
                $this->result_browser_safari_original = 1;
            }
        }

        // Check and correct browser version anomaly

        if (intval($this->result_browser_version) > 200 && !$this->match_ua('FBAV/|FBSV/|GSA/|Instagram')) {
            $this->result_browser_version = 0;
        }

        // Set Browser title

        if (!empty($this->result_browser_version)) {
            $this->result_browser_title = $this->result_browser_name . ' ' . $this->result_browser_version;
        } else {
            $this->result_browser_title = $this->result_browser_name . ' (unknown version)';

            $browsers_without_versions = array();
            $browsers_without_versions[] = 'Android Browser';
            $browsers_without_versions[] = 'WKWebView';
            $browsers_without_versions[] = 'Safari SDK';
            $browsers_without_versions[] = 'Playstation Browser';
            $browsers_without_versions[] = 'OmniWeb';
            $browsers_without_versions[] = 'Steam Client';
            $browsers_without_versions[] = 'Steam Overlay';
            $browsers_without_versions[] = 'Maple';
            $browsers_without_versions[] = 'Espial';
            $browsers_without_versions[] = 'Diigo Browser';
            $browsers_without_versions[] = 'IceWeasel';
            $browsers_without_versions[] = 'Iceweasel';
            $browsers_without_versions[] = 'Facebook App';
            $browsers_without_versions[] = 'Twitter App';
            $browsers_without_versions[] = 'Bing App';
            $browsers_without_versions[] = 'Pinterest App';
            $browsers_without_versions[] = 'Ali App';
            $browsers_without_versions[] = 'Alipay App';
            $browsers_without_versions[] = 'Lunascape';

            if (in_array($this->result_browser_name, $browsers_without_versions) || isset($darwin_app)) {
                $this->result_browser_title = $this->result_browser_name;
            }

            $browsers_without_versions = null;
        }

        if (strpos($this->result_browser_name, 'unknown') !== false) {
            $this->result_browser_title = 'unknown';
        }
        if ($this->result_browser_version == null) {
            $this->result_browser_version = 0;
        }

        // EdgeHTML browser should not be detected as a Chromium engine
        if ($this->result_browser_name === 'Edge' && $this->result_browser_version >= 12 && $this->result_browser_version <= 18) {
            $this->result_browser_chromium_version = 0;
        }

        if ($this->get_mode === 'browser') {
            return null;
        }

        /*
        ---------------------
         Detect device types
        ---------------------
        */

        // Desktop

        if ($this->result_os_family !== 'unknown' && $this->result_os_type !== 'mixed') {
            $this->result_device_type = 'desktop';
        }

        // Mobile

        if ($this->result_mobile == 1) {
            $this->result_device_type = 'mobile';
        }

        // TV

        if ($this->match_ua('TV|HDMI|CrKey| Escape |Kylo/|SmartLabs|SC/IHD|Viera|BRAVIA|NetCast|Roku/DVP| Roku |Maple|DuneHD|CE-HTML|EIS iPanel|Sunniwell; Resolution|Freebox|Netbox|Netgem|AFTT|AFTM|AFTB|DLNADOC| iconBIT |olleh tv|stbapp |; MIBOX|ABOX-I|; H96 |; X96|HX S905|; M8S |MINIM8S|MXIII-|; NEO-X|; NEO-U| Nexus Player |TPM171E|; V88 |MXQPRO|NEXBOX-|; Leelbox|ZIDOO|; A95X| Beelink |; T95Z|; TX3 ') && !$this->match_ua('BTV-')) {
            $this->result_device_type = 'tv';
        }

        // Console

        if ($this->matchi_ua('playstation') || $this->match_ua('Xbox|GAMEPAD|Nintendo|OUYA|; SHIELD Build')) {
            $this->result_device_type = 'console';
        }

        // MediaPlayer

        if ($this->match_ua('iPod|AlexaMediaPlayer|AppleCoreMedia')) {
            $this->result_device_type = 'mediaplayer';
        }

        // Car

        if ($this->match_ua('CarBrowser|Tesla/')) {
            $this->result_device_type = 'car';
        }

        // Watch

        if ($this->matchi_ua('watch') && !$this->match_ua('AirWatch')) {
            $this->result_device_type = 'watch';
        }

        if ($this->get_mode === 'device') {
            return null;
        }

        /*
        ---------------------------------------------
         Detect 64bits mode (browser + OS specified)
        ---------------------------------------------
        */

        if ($this->result_os_name === 'MacOS' && $this->macos_version_minor >= 6) {
            if ($this->result_browser_name === 'Safari' || $this->result_browser_name === 'Safari SDK') {
                $this->result_64bits_mode = 1;
            }
        }

        /*
        ---------------------------------------------------------
         MacOS Big Sur + Firefox version > 86 detection feature
        ---------------------------------------------------------
        */

        if ($this->result_os_name === 'MacOS' && $this->macos_version_minor == 15 && $this->result_browser_name === 'Firefox' && $this->result_browser_version > 86) {
            $this->result_os_version = 'Big Sur';
            $this->result_os_title = 'MacOS ' . $this->result_os_version;
        }

        return null;
    }

    /**
     * The method returns all possible environment data from User-Agent
     *
     * @param string $ua The User-Agent string
     * @param string $result_format If specified, method returns result as JSON
     * @return mixed Returns User-Agent parsing result as array or JSON string (optional)
     */

    public function getAll($ua, $result_format = '')
    {
        $this->useragent = trim($ua);
        $this->get_mode = 'all';
        $this->resetProperties();
        $this->getResult();
        $result = array(
            'os_type' => $this->result_os_type,
            'os_family' => $this->result_os_family,
            'os_name' => $this->result_os_name,
            'os_version' => $this->result_os_version,
            'os_title' => $this->result_os_title,
            'device_type' => $this->result_device_type,
            'browser_name' => $this->result_browser_name,
            'browser_version' => $this->result_browser_version,
            'browser_title' => $this->result_browser_title,
            'browser_chrome_original' => $this->result_browser_chrome_original,
            'browser_firefox_original' => $this->result_browser_firefox_original,
            'browser_safari_original' => $this->result_browser_safari_original,
            'browser_chromium_version' => $this->result_browser_chromium_version,
            'browser_gecko_version' => $this->result_browser_gecko_version,
            'browser_webkit_version' => $this->result_browser_webkit_version,
            'browser_android_webview' => $this->result_browser_android_webview,
            'browser_ios_webview' => $this->result_browser_ios_webview,
            'browser_desktop_mode' => $this->result_browser_desktop_mode,
            '64bits_mode' => $this->result_64bits_mode
        );

        if (strtolower($result_format) === 'json') {
            $result = json_encode($result);
        }
        $this->touch_support_mode = false;
        return $result;
    }

    /**
     * The method returns OS data from User-Agent
     *
     * @param string $ua The User-Agent string
     * @param string $result_format If specified, method returns result as JSON
     * @return mixed Returns User-Agent parsing result as array or JSON string (optional)
     */

    public function getOS($ua, $result_format = '')
    {
        $this->useragent = trim($ua);
        $this->get_mode = 'os';
        $this->resetProperties();
        $this->getResult();
        $result = array(
            'os_type' => $this->result_os_type,
            'os_family' => $this->result_os_family,
            'os_name' => $this->result_os_name,
            'os_version' => $this->result_os_version,
            'os_title' => $this->result_os_title,
            '64bits_mode' => $this->result_64bits_mode
        );

        if (strtolower($result_format) === 'json') {
            $result = json_encode($result);
        }
        $this->touch_support_mode = false;
        return $result;
    }

    /**
     * The method returns Browser data from User-Agent
     *
     * @param string $ua The User-Agent string
     * @param string $result_format If specified, method returns result as JSON
     * @return mixed Returns User-Agent parsing result as array or JSON string (optional)
     */

    public function getBrowser($ua, $result_format = '')
    {
        $this->useragent = trim($ua);
        $this->get_mode = 'browser';
        $this->resetProperties();
        $this->getResult();
        $result = array(
            'browser_name' => $this->result_browser_name,
            'browser_version' => $this->result_browser_version,
            'browser_title' => $this->result_browser_title,
            'browser_chrome_original' => $this->result_browser_chrome_original,
            'browser_firefox_original' => $this->result_browser_firefox_original,
            'browser_safari_original' => $this->result_browser_safari_original,
            'browser_chromium_version' => $this->result_browser_chromium_version,
            'browser_gecko_version' => $this->result_browser_gecko_version,
            'browser_webkit_version' => $this->result_browser_webkit_version,
            'browser_android_webview' => $this->result_browser_android_webview,
            'browser_ios_webview' => $this->result_browser_ios_webview,
            'browser_desktop_mode' => $this->result_browser_desktop_mode
        );

        if (strtolower($result_format) === 'json') {
            $result = json_encode($result);
        }
        $this->touch_support_mode = false;
        return $result;
    }

    /**
     * The method returns Device Type data from User-Agent
     *
     * @param string $ua The User-Agent string
     * @param string $result_format If specified, method returns result as JSON
     * @return mixed Returns User-Agent parsing result as array or JSON string (optional)
     */

    public function getDevice($ua, $result_format = '')
    {
        $this->useragent = trim($ua);
        $this->get_mode = 'device';
        $this->resetProperties();
        $this->getResult();
        $result = array('device_type' => $this->result_device_type);

        if (strtolower($result_format) === 'json') {
            $result = json_encode($result);
        }
        $this->touch_support_mode = false;
        return $result;
    }

    /**
     * This method is needed to detect mobile browsers in Desktop Mode (Android and iOS)
     * The method should call if browser supports Touch events (client-side JS detection is necessary)
     *
     * @return null
     */

    public function setTouchSupport()
    {
        $this->touch_support_mode = true;
        return null;
    }
}
