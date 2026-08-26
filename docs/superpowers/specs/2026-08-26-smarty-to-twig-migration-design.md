# Smarty → Twig Migration — Design

- **Date:** 2026-08-26
- **Status:** Approved (design); implementation plan pending
- **Branch:** `refactor/twig` (currently identical to `develop`)

## Motivation

Move LibreBooking's templating from Smarty (`smarty/smarty ^5.8`) to Twig. Drivers,
in the user's words:

1. **Smarty maintenance risk** — prefer a more actively maintained, standard engine.
2. **Security / autoescaping** — adopt Twig's autoescape-by-default to reduce XSS risk
   versus Smarty's current manual escaping.
3. **Cleanup / modernization** — reduce custom template glue; aligns with the
   ongoing PSR-4 modernization.
4. **Twig `embed`** — the user specifically wants Twig's `embed` for template
   composition (applied as a *later* optimization pass, not during the initial port).

## Current State (as explored)

- `lib/Common/SmartyPage.php` extends `Smarty\Smarty` and registers:
  - **~50 plugins**: `function` (e.g. `translate`, `control`, `validator`,
    `textbox`, `datatable`, `csrf_token`, all `*_button`, `formatdate`,
    `indicator`, `sort_column`, `resource_image`, `jsfile`/`cssfile`/`vendor_js`/`vendor_css`, …),
    `modifier` (`sanitize_rich_text`, `url2link`, `escapequotes`, `urlencode`,
    `intval`, `strtolower`, `count`, `array_key_exists`, `html_entity_decode`,
    `microtime`), and one `block` (`validation_group`).
  - **~40 registered classes** templates reference as `Class::MEMBER`
    (`ConfigKeys`, `Actions`, `CustomAttributeTypes`, `QueryStringKeys`, …).
- **161 `.tpl` files** across ~30 directories under `tpl/`.
- Usage density (files touching each construct): `translate` 129, `{if` 107,
  `foreach` 94, `include` 76, `control` 64, `csrf_token` 41, `formatdate` 34,
  `datatable` 24, `validator` 15.
- **Three render entry points**: `Pages/Page.php` (base page), `lib/Email/EmailMessage.php`
  (email, via `fetch()`), and presenters (e.g. `Presenters/DashboardPresenter.php`).
- **`control` mechanism**: `SmartyPage.DisplayControl()` instantiates `Controls/X.php`
  with the `SmartyPage`, sets params, calls `PageLoad()`; `Control::Display()` renders
  sub-templates via Smarty `createData()`/`createTemplate()`.
- **Overrides**: localized-template fallback (`lang/<code>/…`, falling back to `en_us`)
  and `-custom.tpl` override mechanism (`FetchLocalized`).
- Templates use Smarty specials (`$smarty.server.SCRIPT_NAME`), the
  `|default:array()` idiom, method calls (`$each->Text()`), and modifier chains
  (`sanitize_rich_text|url2link|nl2br`).
- Existing tests: `tests/Infrastructure/Common/SmartyPageTest.php`,
  `SmartyControlTest.php`, and `tests/fakes/FakeSmarty.php`.

## Strategy

**Incremental coexistence.** Smarty and Twig run side by side behind a common
rendering interface; templates migrate file-by-file. Every PR is shippable and the
test suite stays green throughout. Smarty is deleted only after the last `.tpl` is gone.

### Coexistence mechanism (Approach A)

Introduce a `TemplateRenderer` interface:

```
interface TemplateRenderer {
    public function assign(string $name, mixed $value): void;
    public function render(string $templateName, array $vars = []): string;
    public function fetch(string $templateName): string; // email/back-compat
}
```

- `SmartyRenderer` wraps today's `SmartyPage` (behavior unchanged).
- `TwigRenderer` wraps a configured Twig `Environment`.
- `Page`, `EmailMessage`, presenters, and `Control` depend on the **interface**,
  not on `SmartyPage` directly.
- Engine selection is **per template by file existence**: if a `.twig` file exists
  for the requested template it renders via Twig, else Smarty renders the `.tpl`.
  This removes ordering constraints between pages and the controls/includes they embed.

Rejected alternatives: **B** (Twig-first fallback inside `SmartyPage` — conflates
engines, awkward for email/controls); **C** (parallel `tpl_twig/` tree swapped at the
end — effectively a big-bang at cutover).

## Design Detail

