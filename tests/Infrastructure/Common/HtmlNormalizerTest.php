<?php

require_once(__DIR__ . '/HtmlNormalizer.php');

use PHPUnit\Framework\TestCase;

class HtmlNormalizerTest extends TestCase
{
    public function testCollapsesInterTagWhitespace(): void
    {
        $a = "<ul>\n   <li>x</li>\n</ul>";
        $b = '<ul><li>x</li></ul>';
        $this->assertSame(
            HtmlNormalizer::normalize($a),
            HtmlNormalizer::normalize($b)
        );
    }

    public function testCanonicalizesNumericEntities(): void
    {
        $this->assertSame(
            HtmlNormalizer::normalize('O&#039;Brien'),
            HtmlNormalizer::normalize('O&#39;Brien')
        );
    }

    public function testKeepsStructuralDifferences(): void
    {
        $this->assertNotSame(
            HtmlNormalizer::normalize('<div><span>a</span></div>'),
            HtmlNormalizer::normalize('<div><b>a</b></div>')
        );
    }
}
