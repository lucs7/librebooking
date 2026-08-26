# Smarty → Twig Converter Evaluation

**Date:** 2026-08-26  
**Author:** Claude:claude-sonnet-4-6 (Task 0.11)  
**Status:** Decision recorded — no templates modified

---

## 1. Representative Template Sample

Five templates were read in full and characterised below. Total template count: **161 `.tpl` files**.

### 1.1 `tpl/login.tpl`

**Constructs found:**

| Construct | Example |
|-----------|---------|
| Variable output | `{$Path}`, `{$LogoUrl}`, `{$Version}`, `{$ResumeUrl}` |
| `{if}` / `{else}` / `{/if}` | `{if $EnableCaptcha}…{/if}` |
| `{foreach from=… item=…}` | `{foreach from=$Announcements item=each}` |
| `{include file=…}` | `{include file='globalheader.tpl'}`, `{include file='globalfooter.tpl'}` |
| Custom functions (HTML-emitting) | `{control type="CaptchaControl"}`, `{formname key=EMAIL}`, `{setfocus key='EMAIL'}`, `{validation_group}`, `{validator}` |
| `translate` function | `{translate key='LogIn'}`, `{translate key=RememberMe}` |
| Modifier chain | `{$smarty.cookies.language\|escape:"javascript"}` |
| `$smarty.server` | `{$smarty.server.SCRIPT_NAME}` |
| `$smarty.cookies` | `{$smarty.cookies.language}` |
| Class constant | `{QueryStringKeys::LANGUAGE}` (inside JS string) |
| `{object_html_options}` built-in | `{object_html_options options=$Languages key='GetLanguageCode' label='GetDisplayName' selected=$SelectedLanguage}` |

**Difficulty:** Medium — no method calls, but dense custom-plugin usage, `$smarty.*` superglobals, and a Class::CONST in an inline JS block.

---

### 1.2 `tpl/Admin/Resources/manage_resources.tpl` (Admin heavy page, ~600+ lines)

**Constructs found:**

| Construct | Example |
|-----------|---------|
| Variable output + method calls | `{$resource->GetResourceId()}`, `{$resource->GetName()}`, `{$resource->GetColor()}`, `{$resource->GetImages()\|count}` |
| `{assign var=…}` | `{assign var=tableId value=resourcesTable}`, `{assign var=id value=$resource->GetResourceId()}` |
| `{foreach}` / nested `{foreach}` | `{foreach from=$Resources item=resource}`, `{foreach from=$resource->GetImages() item=image}` |
| `{if}` with method-call conditions | `{if $resource->HasImage()}`, `{if !empty($Resources)}` |
| Custom functions | `{control type="AttributeControl" …}`, `{filter_button id="filter"}`, `{reset_button id="clearFilter"}`, `{translate …}`, `{formname …}`, `{datatable …}`, `{object_html_options …}`, `{html_options …}` |
| Class constants | `{ResourceStatus::AVAILABLE}`, `{ResourceStatus::UNAVAILABLE}`, `{ResourceStatus::HIDDEN}` |
| `{include file=…}` with `InlineEdit=true` params | `{include file='globalheader.tpl' InlineEdit=true DataTable=true Trumbowyg=true}` |
| Modifier on method | `{$resource->GetImages()\|count}` |
| Custom `resource_image` function | `{resource_image image=$resource->GetImage()}` |

**Difficulty:** Hard — dense method calls on domain objects, Class::CONST literals, multi-arg custom functions with named params, nested foreach with method-call iterators.

---

### 1.3 `tpl/Reservation/create.tpl` (Largest, ~900 lines)

**Constructs found:**

| Construct | Example |
|-----------|---------|
| `{block name=…}` / `{/block}` | `{block name="header"}…{/block}` (Smarty template inheritance) |
| `{extends file=…}` (via `edit.tpl`) | Reservation views use `{block}` inheritance |
| `{function name=…}` + `{call name=…}` | `{function name="displayResource"}…{/function}` |
| Method calls in conditions | `{if !$resource->GetColor()}`, `{if $resource->GetRequiresApproval()}` |
| Method calls as attribute values | `data-resourceId="{$resource->GetId()}"` |
| `{textbox name=… value=… …}` | `{textbox name="RESERVATION_TITLE" class="…" value="ReservationTitle" id="…" maxlength="300" required=$TitleRequired}` |
| `{control type="RecurrenceControl" …}` | Custom multi-arg control |
| `{assign var=…}` with ternary-style | `{assign var="detailsCol" value="col-12"}` |
| `{foreach from=$StartPeriods item=period}` with nested assigns | Complex foreach with assignment tracking |
| `{formatdate date=$StartDate}` | Custom date filter |