### 1. Plugins, filters & registered-class access

- **HTML-emitting functions → `LibreBookingExtension` Twig functions marked
  `is_safe: ['html']`.** Logic is lifted from `SmartyPage` essentially verbatim; only
  the call convention changes (Smarty `($params, $smarty)` array → named Twig args).
  Covers: `translate`, `control`, `validator`, `async_validator`, `validation_group`,
  `textbox`, `object_html_options`, `html_link`, `setfocus`, `formname`, `js_array`,
  `fullname`, `resource_image`, `csrf_token`, `indicator`, `read_only_attribute`,
  all `*_button`, `showhide_icon`, `sort_column`, `datatable`, `datatablefilter`,
  `formatcurrency`, `formatdate`/`format_date`, `add_querystring`, `jsfile`, `cssfile`,
  `vendor_js`, `vendor_css`, `flush`, `linebreak`.
- **Modifiers → Twig filters.** Custom (keep + safe-html where relevant):
  `sanitize_rich_text`, `url2link`, `escapequotes`, `html_entity_decode`. Map to
  **native** Twig where equivalent: `urlencode`→`url_encode`, `strtolower`→`lower`,
  `count`→`length`, `intval`→small `int` filter, `array_key_exists`→native attribute
  access. The converter rewrites these names.
- **Registered classes → a `constant()` Twig function.** Twig has no `Class::CONST`
  syntax. `{if $x == CustomAttributeTypes::CHECKBOX}` →
  `{% if x == constant('CustomAttributeTypes::CHECKBOX') %}`, backed by PHP `constant()`.
  The converter rewrites `Class::MEMBER` occurrences. Classes used as objects with
  method calls are passed as Twig globals instead.
- **Smarty specials** (`$smarty.server.*`, `$smarty.const.*`, `$smarty.now`,
  `$smarty.section`) map to Twig globals / `constant()` / native loop variables via the
  converter and a few registered globals.

Guiding rule: **lift PHP logic out of `SmartyPage` into `LibreBookingExtension`
unchanged** so behavior matches and golden tests pass; only the call convention changes.

### 2. The `control` mechanism

- `Control`'s constructor takes `TemplateRenderer` (not `SmartyPage`).
  `Control::Display($name)` delegates to `$renderer->render($name, $this->data)`.
- The `control` Twig function instantiates the control with the **Twig** renderer,
  sets params from named args, calls `PageLoad()`, returns buffered output (safe-html).
- Per-control engine fallback: `Controls/Foo.twig` if present, else `Foo.tpl` via Smarty
  — so controls migrate independently of embedding pages (critical: `control` is in 64 files).
- Smarty's `createData()` isolation maps to Twig's per-`render()` context array. Verify
  the handful of controls that read parent-page vars.

### 3. Escaping & security

- Twig runs with **`autoescape: 'html'` globally**.
- HTML-emitting functions marked `is_safe: ['html']` are the **only** sanctioned raw
  path; avoid `|raw` in templates so the safe surface is auditable in one file.
- Plain variables become autoescaped (usually strictly more correct). Double-escape or
  intended-HTML cases surface as golden-test diffs and are resolved deliberately (drop
  an upstream escape, or a justified `|raw` with a comment).
- `sanitize_rich_text` stays **display-only** per CLAUDE.md rich-text rules;
  `sanitize_rich_text|url2link|nl2br` chains carry over as Twig filters. No
  sanitize-on-write, no stored-content migration.
- `translate` output stays safe-html (developer-controlled strings, some contain markup).
- **Per-PR escaping review** folded into every migration PR: for each template, confirm
  each dynamic output is safe-by-function, correctly autoescaped, or deliberately `|raw`
  with justification.

### 4. Converter evaluation & golden-test harness

**Converter (first-pass only).** As the plan's first task, evaluate candidate
open-source Smarty→Twig converters against a representative sample (`login.tpl`, an Admin
page, a heavy `control`/`datatable` page, an email template). Score on variable/`{if}`/
`{foreach}`/`{include}` handling, method-call preservation (`$x->Foo()`), filter
rewriting, and graceful handling of unknown plugins. Recommend one (or a repo-specific
script) in a short written finding. Tool output is **always** followed by manual fix-up
(plugins, `control`, `constant()`, Smarty specials) and must pass golden tests before merge.

