# Smarty → Twig Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace LibreBooking's Smarty templating with Twig, incrementally and without downtime, ending with a single autoescaped Twig engine and Smarty fully removed.

**Architecture:** Both engines run side by side behind a `TemplateRenderer` interface (Approach A from the design). `SmartyRenderer` wraps today's `SmartyPage`; `TwigRenderer` wraps a configured Twig `Environment`. Render entry points (`Page`, `EmailMessage`, `Control`, `DashboardPresenter`) depend on the interface. Engine is chosen per template by file existence (`.twig` → Twig, else `.tpl` → Smarty), so pages, controls, includes, and email migrate independently. Every custom Smarty plugin becomes a Twig function/filter in `LibreBookingExtension`; registered-class constants become a `constant()` function. A golden-test harness captures current Smarty output as the source of truth and asserts each converted template matches after structural normalization.

**Tech Stack:** PHP ≥8.2, `twig/twig`, PHPUnit 11.5+, existing PSR-4 (`LibreBooking\` → `src/`), Smarty 5.8 (removed in Phase 5).

**Design doc:** `docs/superpowers/specs/2026-08-26-smarty-to-twig-migration-design.md`

## Global Constraints

- PHP `>=8.2`; must pass on 8.2, 8.3, 8.4, 8.5 (CI matrix).
- New self-contained classes go under `src/` with namespace `LibreBooking\`, one type per file, `declare(strict_types=1);`, filename == type name (PSR-4 strict; CI runs `composer dump-autoload --optimize --strict-psr`).
- Classes extending legacy globals (`Control`, `Page`, `EmailMessage`) stay in their legacy directories (they cannot see namespaced globals cleanly through the legacy include chains). The renderer interface itself is new/self-contained → `src/`.
- Tests for `src/` code live in `tests/src/`, namespace `LibreBooking\Tests\...`; run with `composer phpunit -- --testsuite src`.
- PSR-12; run `composer phpcsfixer:fix` before every commit. Static analysis: `composer phpstan` (level 2) and `composer phpstan_next` must stay green (`src/` is in both `paths`).
- Single quotes, short array syntax, 4-space PHP indent, LF, no trailing whitespace, no magic numbers (use class constants/enums).
- Rich text: use `sanitize_rich_text` for stored rich-text HTML; keep it display-only; never `html_entity_decode` directly on rich-text display paths; preserve `sanitize_rich_text|url2link|nl2br` chains. `url2link` only linkifies safe `http`/`https`/email.
- External `target="_blank"` links need `rel="noopener noreferrer"`.
- Web entry points (`Web/*.php`) must keep `require_once ROOT_DIR . 'vendor/autoload.php';` AND `require_once ROOT_DIR . 'lib/Common/namespace.php';` (legacy bootstrap registers the global exception handler; dropping it causes runtime fatals — see PR #1552).
- Commit trailers: `Assisted-by: Claude:claude-opus-4-8` and `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`. Conventional-commit headers ≤72 chars; scope `templating` (or the area).
- Config artifact rule: no config-key changes are expected in this plan; if any are added, run `composer config-dist:generate` + `composer env-example:generate`.
- Dev server: Docker watches `app/`, served at `http://localhost:80`, for manual visual spot-checks (in addition to golden tests).
- Do NOT modify `lib/external/`.

---

## Baseline API surface (used by the interface)

Exact members current call sites use on `SmartyPage`, gathered from the codebase:

- `Page` (`Pages/Page.php`): `new SmartyPage($resources, $path)`, `assign()`, `display()`, `getTemplateVars()`, `AddTemplateDirectory()`, `Validators->Register()`, `IsValid()`, `fetch()` (via `SetJson`→`display`).
- `EmailMessage` (`lib/Email/EmailMessage.php`): `new SmartyPage($resources)`, `assign()`, `fetch()`, `FetchLocalized($name, $enforce)`, `getTemplateVars()`, `SmartyTranslate()`.
- `Control` (`Controls/Control.php`): `new X(SmartyPage)`, `createData()`, `createTemplate($name, $data)->display()`.
- `DashboardPresenter`: `new SmartyPage()` passed into control constructors.

The `TemplateRenderer` interface must cover: `assign`, `render` (returns string), `display` (echoes), `fetch` (returns string), `getTemplateVars`, `fetchLocalized`, `addTemplateDirectory`, plus access to a validators holder and `isValid()`. Control rendering (`createData`/`createTemplate`) is abstracted behind `renderControlTemplate(name, vars): string` (Task 1.9).

---

# PHASE 0 — Foundation (no behavior change; still all Smarty)

End state of phase: renderer interface exists, all four entry points route through it (defaulting to Smarty), golden-test harness works and baselines are captured, converter is chosen. Every page still renders identically via Smarty.

### Task 0.1: Add the Twig dependency

**Files:**
- Modify: `composer.json` (`require` block)
- Modify: `composer.lock` (generated)

- [ ] **Step 1: Add twig/twig**

Run:
```bash
composer require "twig/twig:^3.10"
```
Expected: `composer.json` `require` now contains `"twig/twig": "^3.10"`; `composer.lock` updated; `vendor/twig/` present.

- [ ] **Step 2: Verify autoload + syntax**

Run:
```bash
composer dump-autoload --optimize --strict-psr && ./ci/ci-phplint
```
Expected: no PSR-4 violations, no lint errors.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "build(templating): add twig/twig dependency

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.2: `TemplateRenderer` interface

**Files:**
- Create: `src/Common/Templating/TemplateRenderer.php`
- Test: `tests/src/Common/Templating/TemplateRendererTest.php`

**Interfaces:**
- Produces: `LibreBooking\Common\Templating\TemplateRenderer` with methods:
  - `assign(string $name, mixed $value): void`
  - `render(string $templateName, array $vars = []): string`
  - `display(string $templateName): void`
  - `fetch(string $templateName): string`
  - `getTemplateVars(?string $name = null): mixed`
  - `fetchLocalized(string $templateName, bool $enforceCustomTemplate, ?string $languageCode = null): string`
  - `addTemplateDirectory(string $dir): void`
  - `renderControlTemplate(string $templateName, array $vars): string`
  - `validators(): PageValidators`
  - `isValid(): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Common\Templating;

use LibreBooking\Common\Templating\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    public function testInterfaceDefinesRenderingSurface(): void
    {
        $reflection = new \ReflectionClass(TemplateRenderer::class);
        $this->assertTrue($reflection->isInterface());
        foreach (['assign', 'render', 'display', 'fetch', 'getTemplateVars',
                  'fetchLocalized', 'addTemplateDirectory', 'renderControlTemplate',
                  'validators', 'isValid'] as $method) {
            $this->assertTrue($reflection->hasMethod($method), "missing $method");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer phpunit -- --testsuite src --filter TemplateRendererTest`
Expected: FAIL — `Class "LibreBooking\Common\Templating\TemplateRenderer" not found`.

- [ ] **Step 3: Create the interface**

```php
<?php

declare(strict_types=1);

namespace LibreBooking\Common\Templating;

interface TemplateRenderer
{
    public function assign(string $name, mixed $value): void;

    public function render(string $templateName, array $vars = []): string;

    public function display(string $templateName): void;

    public function fetch(string $templateName): string;

    public function getTemplateVars(?string $name = null): mixed;

    public function fetchLocalized(
        string $templateName,
        bool $enforceCustomTemplate,
        ?string $languageCode = null
    ): string;

    public function addTemplateDirectory(string $dir): void;

    public function renderControlTemplate(string $templateName, array $vars): string;

    public function validators(): \PageValidators;

    public function isValid(): bool;
}
```

- [ ] **Step 4: Regenerate autoload + run test**

Run: `composer dump-autoload --strict-psr && composer phpunit -- --testsuite src --filter TemplateRendererTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
composer phpcsfixer:fix
git add src/Common/Templating/TemplateRenderer.php tests/src/Common/Templating/TemplateRendererTest.php composer.json
git commit -m "feat(templating): add TemplateRenderer interface

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.3: `SmartyRenderer` wrapping `SmartyPage`

**Files:**
- Create: `lib/Common/Templating/SmartyRenderer.php` (legacy tree — it references global `SmartyPage`/`PageValidators`, so NOT `src/`)
- Modify: `lib/Common/namespace.php` (add require for the new file)
- Test: `tests/Infrastructure/Common/SmartyRendererTest.php`

**Interfaces:**
- Consumes: `TemplateRenderer` (0.2), global `SmartyPage`.
- Produces: `SmartyRenderer implements \LibreBooking\Common\Templating\TemplateRenderer`, constructor `__construct(?Resources $resources = null, ?string $rootPath = null)`, plus `public function smarty(): SmartyPage` (escape hatch for code still needing the raw object during migration).

- [ ] **Step 1: Write the failing test**

```php
<?php

require_once(__DIR__ . '/../../../lib/Common/namespace.php');

use PHPUnit\Framework\TestCase;
use LibreBooking\Common\Templating\TemplateRenderer;

class SmartyRendererTest extends TestCase
{
    public function testIsATemplateRenderer(): void
    {
        $renderer = new SmartyRenderer();
        $this->assertInstanceOf(TemplateRenderer::class, $renderer);
    }

    public function testAssignAndGetRoundTrip(): void
    {
        $renderer = new SmartyRenderer();
        $renderer->assign('Foo', 'bar');
        $this->assertSame('bar', $renderer->getTemplateVars('Foo'));
    }

    public function testExposesUnderlyingSmartyPage(): void
    {
        $renderer = new SmartyRenderer();
        $this->assertInstanceOf(SmartyPage::class, $renderer->smarty());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer phpunit -- tests/Infrastructure/Common/SmartyRendererTest.php`
Expected: FAIL — class `SmartyRenderer` not found.

- [ ] **Step 3: Implement `SmartyRenderer`**

```php
<?php

use LibreBooking\Common\Templating\TemplateRenderer;

class SmartyRenderer implements TemplateRenderer
{
    private SmartyPage $page;

    public function __construct(?Resources $resources = null, ?string $rootPath = null)
    {
        $this->page = new SmartyPage($resources, $rootPath);
    }

    public function smarty(): SmartyPage
    {
        return $this->page;
    }

    public function assign(string $name, mixed $value): void
    {
        $this->page->assign($name, $value);
    }

    public function render(string $templateName, array $vars = []): string
    {
        foreach ($vars as $k => $v) {
            $this->page->assign($k, $v);
        }
        return $this->page->fetch($templateName);
    }

    public function display(string $templateName): void
    {
        $this->page->display($templateName);
    }

    public function fetch(string $templateName): string
    {
        return $this->page->fetch($templateName);
    }

    public function getTemplateVars(?string $name = null): mixed
    {
        return $this->page->getTemplateVars($name);
    }

    public function fetchLocalized(
        string $templateName,
        bool $enforceCustomTemplate,
        ?string $languageCode = null
    ): string {
        return $this->page->FetchLocalized($templateName, $enforceCustomTemplate, $languageCode);
    }

    public function addTemplateDirectory(string $dir): void
    {
        $this->page->AddTemplateDirectory($dir);
    }

    public function renderControlTemplate(string $templateName, array $vars): string
    {
        $data = $this->page->createData();
        foreach ($vars as $k => $v) {
            $data->assign($k, $v);
        }
        return $this->page->createTemplate($templateName, $data)->fetch();
    }

    public function validators(): PageValidators
    {
        return $this->page->Validators;
    }

    public function isValid(): bool
    {
        return $this->page->IsValid();
    }
}
```

- [ ] **Step 4: Register the file in the legacy include chain**

In `lib/Common/namespace.php`, add (near the other Common requires):
```php
require_once(__DIR__ . '/Templating/SmartyRenderer.php');
```
(Ensure it is required AFTER `SmartyPage.php` is available.)

- [ ] **Step 5: Run test to verify it passes**

Run: `composer phpunit -- tests/Infrastructure/Common/SmartyRendererTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
composer phpcsfixer:fix
git add lib/Common/Templating/SmartyRenderer.php lib/Common/namespace.php tests/Infrastructure/Common/SmartyRendererTest.php
git commit -m "feat(templating): add SmartyRenderer wrapping SmartyPage

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.4: Twig `Environment` factory + `TwigRenderer` skeleton

**Files:**
- Create: `src/Common/Templating/TwigEnvironmentFactory.php`
- Create: `lib/Common/Templating/TwigRenderer.php` (legacy tree — needs global `PageValidators`, `Resources`, `Configuration`)
- Modify: `lib/Common/namespace.php`
- Modify: `.gitignore` (add `tpl_c/twig/`)
- Test: `tests/src/Common/Templating/TwigEnvironmentFactoryTest.php`, `tests/Infrastructure/Common/TwigRendererTest.php`

**Interfaces:**
- Produces:
  - `LibreBooking\Common\Templating\TwigEnvironmentFactory::create(array $templateDirs, string $cacheDir, bool $debug): \Twig\Environment` — configures loader (`FilesystemLoader`), `autoescape => 'html'`, `cache` (false when `$debug`), `strict_variables => false`.
  - `TwigRenderer implements TemplateRenderer`, constructor `__construct(?Resources $resources = null, ?string $rootPath = null)`, plus `environment(): \Twig\Environment`.

- [ ] **Step 1: Write the failing test (factory)**

```php
<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Common\Templating;

use LibreBooking\Common\Templating\TwigEnvironmentFactory;
use PHPUnit\Framework\TestCase;

class TwigEnvironmentFactoryTest extends TestCase
{
    public function testCreatesAutoescapingEnvironment(): void
    {
        $dir = sys_get_temp_dir();
        $env = TwigEnvironmentFactory::create([$dir], $dir . '/twigcache', true);
        $this->assertInstanceOf(\Twig\Environment::class, $env);
        // autoescape on: a rendered variable is HTML-escaped
        $env->setLoader(new \Twig\Loader\ArrayLoader(['t' => '{{ v }}']));
        $this->assertSame('&lt;b&gt;', $env->render('t', ['v' => '<b>']));
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- --testsuite src --filter TwigEnvironmentFactoryTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the factory**

```php
<?php

declare(strict_types=1);

namespace LibreBooking\Common\Templating;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigEnvironmentFactory
{
    /**
     * @param string[] $templateDirs
     */
    public static function create(array $templateDirs, string $cacheDir, bool $debug): Environment
    {
        $existing = array_values(array_filter($templateDirs, 'is_dir'));
        $loader = new FilesystemLoader($existing);

        $env = new Environment($loader, [
            'autoescape' => 'html',
            'cache' => $debug ? false : $cacheDir,
            'debug' => $debug,
            'strict_variables' => false,
            'auto_reload' => $debug,
        ]);

        return $env;
    }
}
```

- [ ] **Step 4: Run factory test — PASS**

Run: `composer dump-autoload --strict-psr && composer phpunit -- --testsuite src --filter TwigEnvironmentFactoryTest`
Expected: PASS.

- [ ] **Step 5: Write the failing test (TwigRenderer)**

```php
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
}
```

- [ ] **Step 6: Run to verify fail**

Run: `composer phpunit -- tests/Infrastructure/Common/TwigRendererTest.php`
Expected: FAIL — class not found.

- [ ] **Step 7: Implement `TwigRenderer`** (validators via standalone `PageValidators`)

```php
<?php

