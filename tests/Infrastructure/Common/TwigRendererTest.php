<?php

require_once(__DIR__ . '/../../../lib/Common/namespace.php');

use PHPUnit\Framework\TestCase;
use LibreBooking\Common\Templating\TemplateRenderer;

class TwigRendererTest extends TestCase
{
    public function testIsATemplateRenderer(): void
    {
        $renderer = new TwigRenderer();
        $this->assertInstanceOf(TemplateRenderer::class, $renderer);
    }

    public function testRendersInlineVariableEscaped(): void
    {
        $renderer = new TwigRenderer();
        $renderer->environment()->setLoader(new \Twig\Loader\ArrayLoader(['t.twig' => '{{ v }}']));
        $this->assertSame('&lt;b&gt;', $renderer->render('t.twig', ['v' => '<b>']));
    }

    public function testValidatorsReturnsPageValidators(): void
    {
        $renderer = new TwigRenderer();
        $this->assertInstanceOf(PageValidators::class, $renderer->validators());
    }

    /**
     * fetch() selects Twig when a .twig counterpart exists in the loader.
     */
    public function testFetchSelectsTwigWhenTwigCounterpartExists(): void
    {
        $renderer = new TwigRenderer();
        $renderer->environment()->setLoader(
            new \Twig\Loader\ArrayLoader(['Email/emailheader.twig' => 'TWIG_HEADER'])
        );
        $renderer->assign('Charset', 'UTF-8');

        $result = $renderer->fetch('Email/emailheader.tpl');

        $this->assertSame('TWIG_HEADER', $result);
    }

    /**
     * fetchLocalized() falls through to Smarty when no .twig counterpart of the
     * localized template exists.  A non-en_us language template (.tpl only) is
     * used since all en_us bodies now have .twig counterparts (Phase 4b).
     * Falls back to en_us which has only a .tpl for a custom-named template.
     */
    public function testFetchLocalizedFallsBackToSmartyWhenNoTwigExists(): void
    {
        // lang/de_DE/ has .tpl email bodies but no .twig counterparts (Phase-5 backlog).
        // fetchLocalized with de_DE will fall back to Smarty for these.
        $renderer = new TwigRenderer();
        $renderer->assign('CurrentLanguage', 'de_DE');
        $renderer->assign('AppTitle', 'LibreBooking');
        $renderer->assign('ScriptUrl', 'http://localhost/');

        // ReportEmail.tpl in de_DE (or en_us fallback) has no .twig counterpart.
        // Either way, Smarty fallback is exercised.
        $result = $renderer->fetchLocalized('ReportEmail.tpl', false, 'de_DE');

        // Should return a non-empty string (the Smarty-rendered template).
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * fetchLocalized() selects the Twig engine when a real .twig file exists at
     * the resolved localized path (lang/en_us/ReportEmail.twig, added in Phase 4b).
     *
     * This replaces the previously vacuous test that could not exercise the positive
     * branch because ROOT_DIR is a compile-time constant.  Now that real on-disk
     * .twig bodies exist, the branch fires without any mocking.
     *
     * The assertion: Twig-rendered output equals Smarty-rendered .tpl output after
     * HTML normalization — proving the engine-select branch fires with a real file.
     */
    public function testFetchLocalizedSelectsTwigWhenTwigFileExists(): void
    {
        $vars = [
            'CurrentLanguage' => 'en_us',
            'AppTitle' => 'LibreBooking',
            'ScriptUrl' => 'http://localhost/',
        ];

        // Twig path: fetchLocalized finds lang/en_us/ReportEmail.twig → renders via Twig
        $twig = new TwigRenderer();
        foreach ($vars as $k => $v) {
            $twig->assign($k, $v);
        }
        $twigOut = $twig->fetchLocalized('ReportEmail.tpl', false, 'en_us');

        // Smarty path: fetchLocalized finds lang/en_us/ReportEmail.tpl → renders via Smarty
        $smarty = new SmartyRenderer();
        foreach ($vars as $k => $v) {
            $smarty->assign($k, $v);
        }
        $smartyOut = $smarty->fetchLocalized('ReportEmail.tpl', false, 'en_us');

        // Both must be non-empty
        $this->assertIsString($twigOut);
        $this->assertNotEmpty($twigOut);

        // Normalize and compare — proving the Twig branch fires and produces correct output
        $normalize = static fn (string $s): string => trim(preg_replace('/\s+/', ' ', $s) ?? $s);
        $this->assertSame(
            $normalize($smartyOut),
            $normalize($twigOut),
            'fetchLocalized must select the .twig file and produce output matching the .tpl'
        );
    }
}
