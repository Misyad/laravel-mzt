<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_plain_text_is_unchanged(): void
    {
        $this->assertSame(
            'Ini adalah deskripsi sederhana.',
            HtmlSanitizer::sanitize('Ini adalah deskripsi sederhana.')
        );
    }

    public function test_plain_text_with_ampersand_is_unchanged(): void
    {
        $this->assertSame(
            'S & W sedang belajar',
            HtmlSanitizer::sanitize('S & W sedang belajar')
        );
    }

    public function test_allowed_tags_are_kept(): void
    {
        $input = '<p>Halo <strong>dunia</strong> <em>!?</em></p>';
        $this->assertSame($input, HtmlSanitizer::sanitize($input));
    }

    public function test_allowed_block_list_kept(): void
    {
        $input = '<ul><li>satu</li><li>dua</li></ul><ol><li>a</li></ol><blockquote>kutipan</blockquote><h1>judul</h1><h2>sub</h2><h3>sub3</h3><h4>sub4</h4><br>';
        $this->assertSame($input, HtmlSanitizer::sanitize($input));
    }

    public function test_script_is_stripped(): void
    {
        $this->assertSame(
            '<p>aman</p>',
            HtmlSanitizer::sanitize('<p>aman</p><script>alert(1)</script>')
        );
    }

    public function test_inline_event_handlers_are_stripped(): void
    {
        $this->assertSame(
            '<p>teks</p>',
            HtmlSanitizer::sanitize('<p onclick="evil()" onerror="x()" onload="y()">teks</p>')
        );
    }

    public function test_javascript_url_is_stripped_from_href(): void
    {
        $this->assertSame(
            '<a>klik</a>',
            HtmlSanitizer::sanitize('<a href="javascript:alert(1)">klik</a>')
        );
    }

    public function test_obfuscated_javascript_url_is_stripped(): void
    {
        $this->assertSame(
            '<a>klik</a>',
            HtmlSanitizer::sanitize('<a href="java&#x09;script:alert(1)">klik</a>')
        );
    }

    public function test_unsafe_img_src_is_stripped(): void
    {
        $this->assertSame(
            '<img alt="gambar">',
            HtmlSanitizer::sanitize('<img src="javascript:alert(1)" alt="gambar">')
        );
    }

    public function test_unsafe_img_data_src_is_stripped(): void
    {
        $this->assertSame(
            '<img alt="gambar">',
            HtmlSanitizer::sanitize('<img src="data:text/html;base64,PHNjcmlwdD4=" alt="gambar">')
        );
    }

    public function test_unsafe_href_data_url_is_stripped(): void
    {
        $this->assertSame(
            '<a>link</a>',
            HtmlSanitizer::sanitize('<a href="data:text/html;base64,PHNjcmlwdD4=">link</a>')
        );
    }

    public function test_disallowed_tags_and_attributes_are_stripped(): void
    {
        $this->assertSame(
            '<p>teks</p>',
            HtmlSanitizer::sanitize('<iframe src="x"></iframe><div class="a" id="b"><p>teks</p></div>')
        );
    }

    public function test_nested_malicious_html_is_cleaned(): void
    {
        $this->assertSame(
            '<p>teks</p>',
            HtmlSanitizer::sanitize('<div><p>teks<script>alert(1)</script></p></div>')
        );
    }

    public function test_malformed_html_does_not_throw(): void
    {
        $output = HtmlSanitizer::sanitize('<p>a<p>');
        $this->assertStringContainsString('a', $output);
        $this->assertStringNotContainsString('script', $output);

        $stray = HtmlSanitizer::sanitize('</><script>');
        $this->assertStringNotContainsString('script', $stray);

        $bare = HtmlSanitizer::sanitize('<');
        $this->assertStringNotContainsString('script', $bare);
        $this->assertStringNotContainsString('<', $bare);
    }

    public function test_sanitizer_is_idempotent(): void
    {
        $input = '<p onclick="x()">Halo <script>alert(1)</script><a href="javascript:y()">klik</a></p>';
        $once = HtmlSanitizer::sanitize($input);
        $twice = HtmlSanitizer::sanitize($once);
        $this->assertSame($once, $twice);
    }

    public function test_unicode_arabic_and_indonesian_are_preserved(): void
    {
        $input = '<p>السلام عليكم ورحمة الله وبركاته</p><p>Semangat belajar! 🎓 éàü</p>';
        $this->assertSame($input, HtmlSanitizer::sanitize($input));
    }

    public function test_allowed_relative_and_https_urls_kept(): void
    {
        $input = '<a href="/storage/gambar.png">lokal</a><a href="https://example.com/a">web</a><img src="https://example.com/g.png" alt="x">';
        $this->assertSame($input, HtmlSanitizer::sanitize($input));
    }

    public function test_style_meta_and_script_tags_are_stripped(): void
    {
        $this->assertSame(
            '',
            HtmlSanitizer::sanitize('<style>body{color:red}</style><meta name="x" content="y"><script>z</script>')
        );
    }
}
