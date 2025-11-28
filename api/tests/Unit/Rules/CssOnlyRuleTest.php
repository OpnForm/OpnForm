<?php

namespace Tests\Unit\Rules;

use App\Rules\CssOnlyRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CssOnlyRuleTest extends TestCase
{
    /** @test */
    public function it_accepts_valid_css(): void
    {
        $data = ['css' => 'body { color: red; } .card > .title { font-weight: 700; }'];
        $v = Validator::make($data, ['css' => [new CssOnlyRule()]]);
        $this->assertTrue($v->passes());
    }

    /** @test */
    public function it_rejects_html_like_content(): void
    {
        $data = ['css' => '<div>bad</div>'];
        $v = Validator::make($data, ['css' => [new CssOnlyRule()]]);
        $this->assertFalse($v->passes());
    }

    /** @test */
    public function it_rejects_expression_function(): void
    {
        $data = ['css' => 'div { width: expression(alert(1)); }'];
        $v = Validator::make($data, ['css' => [new CssOnlyRule()]]);
        $this->assertFalse($v->passes());
    }

    /** @test */
    public function it_rejects_ie_behavior_property(): void
    {
        $data = ['css' => 'div { behavior: url(#default#VML); }'];
        $v = Validator::make($data, ['css' => [new CssOnlyRule()]]);
        $this->assertFalse($v->passes());
    }

    /** @test */
    public function it_accepts_http_https_urls_and_safe_data_images(): void
    {
        $ok = [
            'div { background-image: url(https://example.com/a.png); }',
            'div { background-image: url(data:image/png;base64,iVBORw0KGgo=); }',
        ];
        foreach ($ok as $css) {
            $v = Validator::make(['css' => $css], ['css' => [new CssOnlyRule()]]);
            $this->assertTrue($v->passes());
        }
    }

    /** @test */
    public function it_rejects_dangerous_urls(): void
    {
        $bad = [
            'div { background: url(javascript:alert(1)); }',
            'div { background: url(vbscript:alert(1)); }',
            'div { background: url(data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==); }',
            'div { background: url(data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+); }',
        ];
        foreach ($bad as $css) {
            $v = Validator::make(['css' => $css], ['css' => [new CssOnlyRule()]]);
            $this->assertFalse($v->passes());
        }
    }

    /** @test */
    public function it_validates_import_urls(): void
    {
        $ok = ['@import url("https://example.com/styles.css"); body{color:#000}'];
        foreach ($ok as $css) {
            $v = Validator::make(['css' => $css], ['css' => [new CssOnlyRule()]]);
            $this->assertTrue($v->passes());
        }

        $bad = ['@import url("javascript:alert(1)");'];
        foreach ($bad as $css) {
            $v = Validator::make(['css' => $css], ['css' => [new CssOnlyRule()]]);
            $this->assertFalse($v->passes());
        }
    }

    /** @test */
    public function it_allows_empty_string(): void
    {
        $v = Validator::make(['css' => ''], ['css' => [new CssOnlyRule()]]);
        $this->assertTrue($v->passes());
    }
}