**Difficulty:** Very hard — template inheritance (`{block}` / `{extends}`), named template functions (`{function}` / `{call}`), high density of `->` method calls both in output and conditions, `{textbox}` and other multi-argument HTML-emitting custom tags.

---

### 1.4 `tpl/Email/emailheader.tpl`

**Constructs found:**

| Construct | Example |
|-----------|---------|
| Variable output | `{$Charset}` |
| No control structures | Plain HTML wrapper |
| No custom plugins | Email templates use a separate simpler Smarty instance |

**Difficulty:** Very easy — almost plain HTML with a couple of variables. Email templates (`tpl/Email/`) are a separate rendering path (`SmartyEmail.php`) with fewer plugins.

---

### 1.5 `tpl/Controls/DatePickerSetup.tpl`

**Constructs found:**

| Construct | Example |
|-----------|---------|
| Variable output | `{$ControlId}`, `{$DateFormat}`, `{$MinDate}`, `{$MaxDate}` |
| Modifier | `{$Inline\|json_encode}`, `{$NumberOfMonths\|default:1}` |
| Inline ternary (Smarty shorthand) | `{$Multiple ? '"multiple"' : '"single"'}` |
| `{if … \|default:…}` | `{if $AltInput\|default:true}true{else}false{/if}` |
| `{nofilter}` flag | `{$AltFormatJson nofilter}`, `{$DefaultDateJson nofilter}` |
| Arithmetic in output | `{$FirstDay >= 0 && $FirstDay <= 6}` inside `{if}` |

**Difficulty:** Medium — mostly a JS block with Smarty interpolation. The `nofilter` flag (raw output), inline ternary (`? :`), and `|default:` on `{if}` conditions need special attention.

---

## 2. Custom Plugin Inventory (from `SmartyPage.php`)

47 plugins are registered. Key categories:

| Category | Plugins |
|----------|---------|
| **Functions (HTML-emitting)** | `control`, `datatable`, `datatablefilter`, `validator`, `validation_group` (block), `textbox`, `formname`, `setfocus`, `object_html_options`, `html_link`, `js_array`, `async_validator`, `fullname`, `add_querystring`, `resource_image`, `indicator`, `csrf_token`, `flush`, `jsfile`, `cssfile`, `formatdate`, `format_date`, `formatcurrency`, `linebreak` |
| **Buttons** | `cancel_button`, `update_button`, `add_button`, `delete_button`, `reset_button`, `filter_button`, `ok_button` |
| **Modifiers** | `url2link`, `escapequotes`, `sanitize_rich_text`, `urlencode`, `html_entity_decode`, `intval`, `strtolower`, `microtime`, `array_key_exists`, `count` |
| **Icons** | `showhide_icon`, `sort_column` |

Additional `$smarty.*` superglobals in use:

- `$smarty.server.SCRIPT_NAME`, `$smarty.server.HTTP_HOST`, `$smarty.server.REQUEST_URI`
- `$smarty.cookies.language`
- `$smarty.foreach.<name>.last`, `$smarty.foreach.<name>.index` (foreach metadata)
- `$smarty.capture.<name>` (capture blocks)

---

## 3. Candidate Converter Evaluation

### 3.1 `OXID-eSales/smarty-to-twig-converter`

- **Language / how it runs:** PHP CLI (`php toTwig convert --path=…`), installable via Composer (MIT)
- **Last activity:** 2026-05-01 (actively maintained; recent fix for quote-aware attribute parsing)
- **License:** MIT
- **Constructs handled:**
  - Core: `{assign}` → `{% set %}`, `{if}`/`{elseif}`/`{else}`/`{/if}`, `{foreach}`, `{include}`, `{block}`, `{function}` (`{defun}`), `{capture}`, comments `{* *}` → `{# #}`, `{ldelim}`/`{rdelim}`/`{literal}`/`{strip}`
  - Variable conversion: strips `$`, maps `->` to `.`
  - Filter name mapping: `count → length`, `strip_tags → striptags`, `sprintf → format`, etc. (via `FilterNameMap`)
  - `{math}`, `{counter}`, `{cycle}`, `{mailto}`, `{insert}` (niche tags)

- **Critical gap — delimiter mismatch:** The tool's tag patterns use `\[\{…\}\]` (OXID's custom Smarty delimiters), **not** standard `{…}`. Every regex in `getOpeningTagPattern()` is hardcoded to match `[{tagname…}]`. LibreBooking uses standard `{…}` delimiters. The tool **will not match any of our templates without forking and replacing all delimiter regexes**.

