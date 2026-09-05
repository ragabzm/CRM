<?php

declare(strict_types=1);

namespace Tests\Unit\Knowledge;

use App\Modules\Knowledge\Domain\HtmlSanitiser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What survives an article body, and what does not.
 *
 * This is half the defence — the other half is escaping at render — and both
 * are required precisely because one of them will be bypassed one day. These
 * tests are the half that can be checked directly.
 *
 * The cases are named after the bypass rather than the tag, because that is
 * what somebody adding an element to the allow-list needs to think about.
 */
final class HtmlSanitiserTest extends TestCase
{
    private HtmlSanitiser $sanitiser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitiser = new HtmlSanitiser;
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function bypasses(): array
    {
        return [
            'a script block' => ['<p>Hi</p><script>alert(1)</script>', ['alert', '<script']],
            'an inline handler' => ['<p onclick="alert(1)">Hi</p>', ['onclick', 'alert']],
            'an image error handler' => ['<img src="x" onerror="alert(1)">', ['onerror', 'alert']],
            'a javascript href' => ['<a href="javascript:alert(1)">go</a>', ['javascript:']],
            'a data-url link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">go</a>', ['data:text/html']],
            'an iframe' => ['<iframe src="https://evil.test"></iframe>', ['<iframe', 'evil.test']],
            'an embedded object' => ['<object data="x.swf"></object>', ['<object']],
            'a style block' => ['<style>body{display:none}</style><p>Hi</p>', ['<style', 'display:none']],
            'an inline style' => ['<p style="position:fixed;top:0">Hi</p>', ['position:fixed']],
            'a form' => ['<form action="https://evil.test"><input name="pw"></form>', ['<form', '<input']],
            'an svg script' => ['<svg><script>alert(1)</script></svg>', ['<svg', 'alert']],
            'a meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">', ['<meta', 'evil.test']],
        ];
    }

    /**
     * @param  list<string>  $mustNotSurvive
     */
    #[DataProvider('bypasses')]
    public function test_it_removes(string $html, array $mustNotSurvive): void
    {
        $clean = $this->sanitiser->clean($html);

        foreach ($mustNotSurvive as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $clean,
                "[{$fragment}] survived sanitising. Result: {$clean}",
            );
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function keepers(): array
    {
        return [
            'a paragraph' => ['<p>An answer.</p>', 'An answer.'],
            'emphasis' => ['<p><strong>Do</strong> this</p>', '<strong>'],
            'a list' => ['<ul><li>One</li><li>Two</li></ul>', '<li>'],
            'a heading' => ['<h2>Steps</h2>', '<h2>'],
            'a code block' => ['<pre><code>php artisan migrate</code></pre>', '<code>'],
            'a quote' => ['<blockquote>They said this.</blockquote>', '<blockquote>'],
            'an https link' => ['<a href="https://example.test">docs</a>', 'https://example.test'],
            'a mailto link' => ['<a href="mailto:help@example.test">write</a>', 'mailto:'],
            'an https image' => ['<img src="https://x.test/a.png" alt="A screenshot">', 'A screenshot'],
        ];
    }

    #[DataProvider('keepers')]
    public function test_it_keeps(string $html, string $mustSurvive): void
    {
        $this->assertStringContainsString($mustSurvive, $this->sanitiser->clean($html));
    }

    public function test_arabic_and_its_direction_survive(): void
    {
        /*
         * `dir` is on the allow-list for every block element on purpose. An
         * Arabic article quoting an English error message marks the direction
         * of the quote, and stripping it renders the quote backwards — which
         * looks like a bug in the product to every Arabic reader.
         */
        $clean = $this->sanitiser->clean('<p dir="rtl">الفاتورة فيها رسم مكرر</p><blockquote dir="ltr">Error 402</blockquote>');

        $this->assertStringContainsString('الفاتورة فيها رسم مكرر', $clean);
        $this->assertStringContainsString('dir="rtl"', $clean);
        $this->assertStringContainsString('dir="ltr"', $clean);
    }

    public function test_every_link_is_forced_to_disown_its_opener(): void
    {
        /*
         * Without `noopener` a page opened from an article can reach back
         * through `window.opener` and navigate the tab it came from — which is
         * a convincing way to put a fake sign-in page in front of an agent who
         * has just clicked a link in the help they trust.
         */
        $clean = $this->sanitiser->clean('<a href="https://example.test">docs</a>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_an_author_cannot_override_the_forced_rel(): void
    {
        $clean = $this->sanitiser->clean('<a href="https://example.test" rel="opener">docs</a>');

        $this->assertStringContainsString('noopener', $clean);
        $this->assertStringNotContainsString('rel="opener"', $clean);
    }

    public function test_it_reports_a_body_that_sanitised_away_to_nothing(): void
    {
        $clean = $this->sanitiser->clean('<script>alert(1)</script><style>p{}</style>');

        // Refused rather than saved empty: somebody who pasted markup and got a
        // blank article back would have no idea their work was discarded.
        $this->assertTrue($this->sanitiser->isEmptyAfterCleaning($clean));
    }

    public function test_an_image_alone_is_not_an_empty_body(): void
    {
        $clean = $this->sanitiser->clean('<img src="https://x.test/diagram.png" alt="How it fits together">');

        // A diagram with no prose is a complete answer to some questions.
        $this->assertFalse($this->sanitiser->isEmptyAfterCleaning($clean));
    }
}
