# Task 2.17 Controls Templates Twig Migration Report

## Files Created

### Twig Templates (new, alongside existing .tpl files)
- `tpl/Controls/Checkbox.twig`
- `tpl/Controls/DatePickerSetup.twig`
- `tpl/Controls/DateSetup.twig`
- `tpl/Controls/RecurrenceDiv.twig`
- `tpl/Controls/Attributes/Checkbox.twig`
- `tpl/Controls/Attributes/Date.twig`
- `tpl/Controls/Attributes/MultiLineTextbox.twig`
- `tpl/Controls/Attributes/SelectList.twig`
- `tpl/Controls/Attributes/SingleLineTextbox.twig`

### Test Files (new)
- `tests/Golden/ControlsGoldenTest.php`

### Modified Files
- `tests/Infrastructure/Common/ControlTwigFunctionTest.php` — updated `testControlFunctionCheckboxControlFallsBackToTpl` to use normalized parity (Checkbox.twig now exists)
- `tests/Infrastructure/Common/RenderPartialTwigFunctionTest.php` — updated `testRenderPartialFallsBackToSmartyWhenNoTwigExists` and `testRenderPartialDoesNotUseTwigWhenOnlyTplExists` comments/assertions to reflect Checkbox.twig existence
- `phpunit.xml.dist` — added `golden` testsuite entry

## How Fixture Vars Were Determined

### Controls/Checkbox.twig
From `CheckboxControl::PageLoad()`: sets `name` (via FormKeys::Evaluate), `label` (via Resources::GetString), plus `id` and `class` passed by caller. Fixtures use simple alphanumeric values.

### Controls/DatePickerSetup.twig
From `DatePickerSetupControl::PageLoad()`: sets many vars (ControlId, Inline, Multiple, AltInput, AltFormatJson, DateFormat, DefaultDateJson, MinDate, MaxDate, HasTimepicker, Time24Hr, OnSelect, OnClose, FirstDay, DayNamesShort, DayNames, MonthNames, MonthNamesShort, NumberOfMonths, ShowWeekNumbers). Fixtures use fixed deterministic values (static day/month name arrays, fixed JSON strings) rather than invoking the full Control::PageLoad, avoiding Resources/Configuration dependency.

### Controls/DateSetup.twig
From `DatePickerSetupControl::PageLoad()` (used when AltId is set): ControlId, AltId, MinDate, MaxDate, DefaultDate as Date objects. Fixtures use `Date::Parse()` for date objects.

### Controls/RecurrenceDiv.twig
From `RecurrenceControl::PageLoad()`: RepeatEveryOptions (range 1-20), RepeatOptions (associative), optional `prefix`. Two branches: with and without prefix.

### Controls/Attributes/*.twig
From `AttributeControl::PageLoad()`: passes `attribute` (LBAttribute object), `attributeId`, `attributeName`, `class`, `readonly`, `searchmode`, `tooltip`. Real `CustomAttribute` and `LBAttribute` objects are constructed directly in fixtures (label, type, required, possibleValues). All three states (editable, readonly, searchmode) covered for each template.

## New Twig Constructs Added

### `{# comment #}` — Inline Twig comment
**Lesson learned**: In Twig, a comment at the end of a template line (like `{{ var }}{# comment #}\n`) causes the following newline to be consumed, eliminating whitespace before the next closing tag. This was discovered when `Controls/Checkbox.twig` produced `Send Reminder</label>` instead of `Send Reminder </label>` (as Smarty did). **Fix**: Remove the inline comment on that line so the newline is preserved: `{{ label|raw }}\n</label>`.

