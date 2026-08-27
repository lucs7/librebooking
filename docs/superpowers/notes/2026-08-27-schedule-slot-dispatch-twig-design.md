# Design: Schedule slot-dispatch Twig migration

Date: 2026-08-27
Branch: `refactor/twig`
Status: DESIGN ONLY (no template/PHP changes here)

This note designs the migration of the Schedule grid templates from Smarty to
Twig. It is the hardest part of the Smarty->Twig migration because the grid
templates perform **dynamic dispatch**: they render a slot by calling a
`{function}` whose *name is computed at runtime* by
`DisplaySlotFactory::GetFunction()`, and those functions read **implicit global
template variables** rather than only their declared params.

---

## 1. TL;DR of the chosen mechanism

The Twig doc-suggested `attribute(macrosObject, dynamicName, args)` approach
**does NOT work** in this project's Twig version (3.28.0 — see §6 for the live
proof). Macros captured into a variable become `Twig\Markup` values that are not
callable by `()` nor by `attribute()`; `_self` is a string (the template name),
so `attribute(_self, name, args)` also fails.

**The mechanism that works and that we adopt:** one shared macro file per
context that contains (a) all the `display*` macros and (b) a single
`dispatch(fnName, ...commonArgs)` macro implemented as an `{% if/elseif %}`
chain over the **finite, known** set of names, each branch calling
`{{ _self.<literalName>(...) }}`. `_self.<literalName>()` is a compile-time
macro call (Twig special-cases it) and resolves to the *importing file's own*
macros even when the file is imported from elsewhere — verified live.

The grid template imports the shared file and calls the dispatcher:

```twig
{% import 'Schedule/_slot_macros.twig' as slot %}
...
{{ slot.dispatch(DisplaySlotFactory.GetFunction(Slot, AccessAllowed),
                 Slot, Href, SlotRef, ResourceId,
                 CanViewAdmin, spantype, SlotLabelFactory) }}
```

The dispatcher forwards the common positional arg-list to whichever branch
matches. Every `display*` macro in the file therefore standardises on the **same
positional signature** so any branch can be fed the same args.

---

## 2. Inventory of the source templates

### 2.1 Grid / slot-render templates and how they are used

| Template | Role | Includes | Extends |
|---|---|---|---|
| `tpl/Schedule/schedule.tpl` | Full page + default (standard) grid host | `{include "Schedule/schedule-reservations-grid.tpl"}` (block `reservations`) | — |
| `tpl/Schedule/schedule-reservations-grid.tpl` | Standard interactive grid | — | — |
| `tpl/Schedule/schedule-reservations-grid-static.tpl` | Static grid (no JS interactivity) | — | — |
| `tpl/Schedule/schedule-week-condensed.tpl` | Condensed-week variant | overrides block `reservations`; uses `'Condensed'` suffix | `extends schedule.tpl` |
| `tpl/Schedule/schedule-mobile.tpl` | Mobile variant | overrides block `reservations`; uses `'Mobile'` suffix | `extends schedule.tpl` |
| `tpl/Schedule/schedule-flipped.tpl` (Tall) | Periods-as-rows variant | overrides block `reservations`; no suffix (reuses base names) | `extends schedule.tpl` |
| `tpl/Schedule/schedule-days-horizontal.tpl` (Wide) | Days-across variant | overrides block `reservations`; no suffix | `extends schedule.tpl` |
| `tpl/MonitorDisplay/monitor-display-schedule.tpl` | Monitor grid (Static factory) | `{include "Schedule/schedule-reservations-grid-static.tpl"}` when `Format==1` | — |
| `tpl/MonitorDisplay/monitor-display-schedule.twig` | Already migrated in Task 2.8 (macros inline) | `render_partial('...grid-static.tpl')` | — |

Which style renders which template is decided in `Web`/JS by `ScheduleStyle`
(`ScheduleStyle::Standard|Tall|Wide|CondensedWeek`), fetched via the
`DATA_REQUEST=reservations` endpoint; the base grid ships in the initial page.

### 2.2 `{function}` defined vs. `{call}`/dynamic-dispatch sites vs. implicit globals

