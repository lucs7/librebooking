<?php

require_once(__DIR__ . '/../../../lib/Common/namespace.php');

/**
 * Parity tests for the html_options() Twig function vs Smarty's built-in {html_options}.
 *
 * The Twig implementation uses ENT_COMPAT + no double-encode (matching
 * smarty_function_escape_special_chars) and appends "\n" after each </option>
 * for byte-for-byte parity with Smarty's output.
 */
class LibreBookingExtensionHtmlOptionsTest extends TestBase
{
    private \Smarty\Smarty $smarty;

    public function setUp(): void
    {
        parent::setUp();

        $this->smarty = new \Smarty\Smarty();
        $this->smarty->setTemplateDir(sys_get_temp_dir());
        $this->smarty->setCompileDir(sys_get_temp_dir() . '/smarty_compile_html_options');
        $this->smarty->setConfigDir(sys_get_temp_dir());
        $this->smarty->setCacheDir(sys_get_temp_dir() . '/smarty_cache_html_options');
        @mkdir(sys_get_temp_dir() . '/smarty_compile_html_options', 0755, true);
        @mkdir(sys_get_temp_dir() . '/smarty_cache_html_options', 0755, true);
    }

    /** Render {html_options ...} with Smarty and return its output. */
    private function renderSmarty(array $values, array $output, mixed $selected): string
    {
        $tpl = $this->smarty->createTemplate(
            'string:{html_options values=$v output=$o selected=$s}'
        );
        $tpl->assign('v', $values);
        $tpl->assign('o', $output);
        $tpl->assign('s', $selected);
        return $this->smarty->fetch($tpl);
    }

    /** Render {{ html_options(...) }} with Twig and return its output. */
    private function renderTwig(array $values, array $output, mixed $selected): string
    {
        $env = new \Twig\Environment(
            new \Twig\Loader\ArrayLoader(['t' => '{{ html_options(values=v, output=o, selected=s) }}']),
            ['autoescape' => false]
        );
        $env->addExtension(new LibreBookingExtension(Resources::GetInstance(), 'http://example.com/'));
        return $env->render('t', ['v' => $values, 'o' => $output, 's' => $selected]);
    }

    // ── normal list ───────────────────────────────────────────────────────────

    public function testNormalListMatchesSmarty(): void
    {
        $values = [1, 2, 3];
        $output = ['One', 'Two', 'Three'];

        $expected = $this->renderSmarty($values, $output, '');
        $actual = $this->renderTwig($values, $output, '');

        $this->assertSame($expected, $actual);
    }

    // ── selected value ────────────────────────────────────────────────────────

    public function testSelectedValueMatchesSmarty(): void
    {
        $values = [1, 2, 3];
        $output = ['One', 'Two', 'Three'];

        $expected = $this->renderSmarty($values, $output, '2');
        $actual = $this->renderTwig($values, $output, '2');

        $this->assertSame($expected, $actual);
        $this->assertStringContainsString('selected="selected"', $actual);
    }

    // ── HTML-special chars in value and label (escaping parity) ───────────────

    public function testHtmlSpecialCharsMatchesSmarty(): void
    {
        // '&' must be escaped in value and label; selected match should work on raw value
        $values = ['a&b', 'c<d', "e'f"];
        $output = ['A&B', 'C<D', "E'F"];

        $expected = $this->renderSmarty($values, $output, 'a&b');
        $actual = $this->renderTwig($values, $output, 'a&b');

        $this->assertSame($expected, $actual);
        // The selected option's value should be HTML-escaped in the attribute
        $this->assertStringContainsString('value="a&amp;b" selected="selected"', $actual);
        // Single quotes left unescaped (ENT_COMPAT, matching Smarty)
        $this->assertStringContainsString("value=\"e'f\"", $actual);
    }

    // ── trailing newline per option (byte-parity) ─────────────────────────────

    public function testEachOptionHasTrailingNewline(): void
    {
        $actual = $this->renderTwig([1, 2], ['One', 'Two'], '');
        $lines = explode("\n", rtrim($actual, "\n"));
        foreach ($lines as $line) {
            $this->assertStringStartsWith('<option', $line);
            $this->assertStringContainsString('</option>', $line);
        }
        // Every option ends with </option>\n — so splitting on \n gives only option lines
        $this->assertCount(2, $lines);
    }
}
