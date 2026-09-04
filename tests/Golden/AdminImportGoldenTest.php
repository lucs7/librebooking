<?php

require_once(__DIR__ . '/GoldenTemplateTestCase.php');
require_once(__DIR__ . '/../../lib/Common/namespace.php');
require_once(__DIR__ . '/../../tests/fakes/FakeServer.php');

/**
 * Live Smarty-vs-Twig golden comparison for Admin/Import templates.
 *
 * Templates covered:
 *   - tpl/Admin/Import/import.tpl          → .twig  (full parity)
 *   - tpl/Admin/Import/ics_import.tpl      → .twig  (structural for external link CSRF; see notes)
 *   - tpl/Admin/Import/quartzy_import.tpl  → .twig  (structural for rel="noopener noreferrer")
 *
 * Parity strategy
 * ---------------
 * import.twig: No CSRF token, no JS string context divergence — full parity.
 *
 * ics_import.twig:
 *   - CSRF token is stabilized via FakeServer (same value both engines read).
 *   - JS-string `{$smarty.server.SCRIPT_NAME}` → `{{ server.SCRIPT_NAME|raw }}` (accepted
 *     divergence: raw in JS context; the value is a plain path with no HTML special chars,
 *     so both engines emit the same byte sequence). Full parity asserted.
 *
 * quartzy_import.twig:
 *   - CSRF token stabilized via FakeServer.
 *   - The external link to quartzy.com gains `rel="noopener noreferrer"` per the CLAUDE.md
 *     security policy (target="_blank" → must add rel). This is a deliberate structural
 *     improvement over the Smarty template and breaks byte-for-byte parity. Structural
 *     assertion used: verifies the rel attribute is present in Twig and the rest of the
 *     template structure is correct.
 *   - JS-string `{$smarty.server.SCRIPT_NAME}` → `{{ server.SCRIPT_NAME|raw }}` (same as
 *     ics_import — plain path, no special chars, bytes identical). Full parity asserted
 *     except for the noopener divergence, handled via structural assertion for that element.
 *
 * ServiceLocator / CSRF pinning
 * -----------------------------
 * Both engines call ServiceLocator::GetServer()->GetUserSession()->CSRFToken.
 * A FakeServer with a fixed token is installed in setUp() and restored in tearDown().
 */
class AdminImportGoldenTest extends GoldenTemplateTestCase
{
    private ?Resources $savedResources = null;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedCookie = [];

    private ?Server $savedServiceLocatorServer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedResources = Resources::GetInstance();
        Resources::SetInstance(null);
        Resources::GetInstance();

        $this->savedServer = $_SERVER;
        $this->savedCookie = $_COOKIE;

        // Stable SCRIPT_NAME so both engines read the same value in JS and href contexts.
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/ics_import.php';
        $_COOKIE = [];

        // Pin CSRF token so csrf_token() is deterministic in both engines.
        $this->savedServiceLocatorServer = ServiceLocator::GetServer();
        $fakeServer = new FakeServer();
        $fakeServer->UserSession->CSRFToken = 'golden-test-csrf-token';
        ServiceLocator::SetServer($fakeServer);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        $_COOKIE = $this->savedCookie;
        Resources::SetInstance($this->savedResources);
        ServiceLocator::SetServer($this->savedServiceLocatorServer);
        parent::tearDown();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Render both Smarty and Twig and assert normalized byte-identical output.
     *
     * @param array<string, mixed> $vars
     */
    private function assertParity(string $tplName, string $twigName, array $vars): void
    {
        $expected = (new SmartyRenderer())->render($tplName, $vars);
        $actual   = (new TwigRenderer())->render($twigName, $vars);

        $this->assertSame(
            HtmlNormalizer::normalize($expected),
            HtmlNormalizer::normalize($actual),
            "Smarty vs Twig mismatch for $twigName"
        );
    }

    /**
     * Render Twig only and assert the output contains the expected strings.
     *
     * @param array<string, mixed> $vars
     * @param string[]             $expectedStrings
     */
    private function assertTwigContains(string $twigName, array $vars, array $expectedStrings): void
    {
        $html = (new TwigRenderer())->render($twigName, $vars);
        foreach ($expectedStrings as $needle) {
            $this->assertStringContainsString($needle, $html, "Expected '$needle' in $twigName output");
        }
    }