- **Unknown custom plugins:** No fallback mechanism. Tags that do not have a dedicated converter class are simply left unchanged in the output. A `{control type="Foo"}` call passes through unmodified — neither erroring nor emitting a placeholder. This is the best possible behaviour for a first-pass tool.

- **Score (hypothetical, if delimiter issue were fixed):**
  | Axis | Score (0–3) | Notes |
  |------|-------------|-------|
  | Variable output `{$var}` → `{{ var }}` | 3 | Handles `->` → `.`, strips `$`, converts filters |
  | `{if}`/`{foreach}`/`{include}` | 3 | Comprehensive, quote-aware since 2026-04 |
  | Method-call preservation `$x->Foo()` | 2 | `->` → `.` and strips `$`; parentheses kept; output is `x.Foo()` which is valid Twig |
  | Modifier/filter rewriting | 2 | Name map covers Smarty builtins; repo-specific modifiers (`sanitize_rich_text`, `url2link`) pass through with original names |
  | Unknown plugin handling | 3 | Passes through unmodified — safe for first pass |

  **Overall: strong on standard constructs but requires delimiter fork before use.**

- **Score as-is (no delimiter patch):** 0 — would produce no conversions at all.

---

### 3.2 `jusurb/to-twig` (and `victormacko/to-twig` fork)

- **Last activity:** 2016-08-29 (archived, read-only)
- **License:** MIT
- **Constructs handled:** `{include}`, `{assign}`, `{$var}`, comments, `{ldelim}`/`{rdelim}`/`{literal}`, `{if}`, `{foreach}` — no block/function/filter support
- **Delimiter:** Uses standard `{…}` — no delimiter mismatch
- **Unknown plugin handling:** Passes through unmodified
- **Score:**
  | Axis | Score | Notes |
  |------|-------|-------|
  | Variable output | 2 | Basic `$var` → `var`; limited modifier support |
  | `{if}`/`{foreach}`/`{include}` | 2 | Handles core tags; no `{block}`, `{extends}`, `{function}` |
  | Method-call preservation | 1 | `->` is not mapped; `{$x->Foo()}` would be corrupted or left as-is |
  | Modifier/filter rewriting | 0 | No filter name mapping |
  | Unknown plugin handling | 3 | Passes through unmodified |

  **Verdict: too stale and too narrow for this codebase. 8-year-old unmaintained; misses block inheritance and method calls.**

---

### 3.3 `vytsci/smarty-to-twig-bundle` (Symfony bundle)

- **Last activity:** 2019-04-13
- **Scope:** Symfony-specific; wraps `jusurb/to-twig` with a console command
- **Verdict:** Same limitations as `jusurb/to-twig` plus Symfony coupling we don't need. Rejected.

---

### 3.4 Tool execution in sandbox

**The OXID tool could not be run on sample templates in this environment** because:
1. Composer network access is unreliable in the CI/sandbox (known issue in `CLAUDE.md`).
2. Even if installed, the `[{…}]` delimiter mismatch would produce no output.

Scores above for OXID are based on full source-code review via GitHub API. All converter source files (`ConverterAbstract.php`, `VariableConverter.php`, `IfConverter.php`, `IncludeConverter.php`, `FilterNameMap.php`) were read and analysed directly.

---

## 4. Recommendation

### Decision: Write a small repo-specific PHP conversion script

No existing tool is usable out of the box:

- The **OXID tool** is the best-maintained and most capable converter, but it targets OXID's `[{…}]` delimiters, not standard `{…}`. Forking it to patch all 36+ converter classes is more work than writing a focused script for what LibreBooking actually uses.
- The **archived tools** (`jusurb`, `victormacko`, `vytsci`) are stale, miss method calls and block inheritance, and have not been updated in 7–10 years.

### Scope of the script

The script should handle **only the mechanical, safe rewrites**. Everything else is left for hand-finishing, gated by the golden tests from Task 0.10.

