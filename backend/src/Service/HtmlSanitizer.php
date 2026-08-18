<?php

namespace App\Service;

/**
 * Lightweight HTML sanitizer that allows only safe formatting tags and attributes.
 *
 * Allowed elements: text formatting (p, br, strong, em, u, s, del, sub, sup,
 * h1-h6, blockquote, ul, ol, li, pre, hr), structure (div, span), hyperlinks
 * (a), and images (img). Everything else is stripped. Event handler attributes
 * and javascript:/data: URIs are removed.
 */
class HtmlSanitizer
{
    /** @var array<string, string[]> tag => allowed attributes */
    private const ALLOWED_TAGS = [
        'p'        => [],
        'br'       => [],
        'strong'   => [],
        'b'        => [],
        'em'       => [],
        'i'        => [],
        'u'        => [],
        's'        => [],
        'del'      => [],
        'sub'      => [],
        'sup'      => [],
        'h1'       => [],
        'h2'       => [],
        'h3'       => [],
        'h4'       => [],
        'h5'       => [],
        'h6'       => [],
        'blockquote' => [],
        'ul'       => [],
        'ol'       => [],
        'li'       => [],
        'pre'      => [],
        'hr'       => [],
        'div'      => ['style'],
        'span'     => ['style'],
        'a'        => ['href', 'title', 'target', 'rel'],
        'img'      => ['src', 'alt', 'width', 'height', 'style'],
    ];

    private const ALLOWED_PROTOCOLS = ['http:', 'https:', 'mailto:'];

    private const STYLE_PROPERTIES = [
        'color', 'background-color', 'font-size', 'font-weight', 'font-style',
        'text-decoration', 'text-align', 'line-height', 'margin', 'margin-left',
        'margin-right', 'margin-top', 'margin-bottom', 'padding', 'padding-left',
        'padding-right', 'padding-top', 'padding-bottom', 'border', 'border-radius',
        'width', 'height', 'max-width', 'max-height', 'display', 'float', 'clear',
        'list-style-type', 'vertical-align',
    ];

    public function sanitize(string $html): string
    {
        $doc = new \DOMDocument();

        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        libxml_clear_errors();

        $this->sanitizeNode($doc->documentElement);

        return trim($doc->saveHTML());
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);

            if (!isset(self::ALLOWED_TAGS[$tag])) {
                $this->unwrapElement($node);

                return;
            }

            $this->sanitizeAttributes($node, $tag);

            // Recurse into children (snapshot since DOM mutates in place)
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) {
                $this->sanitizeNode($child);
            }
        } elseif ($node instanceof \DOMDocumentFragment || $node instanceof \DOMDocument) {
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) {
                $this->sanitizeNode($child);
            }
        }
    }

    private function sanitizeAttributes(\DOMElement $node, string $tag): void
    {
        $allowed = self::ALLOWED_TAGS[$tag] ?? [];

        // Collect all attribute names first (DOM modifies in place)
        $attrs = [];
        foreach ($node->attributes as $attr) {
            $attrs[] = $attr->name;
        }

        foreach ($attrs as $attrName) {
            $attr = $node->getAttributeNode($attrName);
            if (null === $attr) {
                continue;
            }

            $lower = strtolower($attrName);

            // Always strip event handlers
            if (str_starts_with($lower, 'on')) {
                $node->removeAttribute($attrName);

                continue;
            }

            if (!in_array($lower, $allowed, true)) {
                $node->removeAttribute($attrName);

                continue;
            }

            // Validate URLs
            if ($lower === 'href' || $lower === 'src') {
                $value = trim($attr->value);
                $lowerValue = strtolower($value);

                if ($this->isDangerousUrl($lowerValue)) {
                    $node->removeAttribute($attrName);

                    continue;
                }

                // Ensure absolute URLs with safe protocols
                if ($lowerValue !== '' && !str_starts_with($lowerValue, 'http://') && !str_starts_with($lowerValue, 'https://') && !str_starts_with($lowerValue, 'mailto:')) {
                    $node->removeAttribute($attrName);
                }
            }

            // Validate style attribute
            if ($lower === 'style') {
                $node->setAttribute($attrName, $this->sanitizeStyle($attr->value));
            }

            // Enforce rel on links
            if ($lower === 'a' && $tag === 'a') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function isDangerousUrl(string $lowerValue): bool
    {
        if (str_starts_with($lowerValue, 'javascript:') || str_starts_with($lowerValue, 'vbscript:') || str_starts_with($lowerValue, 'data:')) {
            return true;
        }

        // Check for protocol bypass attempts (e.g. "java script:")
        if (preg_match('/^[\s\x00-\x1f]*java[\s\x00-\x1f]*script:/i', $lowerValue)) {
            return true;
        }

        return false;
    }

    private function sanitizeStyle(string $style): string
    {
        $allowed = [];
        $declarations = explode(';', $style);

        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if ('' === $declaration) {
                continue;
            }

            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = trim(strtolower($parts[0]));
            $value = trim($parts[1]);

            if (in_array($property, self::STYLE_PROPERTIES, true) && !$this->containsDangerousCss($value)) {
                $allowed[] = "$property: $value";
            }
        }

        return implode('; ', $allowed);
    }

    private function containsDangerousCss(string $value): bool
    {
        $lower = strtolower($value);

        // Block url() that could load external resources or javascript
        if (preg_match('/url\s*\(/i', $lower)) {
            return true;
        }

        // Block expression() (IE)
        if (str_contains($lower, 'expression(')) {
            return true;
        }

        // Block -moz-binding (XSS vector)
        if (str_contains($lower, '-moz-binding')) {
            return true;
        }

        return false;
    }

    private function unwrapElement(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (null === $parent) {
            return;
        }

        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            $parent->insertBefore($node->ownerDocument->importNode($child, true), $node);
        }

        $parent->removeChild($node);
    }
}