### `|raw` filter
Used for:
- `{{ label|raw }}` in Controls/Checkbox.twig — label is a Resources string (plain text, developer-controlled)
- `{{ AltFormatJson|raw }}` — JSON-encoded by PHP via `JsonEncodeForInlineScript()` (HEX-safe)
- `{{ DefaultDateJson|raw }}` — same
- `{{ DayNamesShort|raw }}`, `{{ DayNames|raw }}`, `{{ MonthNames|raw }}`, `{{ MonthNamesShort|raw }}` — JS array literals built by PHP control
- `{{ OnSelect|raw }}`, `{{ OnClose|raw }}` — JS callback strings (developer-controlled via PHP)

### `{{ constant('Class::CONST') }}`
Used in RecurrenceDiv.twig for `RepeatMonthlyType::DayOfMonth` and `RepeatMonthlyType::DayOfWeek` (replacing Smarty `{RepeatMonthlyType::DayOfMonth}`).

### `{% if prefix is defined %}` 
Used in RecurrenceDiv.twig to check for optional `prefix` variable (replacing Smarty `{if isset($prefix)}`).

### `{% for k, v in RepeatOptions %}` 
Key-value loop replacing Smarty `{foreach from=$RepeatOptions key=k item=v}`.

### `{{ attribute.Method() }}`
Method calls on attribute objects (replacing Smarty's `{$attribute->Method()}`).

### `{% set attributeValue = attribute.Value() %}`
Used in Attributes/Date.twig to capture attribute value once (replacing Smarty `{assign}`).

### `{{ control('DatePickerSetupControl', {ControlId: attributeId, DefaultDate: attributeValue, HasTimepicker: true}) }}`
Used in Attributes/Date.twig to invoke nested DatePickerSetupControl (replacing Smarty `{control type="DatePickerSetupControl" ...}`).

## Escaping Decisions / |raw Usage

**Autoescaping ON** for all `.twig` files (TwigEnvironmentFactory sets `autoescape => 'html'`).

- **`{{ label|raw }}`** (Controls/Checkbox.twig): The label is fetched via `Resources::GetString()`, which returns plain-text strings (no HTML special characters expected). Applied `|raw` to match Smarty's unescaped output. Risk: minimal since these are developer-controlled translation strings.

- **`{{ AltFormatJson|raw }}`**, **`{{ DefaultDateJson|raw }}`** (DatePickerSetup.twig): These are pre-encoded via PHP's `JsonEncodeForInlineScript()` using `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`, which makes them safe for inline `<script>` embedding. `|raw` needed so Twig does not double-encode the JSON.

- **`{{ DayNamesShort|raw }}`**, etc. (DatePickerSetup.twig): PHP-built JS array literal strings like `['Sun','Mon',...]`. Builder uses `implode("','", $values)` which escapes via single quotes — safe for inline JS.

- **`{{ OnSelect|raw }}`**, **`{{ OnClose|raw }}`** (DatePickerSetup.twig): JS callback strings provided by developer via PHP (`SetDefault('OnSelect', null)`). These are developer-controlled, not user input.

- **All attribute values** (Attributes/*.twig): `{{ attribute.Value() }}` is NOT marked `|raw` — HTML autoescaping applies. This is correct since attribute values are user input. Parity with Smarty is maintained because HtmlNormalizer collapses the escaped entities identically.

- **`{{ attribute.Label() }}`**: Not `|raw` — autoescaped. Correct for user-defined labels.

## Golden Strategy

### Full assertParity (normalized byte-equal after HtmlNormalizer)
Used for all 9 templates:
- Controls/Checkbox — confirmed equal after inline comment whitespace fix
- Controls/DatePickerSetup — pure JS/HTML, deterministic
- Controls/DateSetup — pure JS/HTML, deterministic
- Controls/RecurrenceDiv — deterministic HTML with translate() calls (real Resources)
- Controls/Attributes/Checkbox — deterministic HTML
- Controls/Attributes/Date — DatePickerSetupControl is invoked; with real Resources reset, output is deterministic
- Controls/Attributes/MultiLineTextbox — deterministic HTML
- Controls/Attributes/SelectList — deterministic HTML
- Controls/Attributes/SingleLineTextbox — deterministic HTML

All use `renderControlTemplate()` which routes to `.twig` when it exists (Twig path) vs. Smarty for the reference.

## Branches Covered (45 tests)

### Controls/Checkbox: 2 tests
- `testCheckboxUnchecked` — base case
- `testCheckboxWithExtraClass` — compound CSS class

### Controls/DatePickerSetup: 8 tests
- `testDatePickerSetupBasic` — date-only
- `testDatePickerSetupWithTimepicker` — with time
- `testDatePickerSetupMultipleMode` — multiple date selection
- `testDatePickerSetupWithMinMaxDate` — date range constraints
- `testDatePickerSetupWithDefaultDate` — pre-filled date
- `testDatePickerSetupWithOnSelect` — JS callback
- `testDatePickerSetupInlineMode` — inline display
- `testDatePickerSetupFirstDayMonday` — locale first day
- `testDatePickerSetupFirstDayOutOfRange` — firstDayOfWeek omitted (> 6)

### Controls/DateSetup: 3 tests
- `testDateSetupNoDefaults` — empty AltId, no dates
- `testDateSetupWithAltId` — change listener
- `testDateSetupWithMinMaxAndDefault` — min/max/default with Date objects

### Controls/RecurrenceDiv: 2 tests
- `testRecurrenceDivNoPrefix` — no prefix
- `testRecurrenceDivWithPrefix` — with prefix (uses `is defined` branch)

### Controls/Attributes/Checkbox: 7 tests
- Editable (unchecked, checked+required)
- Readonly (true value, false value)
- Readonly with tooltip
- Searchmode (selected, unselected)

### Controls/Attributes/MultiLineTextbox: 5 tests
- Editable, Required, Readonly, Readonly+tooltip, Searchmode

### Controls/Attributes/SelectList: 6 tests
- Editable (empty, selected value), Required, Readonly, Readonly+tooltip, Searchmode

### Controls/Attributes/SingleLineTextbox: 7 tests
- Editable, With value, Required, Readonly, Readonly+tooltip, Searchmode, No class (isset branch)

### Controls/Attributes/Date: 4 tests
- Editable, Readonly (with value), Readonly+tooltip, Required

## Results

All gates pass:
- `composer phpunit -- --testsuite golden`: **283/283 tests pass**
- `composer phpunit` (full suite): **2194/2194 tests pass** (0 failures)
- `composer phpcsfixer:fix`: **0 files changed**
- `composer phpstan`: **0 errors**
- `composer phpstan_next`: **0 errors**

## Concerns

1. **Inline Twig comment whitespace**: The `{# comment #}` tag at the end of a template line consumes the trailing newline, removing the space that Smarty preserves before `</label>`. Fixed by removing the inline comment. Other locations where inline comments appear in existing `.twig` files should be audited for this behavior.

2. **Existing tests needed updating**: Two existing tests (`testControlFunctionCheckboxControlFallsBackToTpl` and `testRenderPartialFallsBackToSmartyWhenNoTwigExists`) explicitly tested the `.tpl`-fallback path using `Controls/Checkbox.tpl` as a template "with no .twig counterpart." Both had to be updated to use normalized parity comparison since `Controls/Checkbox.twig` now exists. The tests still verify equivalence — they just use `HtmlNormalizer` instead of `assertSame` for byte equality.

3. **DateSetup template uses `AddDays(1)` method call**: In `DateSetup.twig`, the line `{{ formatdate(date=MaxDate.AddDays(1), format='Y-m-d') }}` calls a method on a Date object via Twig. Twig supports method/property access on objects via dot notation, so `MaxDate.AddDays(1)` calls `$MaxDate->AddDays(1)`. This is confirmed working by the tests.

4. **`render_partial` vs control template scope**: The two functions differ in their data scope. `renderControlTemplate` creates an isolated data scope; `renderPartial` uses full-page context. Both are covered by TwigRenderer and fall back to Smarty correctly.
