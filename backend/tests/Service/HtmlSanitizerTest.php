<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testStripsScriptTagButKeepsContent(): void
    {
        $result = $this->sanitizer->sanitize('<script>alert(1)</script><p>Safe</p>');

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringContainsString('alert(1)', $result);
        $this->assertStringContainsString('<p>Safe</p>', $result);
    }

    public function testRemovesEventHandlerAttributes(): void
    {
        $result = $this->sanitizer->sanitize('<p onclick="alert(1)">text</p>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('text', $result);
    }

    public function testRemovesOnHoverEventHandler(): void
    {
        $result = $this->sanitizer->sanitize('<div onmouseover="steal()">hover</div>');

        $this->assertStringNotContainsString('onmouseover', $result);
        $this->assertStringContainsString('hover', $result);
    }

    public function testBlocksJavascriptUrl(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">link</a>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testBlocksDataUrl(): void
    {
        $result = $this->sanitizer->sanitize('<img src="data:image/png;base64,abc">');

        $this->assertStringNotContainsString('data:', $result);
    }

    public function testBlocksVbscriptUrl(): void
    {
        $result = $this->sanitizer->sanitize('<a href="vbscript:MsgBox">link</a>');

        $this->assertStringNotContainsString('vbscript:', $result);
    }

    public function testAllowSafeFormattingTags(): void
    {
        $html = '<p><strong>bold</strong> <em>italic</em> <u>underline</u> <h2>Title</h2><ul><li>one</li></ul></p>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
        $this->assertStringContainsString('<u>underline</u>', $result);
        $this->assertStringContainsString('<h2>Title</h2>', $result);
        $this->assertStringContainsString('<ul><li>one</li></ul>', $result);
    }

    public function testStripsUnknownAttributes(): void
    {
        $result = $this->sanitizer->sanitize('<p id="x" class="y" data-foo="bar">text</p>');

        $this->assertStringNotContainsString('id=', $result);
        $this->assertStringNotContainsString('class=', $result);
        $this->assertStringNotContainsString('data-foo=', $result);
    }

    public function testKeepsAllowedAnchorAttributes(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com" title="Tip">link</a>');

        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('title="Tip"', $result);
    }

    public function testKeepsSafeStyleProperty(): void
    {
        $result = $this->sanitizer->sanitize('<span style="color: red">text</span>');

        $this->assertStringContainsString('color: red', $result);
    }

    public function testFiltersDangerousCssUrlsInStyle(): void
    {
        $html = '<span style="color: red; background-image: url(http://evil.com/x)">text</span>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('color: red', $result);
        $this->assertStringNotContainsString('background-image', $result);
        $this->assertStringNotContainsString('url(', $result);
    }

    public function testFiltersExpressionCss(): void
    {
        $result = $this->sanitizer->sanitize('<span style="width: expression(alert(1))">text</span>');

        $this->assertStringNotContainsString('expression(', $result);
    }

    public function testFiltersMozBindingCss(): void
    {
        $result = $this->sanitizer->sanitize('<span style="-moz-binding: url(http://evil)">text</span>');

        $this->assertStringNotContainsString('-moz-binding', $result);
    }

    public function testRemovesStyleFromTagsThatDoNotAllowIt(): void
    {
        $result = $this->sanitizer->sanitize('<p style="color: red">text</p>');

        $this->assertStringNotContainsString('style', $result);
    }

    public function testKeepsAbsoluteHttpUrl(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com">link</a>');

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testKeepsMailtoUrl(): void
    {
        $result = $this->sanitizer->sanitize('<a href="mailto:user@example.com">email</a>');

        $this->assertStringContainsString('mailto:user@example.com', $result);
    }

    public function testRemovesRelativeUrl(): void
    {
        $result = $this->sanitizer->sanitize('<a href="/about">about</a>');

        $this->assertStringNotContainsString('/about', $result);
    }
}