| Transform | Rule |
|-----------|------|
| `{$var}` → `{{ var }}` | Strip `$`, wrap in `{{ … }}` |
| `{$var\|modifier}` → `{{ var\|modifier }}` | Strip `$`, keep pipe; remap names (see below) |
| `{$obj->Prop}` → `{{ obj.Prop }}` | Strip `$`, `->` → `.` |
| `{$obj->Method()}` → `{{ obj.Method() }}` | Same; parens preserved |
| `{if cond}` → `{% if cond %}` | Map `&&`→`and`, `\|\|`→`or`, strip `$` from vars |
| `{elseif cond}` → `{% elseif cond %}` | Same |
| `{else}` → `{% else %}` | Literal swap |
| `{/if}` → `{% endif %}` | Literal swap |
| `{foreach from=$x item=y}` → `{% for y in x %}` | Extract `from`/`item` attrs |
| `{/foreach}` → `{% endfor %}` | Literal swap |
| `{foreachelse}` → `{% else %}` (inside `for`) | Literal swap |
| `{assign var=x value=$y}` → `{% set x = y %}` | Named-attr extraction |
| `{include file='foo.tpl'}` → `{% include 'foo.html.twig' %}` | Extension rewrite |
| `{block name=x}` → `{% block x %}` | Named-attr extraction |
| `{/block}` → `{% endblock %}` | Literal swap |
| `{extends file='…'}` → `{% extends '…' %}` | Attr extraction + ext rewrite |
| `{* comment *}` → `{# comment #}` | Delimiters only |
| `{literal}…{/literal}` → `{% verbatim %}…{% endverbatim %}` | Tag swap |
| `Class::CONST` (bare inside `{…}`) → `constant('Class::CONST')` | Regex for `[A-Z][A-Za-z]+::[A-Z_]+` |
| `$smarty.server.X` → `global.server.X` (or configured global) | Regex + global var |
| `$smarty.cookies.X` → `global.cookies.X` | Same |
| `$smarty.foreach.NAME.last` → `loop.last` (inside for) | Context-aware but flag for hand review |
| `$smarty.capture.NAME` → captured via `{% set %}` block | Flag for hand review |

**Modifier name remaps** (Smarty → Twig):

| Smarty | Twig |
|--------|------|
| `\|count` | `\|length` |
| `\|escape:'html'` | `\|e` |
| `\|escape:'javascript'` | `\|e('js')` |
| `\|lower` | `\|lower` (same) |
| `\|upper` | `\|upper` (same) |
| `\|nl2br` | `\|nl2br` (register as filter) |
| `\|default:val` | `\|default(val)` |
| `\|json_encode` | `\|json_encode` (register as filter) |
| `\|truncate:N:"…"` | `\|slice(0,N)` (approximate; flag) |

**Constructs explicitly out of scope (leave for hand-finishing):**

- `{control …}`, `{translate …}`, `{formname …}`, `{textbox …}`, `{datatable …}`, all button functions, `{object_html_options …}`, `{html_options …}`, `{resource_image …}`, `{validator …}`, `{validation_group}`, `{setfocus …}` — these require knowledge of LibreBookingExtension signatures and are fast to hand-convert.
- `{function name=…}` / `{call name=…}` — Twig `macro` equivalent; needs per-template logic.
- `{capture}` / `$smarty.capture.*` — Twig `{% set %}` block; context-sensitive.
- `{nofilter}` flag on variable — `{{ var\|raw }}`.
- Inline ternary `{$x ? 'a' : 'b'}` — `{{ x ? 'a' : 'b' }}` (works in Twig but needs verification).
- `{foreach}` with `name=` attribute and `$smarty.foreach.*` metadata.

### Implementation guidance

The script should be ~200–300 lines of PHP (or a series of well-ordered `sed` / `perl` invocations run in a fixed sequence). Sequence matters: process multi-character constructs before single-character ones to avoid double-substitution. The script lives in `tools/tpl-to-twig.php` (not yet created — this note is decision-only).

### Phase 1 exception

The five Phase 1 templates (`login.tpl`, `error.tpl`, `wait-box.tpl`, and their shared includes `globalheader.tpl`, `globalfooter.tpl`, `javascript-includes.tpl`) should be **hand-converted directly** regardless of what tooling is chosen. They are small, well-understood, and the hand conversion doubles as a test of the LibreBookingExtension function signatures before any bulk script is written.

### Quality gate

Whatever is used — script or hand conversion — **every converted template is gated by the golden tests from Task 0.10** before it is considered done. The converter output is always a starting draft, never a finished artefact.

---

## 5. Summary Table

| Tool | Delimiter | Active | Score | Usable as-is |
|------|-----------|--------|-------|--------------|
| OXID `smarty-to-twig-converter` | `[{…}]` — MISMATCH | Yes (2026-05) | High if forked | No (needs delimiter fork) |
| `jusurb/to-twig` | `{…}` — correct | No (archived 2016) | Low | No (too narrow) |
| `victormacko/to-twig` | `{…}` — correct | No (2017) | Low | No |
| `vytsci/smarty-to-twig-bundle` | `{…}` — correct | No (2019) | Low | No |
| **Repo-specific script (recommended)** | `{…}` — correct | N/A — write it | Targeted | **Yes** |
