<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Deliberately small rich-text allowlist for Notes.
 *
 * It keeps presentation formatting out of the database as executable browser
 * markup. Incoming editor HTML is sanitized again on every write, and the
 * generated plain-text projection is used for server-side search.
 */
class RichTextSanitizer
{
    private const ALLOWED = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'strong', 'em', 'u',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a',
    ];

    private const REMOVE_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'svg', 'math', 'template', 'meta', 'link', 'base',
    ];

    /** @return array{html: string, text: string} */
    public function sanitize(string $html): array
    {
        $html = mb_substr($html, 0, 250_000);
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="scrunote-note-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
            $root = (new DOMXPath($document))->query('//*[@id="scrunote-note-root"]')->item(0);

            if (! $root instanceof DOMElement) {
                return ['html' => '<p><br></p>', 'text' => ''];
            }

            $this->cleanChildren($document, $root);
            $safe = $this->innerHtml($document, $root);
            $safe = $this->normalizeEmptyDocument($safe);

            return [
                'html' => $safe,
                'text' => $this->plainText($safe),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function cleanChildren(DOMDocument $document, DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var DOMElement $element */
            $element = $node;
            $tag = strtolower($element->tagName);

            if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                $parent->removeChild($element);

                continue;
            }

            if ($tag === 'div') {
                $element = $this->replaceTag($document, $element, 'p');
                $tag = 'p';
            } elseif ($tag === 'b') {
                $element = $this->replaceTag($document, $element, 'strong');
                $tag = 'strong';
            } elseif ($tag === 'i') {
                $element = $this->replaceTag($document, $element, 'em');
                $tag = 'em';
            }

            $this->cleanChildren($document, $element);

            if (! in_array($tag, self::ALLOWED, true)) {
                $this->unwrap($element);

                continue;
            }

            $href = $tag === 'a' ? $element->getAttribute('href') : null;
            while ($element->attributes->length > 0) {
                $attribute = $element->attributes->item(0);
                if ($attribute !== null) {
                    $element->removeAttributeNode($attribute);
                }
            }

            if ($tag === 'a') {
                $safeHref = $href === null ? null : $this->safeHref($href);
                if ($safeHref === null) {
                    $this->unwrap($element);
                } else {
                    $element->setAttribute('href', $safeHref);
                    $element->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }
    }

    private function replaceTag(DOMDocument $document, DOMElement $element, string $name): DOMElement
    {
        $replacement = $document->createElement($name);
        foreach (iterator_to_array($element->childNodes) as $child) {
            $replacement->appendChild($child);
        }
        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }
        $parent->removeChild($element);
    }

    private function safeHref(string $href): ?string
    {
        $href = trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', '', $href) ?? '');
        if ($href === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $href : null;
    }

    private function innerHtml(DOMDocument $document, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }

        return $html;
    }

    private function normalizeEmptyDocument(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', '', $html) ?? '';
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? '<p><br></p>' : $html;
    }

    private function plainText(string $html): string
    {
        $withBreaks = preg_replace('/<(br|\/p|\/h[1-4]|\/li|\/blockquote|\/pre)\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';

        return trim($text);
    }
}
