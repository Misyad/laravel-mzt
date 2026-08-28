<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'em',
        'a',
        'ul',
        'ol',
        'li',
        'blockquote',
        'h1',
        'h2',
        'h3',
        'h4',
        'img',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt'],
    ];

    private const SAFE_URL_SCHEMES = ['http', 'https', 'mailto', 'tel', 'ftp'];

    private const REMOVED_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'meta',
        'link',
        'base',
        'form',
        'input',
        'textarea',
        'select',
        'option',
        'button',
        'template',
        'noscript',
        'svg',
        'math',
        'video',
        'audio',
        'source',
        'canvas',
        'map',
        'area',
        'frame',
        'frameset',
        'applet',
        'param',
        'head',
        'title',
        'body',
        'html',
    ];

    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        if (strpos($html, '<') === false) {
            return $html;
        }

        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } catch (\Throwable $e) {
            $loaded = false;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }

        self::sanitizeChildren($body);

        $output = '';
        foreach (iterator_to_array($body->childNodes) as $node) {
            $output .= $dom->saveHTML($node);
        }

        return self::stripXmlProcessingInstruction($output);
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! ($child instanceof DOMElement)) {
                $parent->removeChild($child);

                continue;
            }

            $tag = strtolower($child->nodeName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeChildren($child);
                if (in_array($tag, self::REMOVED_TAGS, true)) {
                    $parent->removeChild($child);
                } else {
                    self::unwrap($parent, $child);
                }

                continue;
            }

            self::sanitizeAttributes($child);
            self::sanitizeChildren($child);
        }
    }

    private static function unwrap(DOMNode $parent, DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }
        $parent->removeChild($element);
    }

    private static function sanitizeAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' || $name === 'src') {
                if (! self::isSafeUrl($attribute->nodeValue, $name === 'src')) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }
    }

    private static function isSafeUrl(string $url, bool $forImage = false): bool
    {
        $url = preg_replace('/[\x00-\x20\x7F]/', '', trim($url));
        if ($url === '') {
            return false;
        }

        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $url, $matches)) {
            $scheme = strtolower($matches[1]);
            if (in_array($scheme, self::SAFE_URL_SCHEMES, true)) {
                return true;
            }
            if ($forImage && $scheme === 'data'
                && preg_match('#^data:image/(png|jpe?g|gif|webp)(;base64)?,#', $url)) {
                return true;
            }

            return false;
        }

        return true;
    }

    private static function stripXmlProcessingInstruction(string $output): string
    {
        return preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $output) ?? $output;
    }
}
