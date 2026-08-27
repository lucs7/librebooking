# Task 2.13 Report — Dashboard Templates Twig Migration

## Files Created

### Twig Templates (9 files in `tpl/Dashboard/`)

- `dashboard_reservation.twig` — Core partial included by all other templates
- `announcements.twig`
- `upcoming_reservations.twig`
- `admin_upcoming_reservations.twig`
- `group_upcoming_reservations.twig`
- `missing_check_in_out_reservations.twig`
- `past_reservations.twig`
- `pending_approval_reservations.twig`
- `resource_availability.twig` — Most complex; includes Twig 3 scoping workaround

### Test File

- `tests/Golden/DashboardGoldenTest.php` — 23 tests covering all 9 templates

### Config Changes

- `phpunit.xml.dist` — Added `golden` testsuite entry

## Conversion Decisions

### dashboard_reservation.twig

- `{assign var=checkin value=...&&...}` → `{% set checkin = ... and ... %}` (PHP `&&` to Twig `and`)
- `{assign var=class value=""}` + conditional assign → Twig ternary: `{% set class = reservation.RequiresApproval ? 'pending' : '' %}`
- `{if isset($orangePending)}` → `{% if orangePending is defined %}`
- `{if !$reservation->IsUserOwner($UserId)}` → `{% if not reservation.IsUserOwner(UserId) %}`
- `{$reservation->Title|escape:'html'|default:$DefaultTitle}` → `{{ reservation.Title is not empty ? reservation.Title : DefaultTitle }}`
- `{fullname first=$reservation->FirstName|unescape:'html' ...}` → `{{ fullname(first=reservation.FirstName|html_entity_decode, ...) }}`
- Comments converted from `{* ... *}` to `{# ... #}`

### announcements.twig

- `{foreachelse}` → `{% else %}` inside `{% for %}...{% endfor %}`
- `{$each->Text()|sanitize_rich_text|url2link|nl2br}` → `{{ each.Text()|sanitize_rich_text|url2link|nl2br|raw }}`
- `|raw` added to prevent double-escaping of the sanitized HTML chain
- Added comment: `{# sanitize_rich_text+url2link+nl2br chain produces safe HTML #}`
- Stray `</ul>` after foreach preserved for faithful 1:1 output

### pending_approval_reservations.twig

- `{$T|default:array()|count}` — `$T` is never set in Smarty (bug documented). Faithful translation: `{{ T is defined and T ? T|length : 0 }}` — outputs 0 as Smarty does
- `{assign var=orangePending value=false}` placed before first section per Smarty source order
- `orangePending` passed explicitly in each include

### resource_availability.twig

- Smarty array literal `[['title' => ..., ...]]` → Twig `[{'title': ..., ...}]`
- **Twig 3 scoping bug workaround**: Variables assigned inside `{% for %}` do NOT propagate out. Pre-computed `hasData` using Twig's `filter()` function before the inner Schedule loop:
  ```twig
  {% set hasData = Schedules|filter(s => section.data[s.GetId()] is defined ...)|length > 0 %}
  ```
- `section.data[$s->GetId()]` with PHP array access → `section.data[s.GetId()]` (Twig array access with method key)
- `dateField` conditional uses `{% if %}` blocks; variables set inside `{% if %}` DO propagate (only `{% for %}` creates new scope in Twig 3)

## Escaping Decisions

- All `{$var|escape:'html'}` patterns dropped — Twig autoescape (`autoescape: html`) handles these
- `{$var|unescape:'html'}` → `{{ var|html_entity_decode }}` (custom filter)
- Rich text chains get `|raw` at end to prevent double-escaping
- Twig's context-aware autoescape converts literal `&` in href attributes to `&amp;` (technically correct HTML; Smarty emits raw `&`)

## Golden Test Strategy

- `assertParity()` for 22 of 23 test cases — full Smarty vs. Twig normalized HTML comparison
- `assertTwigContains()` for `testResourceAvailabilityWithData` — due to the `&` vs `&amp;` divergence in href attributes (Twig correct HTML, Smarty technically invalid)
- Fixtures: `ReservationItemView` constructed directly, `ResourceDto` with null non-essential fields, `Schedule` with UTC timezone, anonymous class for announcements
- `CheckinDate`/`CheckoutDate` set to `NullDate()` to avoid null method calls in `RequiresCheckin()`/`RequiresCheckout()`

## Test Results Summary

- Golden suite: 23/23 pass (23 tests, 32 assertions)
- Full suite: 2096/2096 pass
- PHPStan base: 0 errors
- PHPStan next: 0 errors
- php-cs-fixer: 0 fixable issues

## Divergences from Smarty Source

1. **`&` vs `&amp;` in href attributes** (`resource_availability.twig`): Twig's context-aware HTML autoescape encodes `&` to `&amp;` within href attribute values. Smarty outputs raw `&`. The Twig output is technically correct HTML.

2. **`{$T|default:array()|count}` always 0** (`pending_approval_reservations.twig`): Faithfully reproduced — `$T` is never assigned in the Smarty template, so all three "LaterThisMonth", "LaterThisYear", "Other" section counts always render as 0. Documented in the template with a comment.

3. **Stray `</ul>` in announcements** preserved verbatim from Smarty source for output parity.
