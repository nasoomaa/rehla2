<?php

declare(strict_types=1);

namespace Rehla\Content\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = ['p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'ul', 'ol', 'li', 'blockquote', 'a', 'code', 'pre', 'hr'];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template'];

    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="rehla-content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*');
        if ($nodes !== false) {
            $elements = [];
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
            foreach (array_reverse($elements) as $element) {
                if ($element->getAttribute('id') === 'rehla-content-root') {
                    continue;
                }
                $tag = strtolower($element->tagName);
                if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                    $element->parentNode?->removeChild($element);

                    continue;
                }
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->unwrap($element);

                    continue;
                }
                foreach (iterator_to_array($element->attributes) as $attribute) {
                    if ($tag !== 'a' || ! in_array(strtolower($attribute->name), ['href', 'title'], true)) {
                        $element->removeAttribute($attribute->name);
                    }
                }
                if ($tag === 'a' && ! $this->isSafeHref($element->getAttribute('href'))) {
                    $element->removeAttribute('href');
                }
            }
        }

        $root = $document->getElementById('rehla-content-root');
        if ($root === null) {
            return '';
        }
        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild instanceof DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function isSafeHref(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }
        $scheme = parse_url($href, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }
}