**`schedule.tpl`** (and the identical block in `schedule-reservations-grid.tpl`)
defines the **base "normal" macro set**:

- `displayPastTime`, `displayReservable`, `displayRestricted`,
  `displayUnreservable` — each reads only declared-ish params
  `$Slot, $Href, $SlotRef, $ResourceId` (in Smarty these arrive via the
  `{call}`; but note `displaySlot` itself is called with those as attributes).
- `displaySlot` — the dispatch site:
  `{call name=$DisplaySlotFactory->GetFunction($Slot, $AccessAllowed) Slot=... Href=... SlotRef=... ResourceId=...}`.
  Implicit global read: **`$DisplaySlotFactory`** (page var).

The base set has **no reserved-state macros** — `DisplaySlotFactory`
(non-static) never returns `displayReserved`/`displayMyReserved`/etc. (see §3).

**`schedule-reservations-grid.tpl`** / **`-static.tpl`**: redefine `displaySlot`
(same body); the grid loops build `$Slot`, `$href`, `$slotRef`, `$resourceId`,
`AccessAllowed` and invoke `{displaySlot ...}`. Implicit globals used by the grid
body (not the macros): `$DailyLayout`, `$Resources`, `$ScheduleId`,
`$CreateReservationPage`, `$BoundDates`, `$LoadViewOnly`, `$AllowGuestBooking`
(grid.tpl only), plus `Date::Now()`, `Pages::*`.

**`schedule-week-condensed.tpl`** defines the **`Condensed` suffixed set**:

- `displayGeneralReservedCondensed` — implicit globals:
  **`$DisplaySlotFactory`** (calls `GetCondensedPeriodLabel`),
  **`$Periods`**, **`$SlotLabelFactory`**; params `$Slot, $OwnershipClass`
  (+ locally-set `$class`, `$color`).
- `displayAdminReservedCondensed`, `displayMyReservedCondensed`,
  `displayMyParticipatingCondensed`, `displayReservedCondensed` — thin wrappers
  calling `displayGeneralReservedCondensed` with an `OwnershipClass`.
- `displayPastTimeCondensed`, `displayReservableCondensed`,
  `displayRestrictedCondensed` — **empty** (render nothing).
- `displayUnreservableCondensed` — reads **`$SlotLabelFactory`**; `$Slot`.
- `displaySlotCondensed` — dispatch site with suffix `'Condensed'`, passes
  `Slot, Href, Periods`. Implicit: **`$DisplaySlotFactory`**, **`$Periods`**.

**`schedule-mobile.tpl`** defines the **`Mobile` suffixed set** — same shape as
Condensed but:

- `displayGeneralReservedMobile` reads **`$SlotLabelFactory`**; builds a
  New/Updated `badge` inline (`translate`), `$Slot, $OwnershipClass`.
- `displayUnreservableMobile` reads **`$SlotLabelFactory`**.
- Empty: `displayPastTimeMobile`, `displayReservableMobile`,
  `displayRestrictedMobile`.
- `displaySlotMobile` — dispatch site, suffix `'Mobile'`, passes
  `Slot, Href, SlotRef`.

**`schedule-flipped.tpl`** (Tall) defines `displaySlotTall` **but** it dispatches
with **no suffix**, so it reuses the base `display{Reservable,Restricted,
Unreservable,PastTime}` macros. Problem: those base macros are defined in
`schedule.tpl` and this file `extends schedule.tpl`, so at render time Smarty's
global `{function}` registry makes them visible. Call site passes
`Slot, Href, SlotRef, ResourceId`. Note the Tall grid passes `$period` objects
(from `GetPeriods`) as `$Slot`, not layout slots.

**`schedule-days-horizontal.tpl`** (Wide) redefines `displaySlot` (base body,
no suffix), reuses base macros. Passes period objects as `$Slot`.

**`monitor-display-schedule.tpl`** (the Smarty original; the `.twig` already
exists from 2.8) defines the **monitor macro set** — this is the richest set
because it uses `StaticDisplaySlotFactory`, which DOES return reserved-state
names:

- `displayGeneralReserved` — implicit globals: **`$spantype`** (defaulted to
  `'col'` in-macro; never set by PHP), **`$SlotLabelFactory`**, **`$CanViewAdmin`**
  (indirectly via `displayReserved`'s `Draggable`), `$ResourceId`, `$Draggable`,
  `$OwnershipClass`.
- `displayMyReserved`, `displayAdminReserved`, `displayMyParticipating`,
  `displayReserved` — wrappers (Reserved passes `Draggable=$CanViewAdmin`).
- `displayPastTime` — reads **`$spantype`**, **`$CanViewAdmin`**,
  **`$SlotLabelFactory`**, `$SlotRef`, `$ResourceId`.
- `displayReservable` — reads **`$spantype`**; `$Slot, $Href, $SlotRef, $ResourceId`.
- `displayRestricted` — reads **`$spantype`**; `$Slot`.
- `displayUnreservable` — reads **`$spantype`**, **`$SlotLabelFactory`**.
- Grid host body: `Format==1` -> include static grid; else custom day list
  (reads `$BoundDates, $Resources, $DailyLayout, $SlotLabelFactory`).

### 2.3 Enumerated implicit globals (become explicit macro params)

Across all sets the implicit template-scope reads that must be threaded as
explicit params are:

- `DisplaySlotFactory` — only needed at the **dispatch site** (call
  `.GetFunction()` / `.GetCondensedPeriodLabel()`); pass into the dispatcher, not
  into leaf macros, except Condensed's `displayGeneralReserved` which calls
  `GetCondensedPeriodLabel` -> it needs `DisplaySlotFactory` **and** `Periods`.
- `SlotLabelFactory` — needed by every macro that renders `Slot.Label(...)`.
- `CanViewAdmin` — monitor `displayReserved`/`displayPastTime`.
- `spantype` — monitor set only (always `'col'` in practice; keep as param with
  `?? 'col'` default, exactly as the existing 2.8 `.twig` does).
- `Periods` — Condensed set (`GetCondensedPeriodLabel`, dispatch arg).
- `ResourceId`, `Href`, `SlotRef`, `AccessAllowed` — already explicit at call
  sites; keep explicit.

---

## 3. `DisplaySlotFactory` — exact return strings per context

`lib/Application/Schedule/DisplaySlotFactory.php` has two classes.

### `DisplaySlotFactory` (schedule views — `SchedulePage`, `ViewSchedulePage`)

`GetFunction(SchedulePeriod $slot, $accessAllowed=false, $functionSuffix='')`:

| Condition | Returned name (suffix appended) |
|---|---|
| `!$accessAllowed` | `displayRestricted{S}` |
| past date & not admin | `displayPastTime{S}` |
| reservable | `displayReservable{S}` |
| else | `displayUnreservable{S}` |

So the **schedule-view macro set must define exactly**: `displayRestricted`,
`displayPastTime`, `displayReservable`, `displayUnreservable` (× suffix).
It never returns any `Reserved` variant. (Reserved slots in the interactive
schedule are injected by JS after an AJAX load, not by these macros.)

Suffix per variant: standard/tall/wide -> `''`; condensed -> `'Condensed'`;
mobile -> `'Mobile'`.

### `StaticDisplaySlotFactory` (monitor + `ReservationUserAvailabilityPage`)

`GetFunction(IReservationSlot $slot, $accessAllowed=false, $functionSuffix='')`:

| Condition | Returned name |
|---|---|
| reserved & mine | `displayMyReserved{S}` |
| reserved & participating | `displayMyParticipating{S}` |
| reserved & admin-for | `displayAdminReserved{S}` |
| reserved (other) | `displayReserved{S}` |
| not reserved & `!$accessAllowed` | `displayRestricted{S}` |
| not reserved & past & not admin | `displayPastTime{S}` |
| not reserved & reservable | `displayReservable{S}` |
| else | `displayUnreservable{S}` |

So the **monitor/static macro set must define all eight**:
`displayMyReserved, displayMyParticipating, displayAdminReserved,
displayReserved, displayRestricted, displayPastTime, displayReservable,
displayUnreservable` (suffix `''` in the monitor/static usage).

`GetCondensedPeriodLabel($periods, $start, $end)` (both classes, identical):
returns a labelled period string or a formatted `start - end`; used only by the
Condensed set.

Factory wiring (confirmed):
- `Pages/SchedulePage.php` -> `new DisplaySlotFactory()`
- `Pages/ViewSchedulePage.php` -> `new DisplaySlotFactory()`
- `Pages/MonitorDisplayPage.php` -> `new StaticDisplaySlotFactory()` + `Format`
- `Pages/Ajax/ReservationUserAvailabilityPage.php` -> `new StaticDisplaySlotFactory()`

---

## 4. The Twig design

### 4.1 Shared macro files (three contexts, one file each)

| File | Contexts | Macros | Dispatcher common signature |
|---|---|---|---|
| `tpl/Schedule/_slot_macros.twig` | standard / tall / wide (base, no suffix) | `displayReservable, displayRestricted, displayUnreservable, displayPastTime` + `dispatch` | `dispatch(fnName, Slot, Href, SlotRef, ResourceId, CanViewAdmin)` |
| `tpl/Schedule/_slot_macros_condensed.twig` | condensed | `displayGeneralReservedCondensed` + 5 reserved wrappers + `displayUnreservableCondensed` + 3 empty macros + `dispatch` | `dispatch(fnName, Slot, Href, Periods, SlotLabelFactory, DisplaySlotFactory)` |
| `tpl/Schedule/_slot_macros_mobile.twig` | mobile | mobile reserved set + empties + `displayUnreservableMobile` + `dispatch` | `dispatch(fnName, Slot, Href, SlotRef, SlotLabelFactory)` |
| `tpl/MonitorDisplay/_slot_macros_monitor.twig` | monitor + static grid | all 8 (reserved + non-reserved) + `dispatch` | `dispatch(fnName, Slot, Href, SlotRef, ResourceId, CanViewAdmin, spantype, SlotLabelFactory)` |

Why separate files rather than one big file with all suffixes: the suffix in
Smarty was a hack to namespace macro sets in a single global registry. In Twig
we get real file scoping for free, so the suffix disappears — each context's
`dispatch` strips the suffix by calling
`DisplaySlotFactory.GetFunction(Slot, Access)` with **empty** suffix (the names
inside the file are unsuffixed) OR the dispatcher matches on the suffixed name.
Recommended: call `GetFunction(Slot, Access, '')` (no suffix) and let the
dispatcher match base names; keeps one dispatch chain per file.

### 4.2 The dispatcher macro (schedule-view example)

```twig
{# tpl/Schedule/_slot_macros.twig #}
{% macro displayReservable(Slot, Href, SlotRef, ResourceId) %}
    <td class="reservable clickres slot" ref="{{ SlotRef }}" data-href="{{ Href }}"
        data-start="{{ Slot.BeginDate().Format('Y-m-d H:i:s')|urlencode }}"
        data-end="{{ Slot.EndDate().Format('Y-m-d H:i:s')|urlencode }}"
        data-min="{{ Slot.BeginDate().Timestamp() }}"
        data-max="{{ Slot.EndDate().Timestamp() }}"
        data-resourceId="{{ ResourceId }}">&nbsp;</td>
{% endmacro %}
{# displayRestricted / displayUnreservable / displayPastTime: same shape #}

{% macro dispatch(fnName, Slot, Href, SlotRef, ResourceId) %}
    {%- if fnName == 'displayReservable' -%}
        {{ _self.displayReservable(Slot, Href, SlotRef, ResourceId) }}
    {%- elseif fnName == 'displayRestricted' -%}
        {{ _self.displayRestricted(Slot, Href, SlotRef, ResourceId) }}
    {%- elseif fnName == 'displayPastTime' -%}
        {{ _self.displayPastTime(Slot, Href, SlotRef, ResourceId) }}
    {%- else -%}
        {{ _self.displayUnreservable(Slot, Href, SlotRef, ResourceId) }}
    {%- endif -%}
{% endmacro %}
```

Grid dispatch site (in `schedule-reservations-grid.twig`):

```twig
{% import 'Schedule/_slot_macros.twig' as slot %}
...
{{ slot.dispatch(DisplaySlotFactory.GetFunction(Slot, AccessAllowed),
                 Slot, href, slotRef, resourceId) }}
```

The monitor dispatcher additionally branches on the four reserved names and
forwards `CanViewAdmin, spantype, SlotLabelFactory`; its leaf macros are exactly
the ones already written in `monitor-display-schedule.twig` (§4.4).

### 4.3 Per-macro signatures and the implicit globals they need

Standard / tall / wide (`_slot_macros.twig`):

- `displayReservable(Slot, Href, SlotRef, ResourceId)` — none extra
- `displayRestricted(Slot, Href, SlotRef, ResourceId)` — none extra
- `displayUnreservable(Slot, Href, SlotRef, ResourceId)` — none extra
- `displayPastTime(Slot, Href, SlotRef, ResourceId)` — none extra
- `dispatch(fnName, Slot, Href, SlotRef, ResourceId)`

Condensed (`_slot_macros_condensed.twig`):

- `displayGeneralReserved(Slot, OwnershipClass, Periods, SlotLabelFactory, DisplaySlotFactory)`
  — needs `Periods`, `SlotLabelFactory`, `DisplaySlotFactory`
- `displayAdminReserved / displayMyReserved / displayMyParticipating /
  displayReserved (Slot, Periods, SlotLabelFactory, DisplaySlotFactory)` — wrappers
- `displayUnreservable(Slot, SlotLabelFactory)`
- `displayPastTime / displayReservable / displayRestricted (…)` — empty bodies
- `dispatch(fnName, Slot, Href, Periods, SlotLabelFactory, DisplaySlotFactory)`

Mobile (`_slot_macros_mobile.twig`):

- `displayGeneralReserved(Slot, OwnershipClass, SlotLabelFactory)` — needs
  `SlotLabelFactory`; builds New/Updated badge via `translate`
- 4 reserved wrappers `(Slot, SlotLabelFactory)`
- `displayUnreservable(Slot, SlotLabelFactory)`
- 3 empty macros
- `dispatch(fnName, Slot, Href, SlotRef, SlotLabelFactory)`

Monitor (`_slot_macros_monitor.twig`) — identical bodies to the current 2.8
`.twig` macros; signatures:

- `displayGeneralReserved(Slot, Href, SlotRef, OwnershipClass, Draggable, ResourceId, spantype, SlotLabelFactory)`
- `displayMyReserved / displayAdminReserved / displayMyParticipating(Slot, Href, SlotRef, ResourceId, spantype, SlotLabelFactory)`
- `displayReserved(Slot, Href, SlotRef, ResourceId, CanViewAdmin, spantype, SlotLabelFactory)`
- `displayPastTime(Slot, Href, SlotRef, ResourceId, CanViewAdmin, spantype, SlotLabelFactory)`
- `displayReservable(Slot, Href, SlotRef, ResourceId, spantype)`
- `displayRestricted(Slot, spantype)`
- `displayUnreservable(Slot, spantype, SlotLabelFactory)`
- `dispatch(fnName, Slot, Href, SlotRef, ResourceId, CanViewAdmin, spantype, SlotLabelFactory)`

### 4.4 CRITICAL: refactor `monitor-display-schedule.twig` to import the shared file

Currently (`tpl/MonitorDisplay/monitor-display-schedule.twig`) the macros are
**inline** AND the `Format==1` branch does
`render_partial('Schedule/schedule-reservations-grid-static.tpl', _context)`
— which falls back to **Smarty** (no `.twig` yet). That Smarty grid then calls
`{call name=$DisplaySlotFactory->GetFunction(...)}` expecting Smarty
`{function}`s — but the parent is now Twig, so those functions **do not exist**.
This is the flagged CRITICAL breakage: the monitor static path is currently
relying on the Smarty fallback grid + Smarty functions, and the inline Twig
macros are unreachable from it.

Fix as part of this migration:

1. Move the 8 inline monitor macros + a `dispatch` into
   `tpl/MonitorDisplay/_slot_macros_monitor.twig`.
2. `monitor-display-schedule.twig` does
   `{% import 'MonitorDisplay/_slot_macros_monitor.twig' as slot %}` and, for the
   `Format==1` branch, `render_partial('Schedule/schedule-reservations-grid-static.twig', vars)`
   — i.e. the **static grid must also be migrated to `.twig`** so the Twig branch
   of `render_partial` fires and both templates share the same macro file.
3. The static grid `.twig` likewise imports `_slot_macros_monitor.twig` (because
   the monitor uses `StaticDisplaySlotFactory`) and dispatches through it. Note
   the static grid is **also** used by the plain schedule view (via
   `schedule.tpl` there is no static-grid include, but `ViewSchedulePage`/monitor
   use it) — see §5 risk about the static grid being shared by two factories.

Because `render_partial` chooses `.twig` when present, once
`schedule-reservations-grid-static.twig` exists the monitor path stops touching
Smarty entirely.

### 4.5 Base grid + schedule.tpl

- `schedule.twig` drops the four base `{function}`s and the `displaySlot`
  wrapper. It keeps only the page chrome and `{% block reservations %}` which
  `include`s `schedule-reservations-grid.twig`.
- `schedule-reservations-grid.twig` imports `_slot_macros.twig` and replaces the
  `{displaySlot ...}` call inside the slot loop with
  `{{ slot.dispatch(DisplaySlotFactory.GetFunction(Slot, access), Slot, href, slotRef, resourceId) }}`.
- The variant templates (`schedule-week-condensed`, `-mobile`, `-flipped`,
  `-days-horizontal`) each import their context's macro file and dispatch the
  same way in their overridden `reservations` block.

Twig has no `{extends}`+`{block}` limitation here — Twig inheritance is native;
the variants become `{% extends 'Schedule/schedule.twig' %}` with
`{% block reservations %}`.

---

## 5. Risks and fallbacks

1. **Signature drift across the macro family.** Every branch in a `dispatch`
   receives the same positional args, so a leaf macro that ignores some args is
   fine, but a leaf needing an arg the dispatcher doesn't pass will silently
   render wrong. Mitigation: the dispatcher signature is the **superset** of what
   any branch needs (enumerated in §4.3); add a golden test per branch.
2. **Condensed needs `Periods` + `GetCondensedPeriodLabel`.** The condensed
   dispatcher must carry `Periods` and `DisplaySlotFactory`; these are not in the
   other contexts' arg-lists — hence separate files, not one shared signature.
3. **Tall/Wide pass `period` objects as `Slot`.** The base macros call
   `Slot.BeginDate()/EndDate()/Timestamp()` which `SchedulePeriod` supports, but
   they are periods, not layout slots — verify `IsReservable()`/`IsPastDate()`
   used by `GetFunction` behave on periods (they do today under Smarty; keep the
   golden test covering Tall/Wide specifically).
4. **`spantype` is never set by PHP.** Keep the `?? 'col'` default in the monitor
   macros (as the 2.8 `.twig` already does). Do not add a page var.
5. **Static grid shared by two factories.** `schedule-reservations-grid-static`
   is used by the monitor (`StaticDisplaySlotFactory`, needs the 8-name set) and
   potentially by view paths. The grid must import the macro file appropriate to
   its caller. Cleanest: the **caller** imports the macro file and passes the
   dispatcher/macros namespace name is not possible (macros aren't first-class),
   so instead the static grid should `import` `_slot_macros_monitor.twig`
   directly (the 8-name superset also satisfies the schedule-view subset, since
   `DisplaySlotFactory` only ever returns the 4 non-reserved names, which exist
   in the monitor file too). Recommendation: **the static grid imports the
   monitor (superset) macro file**; the interactive grid imports the base file.
6. **No dynamic-string dispatch in Twig 3.28.** `attribute()` / variable-held
   macros are not callable (proven §6). The `{% if/elseif %}` chain is the
   fallback-free mechanism; it is O(n) over ≤8 names — negligible.
7. **`render_partial` Smarty fallback cannot see parent Twig macros.** So we may
   NOT leave any grid on Smarty while its host is Twig. Order (below) migrates a
   host and all grids it can reach together. If any single template proves
   intractable, the fallback is to keep **both** the host and its grid on Smarty
   (revert the pair), never a Twig-host/Smarty-grid split.

---

## 6. Verification of the Twig version behaviour (live)

Twig version: **3.28.0** (`twig/twig: ^3.10` in `composer.json`;
`Twig\Environment::VERSION` == `3.28.0`). Tested against `vendor/` on this
branch:

- `{% import 'macros.twig' as m %}` then `attribute(m, name, args)` ->
  **"Variable m does not exist"** (import alias is not a runtime variable).
- `attribute(_self, name, args)` -> **"Impossible to access an attribute … on a
  string variable"** (`_self` is the template name string in Twig 3.x).
- `dispatch[fnName](Slot, Href)` (macro stored in a hash, called with `()`) ->
  **"Function name must be an identifier"**.
- `attribute(fnmap[fnName], '__invoke', args)` -> macro value is `Twig\Markup`,
  **not callable**.
- `{% if fnName == 'x' %}{{ _self.x(...) }}{% elseif … %}` -> **works**.
- Shared file with an internal `dispatch` using `_self.<literal>()`, imported by
  another template as `{% import '…' as slot %}` and called `slot.dispatch(…)` ->
  **works** (cross-file `_self` resolves to the imported file's own macros).

Conclusion: the task's proposed `attribute(macrosObject, dynamicName, args)`
mechanism is **not viable** in 3.28; the `_self`-chain dispatcher is the design.

---

## 7. Recommended implementation order

1. **`tpl/Schedule/_slot_macros.twig`** (base 4 macros + `dispatch`). Unit-test
   the dispatcher in isolation (all 4 `GetFunction` outcomes).
2. **`schedule-reservations-grid.twig`** (interactive grid) importing #1;
   golden-test against Smarty grid.
3. **`schedule.twig`** (page chrome + block) including #2; golden-test full page.
4. **`_slot_macros_monitor.twig`** (8 macros + dispatch) extracted from the
   existing 2.8 inline macros.
5. **`schedule-reservations-grid-static.twig`** importing #4.
6. **Refactor `monitor-display-schedule.twig`** to import #4 and
   `render_partial` the `.twig` static grid — closes the CRITICAL gap.
7. **`schedule-week-condensed.twig`** + `_slot_macros_condensed.twig`.
8. **`schedule-mobile.twig`** + `_slot_macros_mobile.twig`.
9. **`schedule-flipped.twig`** (Tall) — imports base #1.
10. **`schedule-days-horizontal.twig`** (Wide) — imports base #1.

Do the base trio (1-3) first: it establishes the pattern and unblocks the most
traffic. Do 4-6 next because the monitor is currently the only actively broken
path. Variants (7-10) last.

---

## 8. Golden-test strategy (per template)

For each grid/variant, render the **live Smarty** template and the **new Twig**
template with the *same fixture context* and assert byte-equal (after
whitespace-normalisation, since Twig `{%- -%}` trimming differs from Smarty).

Fixtures must exercise **every dispatch branch**:

- Build `Slot`/`SchedulePeriod` doubles covering: reservable, unreservable,
  restricted (`AccessAllowed=false`), past-time (past date + non-admin session),
  and — for the static/monitor set — reserved-mine, reserved-participating,
  reserved-admin, reserved-other.
- Toggle the session (`ServiceLocator::GetServer()->GetUserSession()`) between
  admin and non-admin to cover the `IsPastDate && !admin` and `IsAdminFor`
  branches, and set `CanViewAdmin` accordingly.
- **Pin the clock**: `Date::Now()` is read directly in the grids and by
  `GetFunction`'s past-date check. Freeze it (the codebase already supports a
  fixed "now" via `Date`/`DateHelper` test seams used elsewhere) so
  past/future/today classification is deterministic; also pin the today-row
  highlight (`date->DateEquals(TodaysDate)`).
- Condensed: include `Periods` with a labelled period to exercise both branches
  of `GetCondensedPeriodLabel`.
- Mobile: set `IsNew()`/`IsUpdated()` on a slot to exercise the badge branch.
- Assert the dispatcher picks the right macro by checking the emitted CSS class
  (`reservable`/`restricted`/`pasttime`/`unreservable`/`reserved mine` …) per
  fixture slot — this is the direct observable of correct dynamic dispatch.

Run these as part of the existing presenter/template test suites; add an
entry-point-style regression only if a `Web/*.php` bootstrap changes (it does
not for this task).