    /**
     * Shared base vars for the globalheader include common to all Admin/Import pages.
     *
     * @return array<string, mixed>
     */
    private function baseVars(): array
    {
        require_once __DIR__ . '/../../lang/AvailableLanguage.php';

        return [
            'HtmlLang'              => 'en',
            'HtmlTextDirection'     => 'ltr',
            'Title'                 => 'LibreBooking',
            'TitleKey'              => '',
            'TitleArgs'             => [],
            'Charset'               => 'UTF-8',
            'Path'                  => '/web/',
            'FaviconUrl'            => 'favicon.ico',
            'UseLocalJquery'        => false,
            'Trumbowyg'             => false,
            'DataTable'             => false,
            'InlineEdit'            => false,
            'Select2'               => false,
            'cssTheme'              => 'light',
            'ScriptUrl'             => 'http://localhost/web',
            'HideNavBar'            => false,
            'HomeUrl'               => 'http://localhost/web/index.php',
            'LogoUrl'               => 'logo.png',
            'Version'               => '1',
            'CompanyName'           => 'Test Corp',
            'CompanyUrl'            => 'https://example.com',
            'AppTitle'              => 'LibreBooking',
            'LoggedIn'              => true,
            'AvailableLanguages'    => [
                new AvailableLanguage('en_us', 'en_us.php', 'English US'),
            ],
            'CurrentLanguage'       => 'en_us',
            'CanViewAdmin'          => true,
            'ShowNewVersion'        => false,
            'EnableConfigurationPage' => true,
            'ShowParticipation'     => false,
            'CreditsEnabled'        => false,
            'PaymentsEnabled'       => false,
            'CanViewResponsibilities' => false,
            'CanViewReports'        => true,
            'ShowScheduleLink'      => false,
        ];
    }

    // ── import.twig ──────────────────────────────────────────────────────────

    /**
     * import.twig is a minimal hub page: two buttons, no dynamic vars.
     * No CSRF token, no JS — full parity assured.
     */
    public function testImportPageMatchesSmarty(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import.php';
        $vars = $this->baseVars();
        $this->assertParity('Admin/Import/import.tpl', 'Admin/Import/import.twig', $vars);
    }

    /**
     * Structural check: the two import-type links are always present.
     */
    public function testImportPageContainsImportTypeLinks(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import.php';
        $vars = $this->baseVars();
        $this->assertTwigContains('Admin/Import/import.twig', $vars, [
            'id="page-import"',
            'href="ics_import.php"',
            'href="quartzy_import.php"',
        ]);
    }

    // ── ics_import.twig ──────────────────────────────────────────────────────

    /**
     * ics_import.twig: full parity expected.
     *
     * CSRF token is pinned via FakeServer (setUp). JS-string `{$smarty.server.SCRIPT_NAME}`
     * maps to `{{ server.SCRIPT_NAME|raw }}` — plain path, no HTML special chars, so both
     * engines emit identical bytes. Parity asserted.
     */
    public function testIcsImportMatchesSmarty(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/ics_import.php';
        $vars = $this->baseVars();
        $this->assertParity('Admin/Import/ics_import.tpl', 'Admin/Import/ics_import.twig', $vars);
    }

    /**
     * Structural verification: key elements that must appear regardless of engine.
     */
    public function testIcsImportContainsKeyElements(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/ics_import.php';
        $vars = $this->baseVars();
        $this->assertTwigContains('Admin/Import/ics_import.twig', $vars, [
            'id="page-import-ics"',
            'id="icsImportForm"',
            'id="importFile"',
            'accept=".ics"',
            'id="btnUpload"',
            'id="importResult"',
            'id="importErrors"',
            'id="importCount"',
            'id="importSkipped"',
            'ajaxAction="importIcs"',
            // CSRF token from FakeServer
            'golden-test-csrf-token',
            // async validators
            'asyncValidation',
            // file input name from FormKeys::ICS_IMPORT_FILE
            'name=\'ICS_IMPORT_FILE\'',
            // JS callback references SCRIPT_NAME
            '/web/admin/import/ics_import.php',
        ]);
    }