use LibreBooking\Common\Templating\TemplateRenderer;
use LibreBooking\Common\Templating\TwigEnvironmentFactory;

class TwigRenderer implements TemplateRenderer
{
    private \Twig\Environment $twig;
    private array $vars = [];
    private string $rootPath;
    private Resources $resources;
    public PageValidators $Validators;

    public function __construct(?Resources $resources = null, ?string $rootPath = null)
    {
        $this->resources = $resources ?? Resources::GetInstance();
        $this->rootPath = $rootPath ?? '';
        $base = __DIR__ . '/../../../';

        $debug = isset($_GET['debug']) ||
            !Configuration::Instance()->GetKey(ConfigKeys::CACHE_TEMPLATES, new BooleanConverter());

        $dirs = [
            $base . 'tpl',
            $base . 'lang/' . $this->resources->CurrentLanguage,
        ];

        $this->twig = TwigEnvironmentFactory::create(
            templateDirs: $dirs,
            cacheDir: $base . 'tpl_c/twig',
            debug: $debug,
        );

        // Populated fully in Task 1.x
        $this->Validators = new PageValidators($this);
    }

    public function environment(): \Twig\Environment
    {
        return $this->twig;
    }

    public function assign(string $name, mixed $value): void
    {
        $this->vars[$name] = $value;
    }

