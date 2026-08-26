<?php

class HtmlNormalizer
{
    public static function normalize(string $html): string
    {
        // Canonicalize numeric entities to their named/short equivalents
        $html = preg_replace_callback('/&#0*(\d+);/', static function ($m) {
            return '&#' . (int) $m[1] . ';';
        }, $html);
        $html = str_replace(['&#39;', '&#34;'], ['&apos;', '&quot;'], $html);

        // Collapse whitespace between tags and trim runs of whitespace
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }
}
