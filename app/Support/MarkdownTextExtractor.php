<?php

namespace App\Support;

class MarkdownTextExtractor
{
    /**
     * Builds a searchable plain-text projection only. Markdown source stays
     * untouched and preview rendering is handled by the browser sanitizer.
     */
    public function extract(string $markdown): string
    {
        $text = preg_replace('/```[^\n]*\n(.*?)```/su', '$1', $markdown) ?? $markdown;
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/<((?:https?:\/\/|mailto:)[^>]+)>/u', '$1', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}>\s?/mu', '', $text) ?? $text;
        $text = preg_replace('/^\s*(?:[-+*]|\d+\.)\s+(?:\[[ xX]\]\s+)?/mu', '', $text) ?? $text;
        $text = preg_replace('/^\s*[-:| ]+\|[-:| ]*$/mu', '', $text) ?? $text;
        $text = str_replace('|', ' ', $text);
        $text = preg_replace('/[`*_~]/u', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