    public function render(string $templateName, array $vars = []): string
    {
        return $this->twig->render($templateName, array_merge($this->vars, $vars));
    }

    public function display(string $templateName): void
    {
        echo $this->render($templateName);
    }

    public function fetch(string $templateName): string
    {
        return $this->render($templateName);
    }

    public function getTemplateVars(?string $name = null): mixed
    {
        if ($name === null) {
            return $this->vars;
        }
        return $this->vars[$name] ?? null;
    }

    public function fetchLocalized(
        string $templateName,
        bool $enforceCustomTemplate,
        ?string $languageCode = null
    ): string {
        // Full localized/-custom resolution implemented in Task 4.x; default path for now.
        return $this->render($templateName);
    }

    public function addTemplateDirectory(string $dir): void
    {
        /** @var \Twig\Loader\FilesystemLoader $loader */
        $loader = $this->twig->getLoader();
        if (is_dir($dir)) {
            $loader->prependPath($dir);
        }
    }

    public function renderControlTemplate(string $templateName, array $vars): string
    {
        return $this->twig->render($templateName, $vars);
    }

    public function validators(): PageValidators
    {
        return $this->Validators;
    }

    public function isValid(): bool
    {
        try {
            $this->Validators->Validate();
            return $this->Validators->AreAllValid();
        } catch (\Exception $ex) {
            Log::Error('Error during page validation', $ex);
            return false;
        }
    }
}
```

Note: `PageValidators` constructor currently type-hints `SmartyPage`. Widen it in this task to accept `SmartyPage|TwigRenderer` (or drop the hint) so both renderers can own a validators holder. Add a one-line test in `tests/Infrastructure/Common/TwigRendererTest.php` asserting `$renderer->validators()` returns a `PageValidators`.

- [ ] **Step 8: Register file + gitignore twig cache**

Add to `lib/Common/namespace.php`:
```php
require_once(__DIR__ . '/Templating/TwigRenderer.php');
```
Add to `.gitignore`:
```
tpl_c/twig/
```

- [ ] **Step 9: Run TwigRenderer test — PASS**

Run: `composer phpunit -- tests/Infrastructure/Common/TwigRendererTest.php`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
composer phpcsfixer:fix
git add src/Common/Templating/TwigEnvironmentFactory.php lib/Common/Templating/TwigRenderer.php lib/Common/namespace.php .gitignore tests/src/Common/Templating/TwigEnvironmentFactoryTest.php tests/Infrastructure/Common/TwigRendererTest.php composer.json lib/Common/Validators/*.php
git commit -m "feat(templating): add Twig environment factory and TwigRenderer skeleton

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.5: `LibreBookingExtension` skeleton

**Files:**
- Create: `lib/Common/Templating/LibreBookingExtension.php` (legacy tree — depends on global `Resources`, `Configuration`, `SmartyPage` logic sources)
- Modify: `lib/Common/namespace.php`
- Modify: `lib/Common/Templating/TwigRenderer.php` (register the extension in the constructor)
- Test: `tests/Infrastructure/Common/LibreBookingExtensionTest.php`

**Interfaces:**
- Produces: `LibreBookingExtension extends \Twig\Extension\AbstractExtension`, constructor `__construct(Resources $resources, string $rootPath)`; `getFunctions(): array` and `getFilters(): array` (empty for now); a `constant` function is added in Task 1.1.

- [ ] **Step 1: Write the failing test**

```php
<?php

require_once(__DIR__ . '/../../../lib/Common/namespace.php');

use PHPUnit\Framework\TestCase;