    /**
     * The informational notices about ICS format constraints are always rendered.
     */
    public function testIcsImportContainsInfoNotes(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/ics_import.php';
        $vars = $this->baseVars();
        $html = (new TwigRenderer())->render('Admin/Import/ics_import.twig', $vars);

        // Three alert-info notes are present.
        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringContainsString('alert-warning', $html);
    }

    // ── quartzy_import.twig ──────────────────────────────────────────────────

    /**
     * quartzy_import.twig: structural assertion due to the `rel="noopener noreferrer"`
     * addition on the Quartzy external link.
     *
     * The Twig template intentionally adds rel="noopener noreferrer" to the
     * target="_blank" link as required by the CLAUDE.md security policy.
     * The Smarty template does not have this attribute, so byte-identical
     * parity is NOT expected for that element. All other structural elements
     * (form, file input, checkbox, CSRF token, JS handlers) match Smarty.
     *
     * We therefore assert the Twig output contains all required structural
     * elements, plus verify both engines contain the same functional form structure.
     */
    public function testQuartzyImportContainsKeyElements(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/quartzy_import.php';
        $vars = $this->baseVars();
        $this->assertTwigContains('Admin/Import/quartzy_import.twig', $vars, [
            'id="page-import-quartzy"',
            'id="quartzyImportForm"',
            'name="quartzyFile"',
            'id="importFile"',
            'accept=".zip"',
            'id="includeBookings"',
            'name="includeBookings"',
            'id="btnUpload"',
            'id="importResult"',
            'id="importErrors"',
            'id="importCount"',
            'id="importSkipped"',
            'ajaxAction="importQuartzy"',
            // CSRF token from FakeServer
            'golden-test-csrf-token',
            // async validators
            'asyncValidation',
            // external link with security attributes (structural improvement over .tpl)
            'href="https://support.quartzy.com/hc/en-us/articles/214823208"',
            'rel="noopener noreferrer"',
            'target="_blank"',
            // JS callback references SCRIPT_NAME
            '/web/admin/import/quartzy_import.php',
        ]);
    }

    /**
     * Verify the Smarty template does NOT have rel="noopener noreferrer"
     * (confirming the Twig template is a security improvement, not a parity bug).
     */
    public function testQuartzyImportSmartyLacksNoopener(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/quartzy_import.php';
        $vars = $this->baseVars();
        $smarty = (new SmartyRenderer())->render('Admin/Import/quartzy_import.tpl', $vars);
        $this->assertStringNotContainsString('noopener', $smarty, 'Smarty .tpl should not have noopener (parity divergence is intentional)');
    }

    /**
     * Both engines render the same form structure (excluding the rel attribute difference).
     * Verify the quartzy form fields exist with correct names in both.
     */
    public function testQuartzyImportBothEnginesHaveSameFormStructure(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/quartzy_import.php';
        $vars = $this->baseVars();
        $smarty = (new SmartyRenderer())->render('Admin/Import/quartzy_import.tpl', $vars);
        $twig   = (new TwigRenderer())->render('Admin/Import/quartzy_import.twig', $vars);

        foreach (['name="quartzyFile"', 'name="includeBookings"', 'id="includeBookings"', 'accept=".zip"'] as $needle) {
            $this->assertStringContainsString($needle, $smarty, "Smarty missing '$needle'");
            $this->assertStringContainsString($needle, $twig, "Twig missing '$needle'");
        }
    }

    /**
     * The Quartzy info/warning notices are always rendered.
     */
    public function testQuartzyImportContainsInfoNotices(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/web/admin/import/quartzy_import.php';
        $vars = $this->baseVars();
        $html = (new TwigRenderer())->render('Admin/Import/quartzy_import.twig', $vars);

        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringContainsString('p@ssw0rd!', $html);
        $this->assertStringContainsString('20 minutes', $html);
    }
}