**Golden-test harness.**
1. One-time **baseline capture**: render each `.tpl` under Smarty with committed fixture
   data; save normalized output as golden files under `tests/golden/<template>.html`.
2. After conversion, a PHPUnit test renders the `.twig` with the same fixtures and asserts
   it matches the golden file **after normalization**.
3. **Normalization** = *structural equivalence*: collapse insignificant whitespace and
   canonicalize HTML-entity escaping form (`&#039;` vs `&#39;`), while still catching
   structural changes (missing/extra elements, wrong attributes, meaningful escape changes).
   Not byte-identical.
4. Fixtures reuse existing fakes (`FakeSmarty`, `SmartyPageTest`/`SmartyControlTest`
   patterns) and cover the branches each template exercises.

Decisions: goldens captured from **current Smarty output** as source of truth;
normalization targets **structural equivalence**, not byte-identical.

**Manual verification.** The dev server runs in Docker watching the `app/` folder,
so edits to templates/PHP reflect live in the running container (served at
`http://localhost:80`). Use it for per-page visual spot-checks during each area's
migration, in addition to (not instead of) the golden tests.

### 5. Sequencing & end state

**Faithful 1:1 conversion first; `embed`/optimization is a later pass.** All templates
in scope, including **email** and **Install**.

- **Phase 0 — Foundation (no behavior change; still all Smarty).** Add `twig/twig`.
  Introduce `TemplateRenderer`, `SmartyRenderer`, skeleton `TwigRenderer` + empty
  `LibreBookingExtension`. Rewire `Page`, `EmailMessage`, presenters, `Control` to the
  interface (defaulting to Smarty). Stand up golden-test harness + capture baselines.
  Evaluate/recommend converter. Ship.
- **Phase 1 — Twig proven end-to-end on leaves.** Implement full `LibreBookingExtension`
  (functions, filters, `constant()`, globals), Twig `control` function, autoescape config.
  Migrate `error.tpl`, `wait-box.tpl`, `login.tpl` + shared includes
  (`globalheader`/`globalfooter`/`javascript-includes`). Golden tests prove parity.
- **Phase 2 — Pages by area, one PR per area**, roughly leaf→root:
  Auth/Activation → MyAccount → Reservation → Schedule/Calendar → Reports →
  Admin (sub-split; largest) → Search/Export/Monitor/ResourceDisplay → Install.
  Each area: convert (tool + hand fix-up), escaping review, golden tests, ship.
  **No `embed` restructuring yet — faithful 1:1.**
- **Phase 3 — Controls.** Migrate `Controls/*.php` templates to `.twig` (interleavable
  with Phase 2 via the fallback).
- **Phase 4 — Email.** Migrate `Email/*.tpl` through `EmailMessage`.
- **Phase 5 — Remove Smarty.** Delete `SmartyPage`, `SmartyRenderer`, `SmartyControls`,
  `smarty/smarty` dependency, `tpl_c`. Collapse `TwigRenderer` as sole renderer. Update
  `CLAUDE.md`, docs, `.gitignore` (twig cache dir), preflight/permissions.
- **Phase 6 (later, optional) — Optimization.** Introduce `embed`, macros, and dedup
  now that behavior is locked by golden tests.

**End state:** one engine (Twig, autoescaped); custom logic centralized in
`LibreBookingExtension`; `-custom.twig` and localized-fallback overrides preserved in
`TwigRenderer`'s loader; golden tests retained as ongoing regression coverage.

## Non-Goals

- No `embed`/macro restructuring during the initial port (deferred to Phase 6).
- No rich-text sanitize-on-write or stored-content migration.
- No functional/behavioral changes to pages beyond escaping corrections surfaced by
  golden tests.
- No changes to the REST API or non-template subsystems.

## Risks & Mitigations

- **Autoescape regressions (double-escape / newly-escaped intended HTML).** Mitigated by
  golden tests + per-PR escaping review; raw surface confined to the extension.
- **`control` interactions across engines.** Mitigated by the renderer interface +
  per-control fallback; verify controls reading parent-page vars.
- **Converter output quality.** Treated as first draft only; golden tests gate merges.
- **Long coexistence window.** Accepted trade-off for shippable, reviewable PRs; two
  engines in `composer.json` until Phase 5.
- **Localized/`-custom` override fidelity.** Ported explicitly into `TwigRenderer`'s
  loader and covered by tests.

## Open Questions

None blocking. Converter choice is resolved during Phase 0.