class LibreBookingExtensionTest extends TestCase
{
    public function testIsATwigExtension(): void
    {
        $ext = new LibreBookingExtension(Resources::GetInstance(), '');
        $this->assertInstanceOf(\Twig\Extension\AbstractExtension::class, $ext);
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- tests/Infrastructure/Common/LibreBookingExtensionTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement skeleton**

```php
<?php

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class LibreBookingExtension extends AbstractExtension
{
    public function __construct(
        private Resources $resources,
        private string $rootPath
    ) {
    }

    public function getFunctions(): array
    {
        return [];
    }

    public function getFilters(): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Register in `TwigRenderer` constructor** (after env creation)

```php
$this->twig->addExtension(new LibreBookingExtension($this->resources, $this->rootPath));
```
And add `require_once(__DIR__ . '/Templating/LibreBookingExtension.php');` to `lib/Common/namespace.php`.

- [ ] **Step 5: Run test — PASS**

Run: `composer phpunit -- tests/Infrastructure/Common/LibreBookingExtensionTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
composer phpcsfixer:fix
git add lib/Common/Templating/LibreBookingExtension.php lib/Common/Templating/TwigRenderer.php lib/Common/namespace.php tests/Infrastructure/Common/LibreBookingExtensionTest.php
git commit -m "feat(templating): add LibreBookingExtension skeleton

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.6: Route `Page` through the renderer (Smarty default, behavior unchanged)

**Files:**
- Modify: `Pages/Page.php` (constructor + `$smarty` property usages)
- Test: existing page tests under `tests/Pages/` must stay green.

**Interfaces:**
- Consumes: `SmartyRenderer` (0.3), `TemplateRenderer` (0.2).
- Produces: `Page::$renderer` (typed `TemplateRenderer`), a protected `RendererFor(...)` factory returning a `SmartyRenderer` by default.

- [ ] **Step 1: Introduce a renderer factory seam (test first)**

Add test `tests/Pages/PageRendererTest.php`:
```php
<?php

require_once(__DIR__ . '/../../Pages/Page.php');

use PHPUnit\Framework\TestCase;
use LibreBooking\Common\Templating\TemplateRenderer;

class PageRendererTest extends TestCase
{
    public function testPageUsesATemplateRenderer(): void
    {
        // A concrete trivial Page subclass for the test
        $page = new class () extends Page {
            public function __construct()
            {
                parent::__construct('');
            }
            public function PageLoad(): void
            {
            }
            public function exposeRenderer(): TemplateRenderer
            {
                return $this->renderer;
            }
        };
        $this->assertInstanceOf(TemplateRenderer::class, $page->exposeRenderer());
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- tests/Pages/PageRendererTest.php`
Expected: FAIL — `$this->renderer` undefined / property missing.

- [ ] **Step 3: Refactor `Page` to hold a `TemplateRenderer`**

In `Pages/Page.php`:
- Add property: `protected \LibreBooking\Common\Templating\TemplateRenderer $renderer;`
- Keep `protected $smarty` as a BC alias pointing at the Smarty page during coexistence.
- Replace `$this->smarty = new SmartyPage($resources, $this->path);` with:
```php
$smartyRenderer = new SmartyRenderer($resources, $this->path);
$this->renderer = $smartyRenderer;
$this->smarty = $smartyRenderer->smarty(); // BC: existing $this->smarty->... calls keep working
```
Leave every other `$this->smarty->assign(...)` line untouched (they still work via the exposed `SmartyPage`). This keeps the diff minimal and behavior identical.

- [ ] **Step 4: Run test + full page suite**

Run: `composer phpunit -- tests/Pages/PageRendererTest.php && composer phpunit -- --testsuite all`
Expected: PageRendererTest PASS; overall suite green (no regressions).

- [ ] **Step 5: Commit**

```bash
composer phpcsfixer:fix
git add Pages/Page.php tests/Pages/PageRendererTest.php
git commit -m "refactor(templating): route Page through TemplateRenderer (Smarty default)

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.7: Route `EmailMessage` through the renderer

**Files:**
- Modify: `lib/Email/EmailMessage.php`
- Test: `tests/Application/Reservation/ReservationEmailTemplateContextTest.php` (existing) stays green; add `tests/Infrastructure/Email/EmailMessageRendererTest.php`.

**Interfaces:**
- Consumes: `SmartyRenderer`.
- Produces: `EmailMessage::$renderer` (`TemplateRenderer`), `$this->email` kept as BC alias to the `SmartyPage`.

- [ ] **Step 1: Failing test**

```php
<?php

require_once(__DIR__ . '/../../../lib/Common/namespace.php');
require_once(__DIR__ . '/../../../lib/Email/namespace.php');

use PHPUnit\Framework\TestCase;
use LibreBooking\Common\Templating\TemplateRenderer;

class EmailMessageRendererTest extends TestCase
{
    public function testEmailUsesRenderer(): void
    {
        $msg = new class () extends EmailMessage {
            public function __construct()
            {
                parent::__construct();
            }
            public function To()
            {
                return [];
            }
            public function Subject()
            {
                return '';
            }
            public function Body()
            {
                return '';
            }
            public function exposeRenderer(): TemplateRenderer
            {
                return $this->renderer;
            }
        };
        $this->assertInstanceOf(TemplateRenderer::class, $msg->exposeRenderer());
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- tests/Infrastructure/Email/EmailMessageRendererTest.php`
Expected: FAIL — `$this->renderer` missing.

- [ ] **Step 3: Refactor `EmailMessage`**

- Add `protected \LibreBooking\Common\Templating\TemplateRenderer $renderer;`
- Replace `$this->email = new SmartyPage($resources);` with:
```php
$smartyRenderer = new SmartyRenderer($resources);
$this->renderer = $smartyRenderer;
$this->email = $smartyRenderer->smarty(); // BC alias
```
- `FetchTemplate()` and `Translate()` keep using `$this->email` (unchanged behavior).

- [ ] **Step 4: Run tests**

Run: `composer phpunit -- tests/Infrastructure/Email/EmailMessageRendererTest.php && composer phpunit -- tests/Application/Reservation/ReservationEmailTemplateContextTest.php`
Expected: PASS; existing email test still green.

- [ ] **Step 5: Commit**

```bash
composer phpcsfixer:fix
git add lib/Email/EmailMessage.php tests/Infrastructure/Email/EmailMessageRendererTest.php
git commit -m "refactor(templating): route EmailMessage through TemplateRenderer

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.8: Route `Control` base through the renderer

**Files:**
- Modify: `Controls/Control.php`
- Test: `tests/Infrastructure/Common/SmartyControlTest.php` (existing) stays green; add `tests/Controls/ControlRendererTest.php`.

**Interfaces:**
- Consumes: `TemplateRenderer`, `SmartyRenderer`.
- Produces: `Control::__construct(TemplateRenderer|SmartyPage $renderer)` accepting either (BC), storing a `TemplateRenderer`; `Control::Display()` uses `renderControlTemplate()`.

- [ ] **Step 1: Failing test**

```php
<?php

require_once(__DIR__ . '/../../Controls/Control.php');
require_once(__DIR__ . '/../../lib/Common/namespace.php');

use PHPUnit\Framework\TestCase;

class ControlRendererTest extends TestCase
{
    public function testAcceptsSmartyRenderer(): void
    {
        $control = new class (new SmartyRenderer()) extends Control {
            public function PageLoad(): void
            {
            }
        };
        $this->assertInstanceOf(Control::class, $control);
    }

    public function testStillAcceptsRawSmartyPageForBackCompat(): void
    {
        $control = new class (new SmartyPage()) extends Control {
            public function PageLoad(): void
            {
            }
        };
        $this->assertInstanceOf(Control::class, $control);
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- tests/Controls/ControlRendererTest.php`
Expected: FAIL (constructor rejects `SmartyRenderer`).

- [ ] **Step 3: Refactor `Control`**

```php
<?php

use LibreBooking\Common\Templating\TemplateRenderer;

abstract class Control
{
    protected TemplateRenderer $renderer;
    protected $id = null;
    /** @var array<string,mixed> */
    protected array $data = [];

    public function __construct(TemplateRenderer|SmartyPage $renderer)
    {
        // BC: some callers still pass a raw SmartyPage.
        $this->renderer = $renderer instanceof SmartyPage
            ? new SmartyRenderer_FromExisting($renderer)
            : $renderer;
        $this->id = uniqid();
    }

    public function Set($var, $value)
    {
        $this->data[$var] = $value;
    }

    protected function Get($var)
    {
        return $this->data[$var] ?? null;
    }

    protected function Display($templateName)
    {
        echo $this->renderer->renderControlTemplate($templateName, $this->data);
    }

    abstract public function PageLoad();
}
```

Add a tiny adapter `SmartyRenderer_FromExisting` in the same file (or fold into `SmartyRenderer` as a static `wrap(SmartyPage): self`). Prefer adding `SmartyRenderer::wrap(SmartyPage $page): self` to `SmartyRenderer` and calling `SmartyRenderer::wrap($renderer)` here instead of a new class. Update `renderControlTemplate` in `SmartyRenderer` already handles the render.

Note the semantic change: `$this->data` moves from `\Smarty\Data` to a plain array; `renderControlTemplate` builds the isolated scope per call in both renderers, preserving control-scope isolation.

- [ ] **Step 4: Run tests (control + existing SmartyControlTest)**

Run: `composer phpunit -- tests/Controls/ControlRendererTest.php && composer phpunit -- tests/Infrastructure/Common/SmartyControlTest.php`
Expected: PASS; existing control test green.

- [ ] **Step 5: Commit**

```bash
composer phpcsfixer:fix
git add Controls/Control.php lib/Common/Templating/SmartyRenderer.php tests/Controls/ControlRendererTest.php
git commit -m "refactor(templating): route Control base through TemplateRenderer

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.9: Route `DashboardPresenter` through `SmartyRenderer`

**Files:**
- Modify: `Presenters/DashboardPresenter.php:24-51`
- Test: `tests/Presenters/DashboardPresenterTest.php` (existing if present) stays green; else add a minimal instantiation test.

- [ ] **Step 1: Failing/guard test**

Add `tests/Presenters/DashboardPresenterRendererTest.php` that instantiates the presenter's controls with a `SmartyRenderer` and asserts no error (mirror existing dashboard test setup for dependencies).

- [ ] **Step 2: Run to verify fail** (if the presenter still hard-codes `new SmartyPage()`)

Run: `composer phpunit -- tests/Presenters/DashboardPresenterRendererTest.php`
Expected: FAIL or type error.

- [ ] **Step 3: Replace `new SmartyPage()` with `new SmartyRenderer()`**

Change each `new AnnouncementsControl(new SmartyPage())` etc. (lines 24-51) to `new AnnouncementsControl(new SmartyRenderer())`. Behavior identical (SmartyRenderer wraps SmartyPage).

- [ ] **Step 4: Run tests**

Run: `composer phpunit -- --testsuite presenters`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
composer phpcsfixer:fix
git add Presenters/DashboardPresenter.php tests/Presenters/DashboardPresenterRendererTest.php
git commit -m "refactor(templating): use SmartyRenderer in DashboardPresenter

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.10: Golden-test harness

**Files:**
- Create: `tests/Golden/GoldenTemplateTestCase.php` (base class with normalization + assertion)
- Create: `tests/Golden/HtmlNormalizer.php`
- Create: `tests/Golden/README.md` (how to add a golden test; how to regenerate baselines)
- Create: `tests/Golden/fixtures/` (per-template fixture PHP files, added as templates migrate)
- Create: `tests/Golden/baselines/` (committed normalized Smarty output, added per template)
- Modify: `phpunit.xml.dist` (add a `golden` testsuite → `./tests/Golden`)
- Test: `tests/Golden/HtmlNormalizerTest.php`

**Interfaces:**
- Produces:
  - `HtmlNormalizer::normalize(string $html): string` — collapses insignificant whitespace between tags, trims, and canonicalizes numeric HTML entities (`&#039;`→`&#39;` form) and `&quot;`/`&#34;` equivalences.
  - `GoldenTemplateTestCase::assertMatchesBaseline(string $baselineName, string $renderedHtml): void` — normalizes both sides and diffs; on `UPDATE_GOLDEN=1` env, writes the baseline instead of asserting.
  - `GoldenTemplateTestCase::captureSmartyBaseline(string $templateName, array $vars, string $baselineName): void` — renders via `SmartyRenderer`, normalizes, writes to `baselines/`.

- [ ] **Step 1: Write the normalizer test**

```php
<?php

require_once(__DIR__ . '/HtmlNormalizer.php');

use PHPUnit\Framework\TestCase;

class HtmlNormalizerTest extends TestCase
{
    public function testCollapsesInterTagWhitespace(): void
    {
        $a = "<ul>\n   <li>x</li>\n</ul>";
        $b = "<ul><li>x</li></ul>";
        $this->assertSame(
            HtmlNormalizer::normalize($a),
            HtmlNormalizer::normalize($b)
        );
    }

    public function testCanonicalizesNumericEntities(): void
    {
        $this->assertSame(
            HtmlNormalizer::normalize("O&#039;Brien"),
            HtmlNormalizer::normalize("O&#39;Brien")
        );
    }

    public function testKeepsStructuralDifferences(): void
    {
        $this->assertNotSame(
            HtmlNormalizer::normalize("<div><span>a</span></div>"),
            HtmlNormalizer::normalize("<div><b>a</b></div>")
        );
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- tests/Golden/HtmlNormalizerTest.php`
Expected: FAIL — `HtmlNormalizer` not found.

- [ ] **Step 3: Implement `HtmlNormalizer`**

```php
<?php

class HtmlNormalizer
{
    public static function normalize(string $html): string
    {
        // Canonicalize numeric entities to their named/short equivalents
        $html = preg_replace_callback('/&#0*(\d+);/', static function ($m) {
            return '&#' . (int) $m[1] . ';';
        }, $html);
        $html = str_replace(['&#39;', '&#34;'], ["&apos;", '&quot;'], $html);

        // Collapse whitespace between tags and trim runs of whitespace
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }
}
```

- [ ] **Step 4: Run normalizer test — PASS**

Run: `composer phpunit -- tests/Golden/HtmlNormalizerTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Implement `GoldenTemplateTestCase`**

```php
<?php

require_once(__DIR__ . '/HtmlNormalizer.php');
require_once(__DIR__ . '/../../lib/Common/namespace.php');

use PHPUnit\Framework\TestCase;

abstract class GoldenTemplateTestCase extends TestCase
{
    protected function baselineDir(): string
    {
        return __DIR__ . '/baselines';
    }

    protected function captureSmartyBaseline(string $templateName, array $vars, string $baselineName): void
    {
        $renderer = new SmartyRenderer();
        $html = $renderer->render($templateName, $vars);
        file_put_contents(
            $this->baselineDir() . '/' . $baselineName . '.html',
            HtmlNormalizer::normalize($html)
        );
    }

    protected function assertMatchesBaseline(string $baselineName, string $renderedHtml): void
    {
        $path = $this->baselineDir() . '/' . $baselineName . '.html';
        $normalized = HtmlNormalizer::normalize($renderedHtml);

        if (getenv('UPDATE_GOLDEN') === '1' || !file_exists($path)) {
            file_put_contents($path, $normalized);
            $this->markTestSkipped("Baseline written: $baselineName");
            return;
        }

        $this->assertSame(file_get_contents($path), $normalized, "Golden mismatch: $baselineName");
    }
}
```

- [ ] **Step 6: Add `golden` testsuite to `phpunit.xml.dist`**

Add inside `<testsuites>`:
```xml
    <testsuite name="golden">
      <directory>./tests/Golden</directory>
    </testsuite>
```
Ensure the default `all` suite still excludes integration but includes `tests/` (Golden is under `tests/`, so it is included automatically). Baseline-writing tests must not run destructively in CI: guard `captureSmartyBaseline` usage behind explicit baseline-capture tests only (see Task 1.10 recipe).

- [ ] **Step 7: Write harness README**

Create `tests/Golden/README.md` documenting: how baselines are captured (`UPDATE_GOLDEN=1 composer phpunit -- --testsuite golden`), the normalization contract (structural equivalence, not byte-identical), and how to add a fixture + baseline + Twig assertion per template.

- [ ] **Step 8: Commit**

```bash
composer phpcsfixer:fix
git add tests/Golden/ phpunit.xml.dist
git commit -m "test(templating): add golden-template test harness

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 0.11: Evaluate and choose a Smarty→Twig converter

**Files:**
- Create: `docs/superpowers/notes/2026-08-26-twig-converter-evaluation.md`

- [ ] **Step 1: Assemble a representative sample**

Pick five templates spanning the hard cases: `tpl/login.tpl` (validators, includes, `$smarty.server`), `tpl/Admin/manage_resources.tpl` or similar (heavy `control` + `datatable`), `tpl/Reservation/reservation.tpl` (largest/most complex), `tpl/Email/emailheader.tpl` (email), `tpl/Controls/` example (control template). List them in the note.

- [ ] **Step 2: Evaluate candidate converters**

Research and try the available open-source Smarty→Twig converters (search: "smarty to twig converter", GitHub). For each, run it on the sample and score (0–3) on: variable output, `{if}`/`{foreach}`/`{include}`, method-call preservation (`$x->Foo()`), modifier/filter rewriting, and graceful handling of unknown plugins (`control`, `translate`, `datatable`, buttons). Record raw output snippets.

- [ ] **Step 3: Decide**

Recommend either a specific tool (as a first-pass only) OR a small repo-specific PHP/sed conversion script (define its scope: variables, `{if}`, `{foreach}`, `{include}`, `{$x|modifier}` name remaps, `Class::CONST`→`constant('Class::CONST')`, `$smarty.server.X`→global). Write the decision + rationale in the note. Whatever is chosen, its output is always hand-finished and gated by golden tests.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/notes/2026-08-26-twig-converter-evaluation.md
git commit -m "docs(templating): evaluate Smarty to Twig converters

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# PHASE 1 — Twig engine complete + proven on leaf pages

End state: `LibreBookingExtension` implements all functions/filters/`constant()`; the Twig `control` function works; autoescape is on; `error.tpl`, `wait-box.tpl`, `login.tpl` and their shared includes render via Twig and pass golden tests. Smarty is still the default for everything else (engine selection by `.twig` existence).

### Task 1.1: `constant()` function + registered-class globals

**Files:**
- Modify: `lib/Common/Templating/LibreBookingExtension.php`
- Test: `tests/Infrastructure/Common/LibreBookingExtensionTest.php`

**Interfaces:**
- Produces: Twig function `constant(string $name): mixed` (backed by PHP `constant()`), usable as `constant('CustomAttributeTypes::CHECKBOX')`.

- [ ] **Step 1: Failing test**

```php
public function testConstantFunctionResolvesClassConstant(): void
{
    $env = new \Twig\Environment(new \Twig\Loader\ArrayLoader([
        't' => "{{ constant('CustomAttributeTypes::CHECKBOX') }}",
    ]));
    $env->addExtension(new LibreBookingExtension(Resources::GetInstance(), ''));
    $this->assertSame((string) CustomAttributeTypes::CHECKBOX, $env->render('t'));
}
```

- [ ] **Step 2: Run to verify fail**

Run: `composer phpunit -- tests/Infrastructure/Common/LibreBookingExtensionTest.php --filter testConstantFunctionResolvesClassConstant`
Expected: FAIL — unknown function `constant`.

- [ ] **Step 3: Implement**

In `getFunctions()`:
```php
new TwigFunction('constant', static fn (string $name): mixed => constant($name)),
```

- [ ] **Step 4: Run — PASS**; **Step 5: Commit** (`feat(templating): add constant() Twig function`).

---

### Task 1.2: `translate` function

**Files:** Modify `LibreBookingExtension.php`; Test same file's test.

**Interfaces:**
- Produces: Twig function `translate(string $key, string|array $args = [])`, `is_safe => ['html']`. Body lifted from `SmartyPage::SmartyTranslate`.

- [ ] **Step 1: Failing test**

```php
public function testTranslateReturnsResourceString(): void
{
    $env = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => "{{ translate('Yes') }}"]));
    $env->addExtension(new LibreBookingExtension(Resources::GetInstance(), ''));
    $this->assertSame(Resources::GetInstance()->GetString('Yes'), $env->render('t'));
}
```

- [ ] **Step 2: Run → fail. Step 3: Implement:**

```php
new TwigFunction('translate', function (string $key, string|array $args = []): string {
    if (empty($args)) {
        return $this->resources->GetString($key, '');
    }
    $args = is_array($args) ? $args : explode(',', $args);
    return $this->resources->GetString($key, $args);
}, ['is_safe' => ['html']]),
```

- [ ] **Step 4: Run → PASS. Step 5: Commit** (`feat(templating): add translate Twig function`).

---

### Task 1.3: Modifiers → Twig filters

**Files:** Modify `LibreBookingExtension.php`; Test same file.

**Interfaces:**
- Produces filters: `sanitize_rich_text` (safe html, backed by `RichTextHtmlSanitizer::Sanitize`), `url2link` (safe html, backed by the linkify logic currently in `SmartyPage::CreateUrl` — extract that logic into a shared static so both engines use one implementation), `escapequotes`, `html_entity_decode`, `intval`, and confirm native Twig `lower`/`url_encode`/`length` cover `strtolower`/`urlencode`/`count`.

- [ ] **Step 1: Failing tests** (one per custom filter). Example:

```php
public function testSanitizeRichTextFilter(): void
{
    $env = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => "{{ v|sanitize_rich_text }}"]));
    $env->addExtension(new LibreBookingExtension(Resources::GetInstance(), ''));
    $this->assertSame(
        RichTextHtmlSanitizer::Sanitize('<b>hi</b><script>x</script>'),
        $env->render('t', ['v' => '<b>hi</b><script>x</script>'])
    );
}
```

- [ ] **Step 2: Run → fail. Step 3: Implement filters in `getFilters()`.** Extract the `CreateUrl`/`IsSafeLinkifyUrl`/`IsValidEmailAddress` logic from `SmartyPage` into a reusable static (e.g. a `LinkifyText` helper in `src/Common/Text/`) and call it from both `SmartyPage` (delegate) and the new `url2link` filter, so behavior is identical and DRY.
- [ ] **Step 4: Run → PASS. Step 5: Commit** (`feat(templating): add Twig filters for Smarty modifiers`).

---

### Task 1.4: HTML-emitting functions (ported from `SmartyPage`)

Because there are ~35 of these, split into **four commits by group**, each its own TDD cycle. All are `is_safe => ['html']`. Lift each body from `SmartyPage`, converting `$params['x']` array access to named Twig args. Keep the emitted markup byte-for-byte identical to `SmartyPage` (golden tests depend on it).

**Files:** Modify `LibreBookingExtension.php`; Test `tests/Infrastructure/Common/LibreBookingExtensionTest.php`.

- [ ] **Group A — buttons & icons:** `cancel_button`, `update_button`, `add_button`, `delete_button`, `reset_button`, `filter_button`, `ok_button`, `showhide_icon`, `indicator`. For each: failing test asserting output equals the current `SmartyPage` method output for representative params, implement, PASS. Commit `feat(templating): port button/icon Twig functions`.

- [ ] **Group B — form/validation:** `validator`, `async_validator`, `validation_group`, `textbox`, `object_html_options`, `setfocus`, `formname`, `csrf_token`, `read_only_attribute`. Note `validation_group` was a Smarty *block*; implement as a Twig function taking the already-rendered inner content string: `validation_group(content, class='error')`. The converter rewrites `{validation_group}...{/validation_group}` into `{{ validation_group(...) }}` wrapping a captured block (`{% set _vg %}...{% endset %}` then `{{ validation_group(_vg) }}`). Commit `feat(templating): port form/validation Twig functions`.

- [ ] **Group C — links/urls/format:** `html_link`, `add_querystring`, `sort_column`, `resource_image`, `fullname`, `formatdate`/`format_date`, `formatcurrency`, `js_array`, `linebreak`, `flush`. Commit `feat(templating): port link/format Twig functions`.

- [ ] **Group D — asset includes & datatables:** `jsfile`, `cssfile`, `vendor_js`, `vendor_css`, `datatable`, `datatablefilter`. These currently `echo`; in Twig return the string (safe html). Commit `feat(templating): port asset/datatable Twig functions`.

Each group's steps follow the standard cycle: (1) write failing test comparing to `SmartyPage` output, (2) run→FAIL, (3) implement, (4) run→PASS, (5) `composer phpcsfixer:fix` + commit.

DRY note: for functions with non-trivial logic (`formatdate`, `datatable`, `sort_column`, `fullname`), extract the shared body into a static helper called by both `SmartyPage` and the Twig function to avoid divergence during coexistence.

---

### Task 1.5: `control` Twig function + per-control engine fallback

**Files:**
- Modify: `lib/Common/Templating/LibreBookingExtension.php`
- Modify: `lib/Common/Templating/TwigRenderer.php` (pass a control-factory callback / the renderer into the extension so `control` can instantiate controls with the Twig renderer)
- Test: `tests/Infrastructure/Common/LibreBookingExtensionTest.php`

**Interfaces:**
- Produces: Twig function `control(type, params={})` (`is_safe html`) that: `require_once ROOT_DIR . "Controls/{type}.php"`, `new {type}($twigRenderer)`, `->Set()` each param, `->PageLoad()`, returns buffered output. The control's own `Display()` calls `renderControlTemplate()`, which renders `Controls/{name}.twig` if present else falls back to Smarty `.tpl` (implement the fallback inside `renderControlTemplate` of BOTH renderers by checking file existence in `tpl/` and delegating to a `SmartyRenderer` when only `.tpl` exists).

- [ ] **Step 1: Failing test** — render `{{ control('SomeSimpleControl') }}` and assert output matches the Smarty `{control type="SomeSimpleControl"}` output (use an existing simple control; capture Smarty output via `SmartyRenderer`).
- [ ] **Step 2: Run → fail. Step 3: Implement `control` + `renderControlTemplate` fallback.**
- [ ] **Step 4: Run → PASS. Step 5: Commit** (`feat(templating): add control Twig function with engine fallback`).

Fallback detail (in `TwigRenderer::renderControlTemplate` and page render): given `name.twig`, if `is_file(tpl/name.twig)` use Twig; elseif `is_file(tpl/name.tpl)` delegate to a `SmartyRenderer` instance rendering `name.tpl`. Add a unit test for this selection logic.

---

### Task 1.6: Engine selection by file existence in the render entry points

**Files:**
- Modify: `Pages/Page.php` (`Display`, `DisplayLocalized`, `SetJson` paths), `lib/Email/EmailMessage.php` (`FetchTemplate`)
- Create: `src/Common/Templating/EngineSelector.php`
- Test: `tests/src/Common/Templating/EngineSelectorTest.php`

**Interfaces:**
- Produces: `LibreBooking\Common\Templating\EngineSelector::twigNameFor(string $tplName): ?string` — returns the `.twig` filename if a Twig template exists in the search paths, else null. `Page::Display()` uses it: if a `.twig` exists, render via a `TwigRenderer` (constructed with the same assigned vars), else via the existing Smarty path.

- [ ] **Step 1: Failing test** for `EngineSelector` (given a temp dir with `foo.twig`, returns `foo.twig`; with only `foo.tpl`, returns null).
- [ ] **Step 2: Run→fail. Step 3: Implement** selector (checks `tpl/` and localized dirs for `basename(tpl, '.tpl') . '.twig'`).
- [ ] **Step 4: Run→PASS.**
- [ ] **Step 5: Wire into `Page::Display`** — the page must funnel *all* its assigned vars into whichever renderer it picks. Since Phase 0 kept `$this->smarty` as the working object, add: at `Display()` time, if `EngineSelector::twigNameFor($templateName)` is non-null, build a `TwigRenderer`, copy `getTemplateVars()` from the Smarty page into it, and render the `.twig`; else current behavior. Add a Page-level test that a page with a `.twig` present renders via Twig.
- [ ] **Step 6: Run page suite → green. Step 7: Commit** (`feat(templating): select Twig engine when a .twig template exists`).

---

### Task 1.7: Migrate shared includes (`globalheader`, `globalfooter`, `javascript-includes`)

**Files:**
- Create: `tpl/globalheader.twig`, `tpl/globalfooter.twig`, `tpl/javascript-includes.twig`
- Create fixtures + baselines under `tests/Golden/`
- Test: `tests/Golden/GlobalIncludesGoldenTest.php`

Follow the **per-template recipe** (defined once in Task 1.10). These three are includes, so their golden test renders them directly with representative header vars (Title, Path, CssUrl, LoggedIn, nav flags, etc. from `Page`). Keep `.tpl` files in place (engine falls back to them elsewhere until dependents migrate). Commit `feat(templating): migrate global header/footer/js includes to Twig`.

---

### Task 1.8: Migrate `error.tpl` and `wait-box.tpl`

**Files:** Create `tpl/error.twig`, `tpl/wait-box.twig`; fixtures + baselines; `tests/Golden/ErrorPageGoldenTest.php`, `WaitBoxGoldenTest.php`. Follow the recipe. Commit `feat(templating): migrate error and wait-box templates to Twig`.

---

### Task 1.9: Migrate `login.tpl`

**Files:** Create `tpl/login.twig`; fixtures covering branches (`ShowLoginError` on/off, `EnableCaptcha` on/off, announcements present/empty); baselines; `tests/Golden/LoginGoldenTest.php`. Follow the recipe. This exercises `translate`, `validation_group`/`validator`, `include`, `sanitize_rich_text`, `$smarty.server.SCRIPT_NAME`→global, `|default:array()`→Twig `default([])`. Commit `feat(templating): migrate login template to Twig`.

- [ ] **Manual check:** with the Docker dev server (`http://localhost:80`), load the login page and confirm it renders correctly (valid + error states).

---

### Task 1.10: Define the per-template migration recipe (reference)

This recipe is applied by every template task in Phases 2–4. It contains real, runnable steps; each application is mechanical. **A "template task" = migrating one `.tpl` to `.twig`.**

**Files per application:**
- Create: `tpl/<Area>/<name>.twig`
- Create: `tests/Golden/fixtures/<name>.php` (returns an array of representative vars, covering each branch the template takes)
- Create: `tests/Golden/baselines/<name>.html` (generated, committed)
- Create/append: `tests/Golden/<Area>GoldenTest.php`

- [ ] **Step 1: Capture the Smarty baseline** (once per template)

Add a test method that calls `captureSmartyBaseline('<Area>/<name>.tpl', $fixtureVars, '<name>')`, then:
```bash
UPDATE_GOLDEN=1 composer phpunit -- --testsuite golden --filter <name>
```
Expected: `tests/Golden/baselines/<name>.html` written (normalized Smarty output). Review it looks sane; commit it.

- [ ] **Step 2: Write the Twig golden test (failing)**

```php
public function testNameRendersLikeSmarty(): void
{
    $vars = require __DIR__ . '/fixtures/<name>.php';
    $renderer = new TwigRenderer();
    $html = $renderer->render('<Area>/<name>.twig', $vars);
    $this->assertMatchesBaseline('<name>', $html);
}
```

- [ ] **Step 3: Run → FAIL** (`.twig` does not exist yet)

Run: `composer phpunit -- --testsuite golden --filter testNameRendersLikeSmarty`
Expected: FAIL (template not found).

- [ ] **Step 4: Convert the template**

Run the chosen converter (Task 0.11) on `<Area>/<name>.tpl` → `<Area>/<name>.twig`, then hand-finish:
- `{$var}` → `{{ var }}`; `{$a->B()}` → `{{ a.B() }}`; `{$a.b}` → `{{ a.b }}`.
- `{if}/{elseif}/{else}/{/if}` → `{% if %}/{% elseif %}/{% else %}/{% endif %}`.
- `{foreach from=$xs item=x}` → `{% for x in xs %}` … `{% endfor %}` (map `{foreachelse}`→`{% else %}`).
- `{include file='x.tpl'}` → `{% include 'x.twig' %}` (include the `.twig`; the include chain migrates leaf-first).
- Modifiers: `|default:array()`→`|default([])`, `|sanitize_rich_text`, `|url2link`, `|nl2br` (Twig native), `|escape`/none→rely on autoescape, `|count`→`|length`, `|strtolower`→`|lower`, `|urlencode`→`|url_encode`.
- Plugins: `{translate key='K'}`→`{{ translate('K') }}`; `{control type="T" a=$b}`→`{{ control('T', {a: b}) }}`; buttons `{update_button ...}`→`{{ update_button(...) }}`; `{csrf_token}`→`{{ csrf_token() }}`; `{validator id=...}`→`{{ validator(...) }}`; `{validation_group}...{/validation_group}`→`{% set _vg %}...{% endset %}{{ validation_group(_vg) }}`.
- `Class::CONST` → `constant('Class::CONST')`.
- `$smarty.server.SCRIPT_NAME` → the registered global (add to `LibreBookingExtension` globals as needed); `$smarty.now`→`"now"|date`; `$smarty.const.X`→`constant('X')`.
- Escaping review: for each dynamic output confirm it is safe-by-function, correctly autoescaped, or deliberately `|raw` with a `{# reason #}` comment.

- [ ] **Step 5: Run → PASS** (iterate normalization/escaping until the Twig output matches the baseline).

Run: `composer phpunit -- --testsuite golden --filter testNameRendersLikeSmarty`
Expected: PASS. If a diff is a legitimate escaping improvement (not a regression), update the baseline with `UPDATE_GOLDEN=1` and note why in the commit.

- [ ] **Step 6: phpcsfixer + commit**

```bash
composer phpcsfixer:fix
git add tpl/<Area>/<name>.twig tests/Golden/
git commit -m "feat(templating): migrate <Area>/<name> template to Twig

Assisted-by: Claude:claude-opus-4-8
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 7 (per area, once): delete the `.tpl`** only after all templates that `{% include %}` it are on Twig AND no `.tpl` still references it. Deleting is Phase 5; during Phases 2–4 the `.tpl` stays as the Smarty fallback.

---

# PHASE 2 — Migrate pages by area (apply Task 1.10 recipe per template)

Order: leaf/simple areas first, Admin last (largest). **One PR per area.** Each area = apply the recipe to every `.tpl` below, add the area's `GoldenTest`, run `composer test` (phpunit + lint), manual spot-check on `http://localhost:80`, ship.

Per-area checklist (counts from inventory; enumerate exact files with `find tpl/<Area> -name '*.tpl'` at task start):

- [ ] **Task 2.1 — root leaf pages (14):** `dashboard.tpl`, `forgot_pwd.tpl`, `guest-participation.tpl`, `maintenance.tpl`, `register.tpl`, `support-and-credits.tpl`, `tos.tpl`, `json_data.tpl`, `globalheader/footer`+`javascript-includes` (done in Phase 1 — skip), remaining root `.tpl`. (`login.tpl`/`error.tpl`/`wait-box.tpl` done in Phase 1.)
- [ ] **Task 2.2 — Activation (2)**
- [ ] **Task 2.3 — ExternalAuth (1)**
- [ ] **Task 2.4 — MyAccount (4)**
- [ ] **Task 2.5 — Search (2)** and **SearchAvailability (2)**
- [ ] **Task 2.6 — Export (4)**
- [ ] **Task 2.7 — Credits (4)**
- [ ] **Task 2.8 — MonitorDisplay (2)** and **ResourceDisplay (5)**
- [ ] **Task 2.9 — Calendar (6)**
- [ ] **Task 2.10 — Schedule (7)**
- [ ] **Task 2.11 — Reports (8)**
- [ ] **Task 2.12 — Reservation (12)**
- [ ] **Task 2.13 — Dashboard (9)** (dashboard sub-templates; coordinate with Phase 3 controls used here)
- [ ] **Task 2.14 — Ajax (14)**
- [ ] **Task 2.15 — Admin (51)** — sub-split into one PR per subdir: `Admin/` root, `Configuration`, `Groups`, `Payments`, `Import`, `Resources`, `Reservations`, `Users`, `Attributes`, `Blackouts`, `Schedules`. Apply the recipe per file.

Each task's Definition of Done: all its `.twig` files pass golden tests; `composer test` green; `composer phpstan` + `phpstan_next` green; manual spot-check done; PR shipped. `.tpl` files remain (fallback) until Phase 5.

---

# PHASE 3 — Migrate Controls

**Files:** For each of the 6 `Controls/*.php` control classes with templates and the 9 `tpl/Controls/*.tpl`:
- Create `tpl/Controls/<name>.twig` via the recipe (Task 1.10).
- Verify the control renders via Twig through `renderControlTemplate` (Task 1.5 fallback already lets pages on either engine embed it).

- [ ] **Task 3.1:** Enumerate controls: `find Controls -name '*.php'` and `find tpl/Controls -name '*.tpl'`. For each control template, add a golden test that drives the control's `PageLoad()` with fixture inputs (mirror `tests/Infrastructure/Common/SmartyControlTest.php`) and compares Twig vs captured Smarty baseline.
- [ ] **Task 3.2–3.x:** One control (or small group) per commit, following the recipe. Include `AttributeControl`, `CaptchaControl`, `CheckboxControl`, `DatePickerSetupControl`, `RecurrenceControl`, and Dashboard controls. Verify the `-custom`/data-scope behavior for any control reading parent-page vars.

DoD: all control templates render via Twig; `SmartyControlTest` + new golden control tests green.

---

# PHASE 4 — Migrate Email templates + localized/-custom resolution

**Files:**
- Create: `tpl/Email/emailheader.twig`, `tpl/Email/emailfooter.twig`, and every `lang/<code>/*.tpl` email body used via `FetchLocalized`.
- Modify: `lib/Common/Templating/TwigRenderer.php` — implement full `fetchLocalized()` (localized dir + `-custom.twig` override + `en_us` fallback), mirroring `SmartyPage::FetchLocalized` but for `.twig` and with the `.tpl` fallback when no `.twig` exists.
- Modify: `lib/Email/EmailMessage.php` — `FetchTemplate` uses the renderer's `fetchLocalized`/`fetch`; keep `Translate()` working (route to the `translate` extension function or a renderer method).
- Test: extend `tests/Application/Reservation/ReservationEmailTemplateContextTest.php`; add golden tests for header/footer and a representative localized body incl. a `-custom` override case.

- [ ] **Task 4.1:** Implement `TwigRenderer::fetchLocalized` (TDD: test localized hit, `-custom` override hit, `en_us` fallback, `.tpl` fallback). Commit.
- [ ] **Task 4.2:** Migrate `emailheader`/`emailfooter` via recipe; golden tests. Commit.
- [ ] **Task 4.3+:** Migrate each localized email body template via recipe. Verify `enforceCustomTemplate` path. Commit per template/group.

DoD: emails render via Twig with localized + custom-template fidelity; email tests green.

---

# PHASE 5 — Remove Smarty

Only start when `find tpl lang -name '*.tpl' | wc -l` is 0 (every template migrated) and the whole suite is green on Twig.

- [ ] **Task 5.1: Delete `.tpl` files.** `find tpl lang -name '*.tpl' -delete`. Run `composer test` → green (everything now resolves to `.twig`). Commit `chore(templating): remove migrated .tpl templates`.
- [ ] **Task 5.2: Collapse renderer selection.** Remove `EngineSelector` and the Smarty branch from `Page::Display`, `EmailMessage`, `renderControlTemplate` fallback — always use Twig. Update tests. Commit `refactor(templating): make Twig the sole renderer`.
- [ ] **Task 5.3: Delete Smarty code.** Remove `lib/Common/SmartyPage.php`, `lib/Common/Templating/SmartyRenderer.php`, `lib/Common/SmartyControls/`, and their requires in `namespace.php`; remove `$this->smarty` BC aliases in `Page`/`EmailMessage`; delete `tests/fakes/FakeSmarty.php`, `SmartyPageTest.php`, `SmartyControlTest.php`, `SmartyRendererTest.php`. Move any still-needed helper logic (already extracted to `src/` in Task 1.3/1.4) — verify none remains only in `SmartyPage`. Commit `refactor(templating): delete SmartyPage and Smarty controls`.
- [ ] **Task 5.4: Drop the dependency.** `composer remove smarty/smarty`; delete `tpl_c/` compiled-Smarty handling; keep `tpl_c/twig/` in `.gitignore`. Update `composer.json`/`.lock`. Run `composer dump-autoload --strict-psr`, `composer test`. Commit `build(templating): remove smarty/smarty dependency`.
- [ ] **Task 5.5: Docs & config.** Update `CLAUDE.md` (Template Engine → Twig; template section; caching notes), `docs/source/DEVELOPER-README.rst`, preflight (`lib/preflight.php` if it checks Smarty), `.gitignore`, any `chmod tpl_c` docs. Update the `smarty-plugins-refactor` memory as superseded. Commit `docs(templating): document Twig as the template engine`.
- [ ] **Task 5.6: Final gate.** `composer test`, `composer phpstan`, `composer phpstan_next`, `composer preflight`, `npm run lint:frontend`, manual smoke on `http://localhost:80` across major pages. Commit any fixes.

---

# PHASE 6 — Optimization with `embed` (later, optional)

Deferred per design (faithful 1:1 first). With behavior locked by golden tests, refactor duplicated layout into Twig `{% extends %}`/`{% block %}`/`{% embed %}` and macros. Each refactor keeps golden tests green (they assert output is unchanged). Out of scope for the initial migration; track as a follow-up.

---

## Self-Review

**Spec coverage:**
- Coexistence interface (Approach A) → Tasks 0.2–0.9. ✓
- Plugin/extension mapping → 1.1–1.4. ✓
- `control` mechanism + fallback → 0.8, 1.5. ✓
- Escaping/autoescape policy → 0.4 (autoescape on), 1.2/1.4 (safe funcs), recipe Step 4 (per-template review). ✓
- Converter evaluation → 0.11. ✓
- Golden-test harness (baseline from Smarty, structural normalization) → 0.10, recipe 1.10. ✓
- Localized/`-custom` fidelity → 4.1. ✓
- Sequencing/phases incl. email + Install, faithful-first → Phases 2–4 (Install = Task 2.x under Admin/Install; add explicit `tpl/Install` (3) as Task 2.16 — see note), embed deferred → Phase 6. ✓
- Smarty removal → Phase 5. ✓
- Manual verification via Docker `localhost:80` → 1.9, Phase 2 DoD, 5.6. ✓

**Gap fixed inline:** Install (3 templates) was not enumerated in Phase 2. Add:
- [ ] **Task 2.16 — Install (3):** apply the recipe to `tpl/Install/*.tpl`.

**Placeholder scan:** No "TBD"/"handle edge cases"/"similar to Task N" — the recipe is concrete and each phase references it explicitly with real commands. ✓

**Type consistency:** `TemplateRenderer` method names (`render`, `fetch`, `fetchLocalized`, `renderControlTemplate`, `validators`, `isValid`) are used consistently across `SmartyRenderer`, `TwigRenderer`, `Page`, `EmailMessage`, `Control`. `LibreBookingExtension` function/filter names match the recipe's conversion table. ✓
