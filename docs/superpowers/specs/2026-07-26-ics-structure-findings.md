# ICS/iCalendar layer — structural findings

Identified during code review of `bugfix/1336-reservation-ics-itip-method`.
Ranked by impact. None of these were introduced by the branch; most are
pre-existing structural issues that the branch made more visible.

---

## 1. `CalendarExportDisplay` inherits `Page` but is a serializer

`CalendarExportDisplay extends Page` yet `PageLoad()` is a no-op.
The class is a VObject serializer — it converts `iCalendarReservationView[]`
to an ICS string. Inheriting from `Page` drags in session handling, header
setup, and the full framework bootstrap even when the class is instantiated
inside `PopulateIcsAttachment` to generate an email attachment.

**Resolution:** Extract `Render()` and `DetermineMethod()` into a standalone
service class in `lib/Application/Schedule/` (e.g. `ICalendarSerializer`).
Keep a thin `CalendarExportDisplay extends Page` in `Pages/` that delegates
to the service for the HTTP export path.

---

## 2. `DetermineMethod()` is in `Pages/` but called from `lib/`

`ReservationEmailMessage` (`lib/Email/Messages/`) calls
`CalendarExportDisplay::DetermineMethod()` (`Pages/Export/`).
This is an inverted layer dependency — `lib/` must never import from `Pages/`.

**Resolution:** Move `DetermineMethod()` to the new service class (see #1)
or onto `iCalendarReservationView` itself. Both callers (email and
subscription) are already in `lib/`/`Presenters/`.

---

## 3. `'Private'` used as a magic sentinel in `OrganizerEmail`

`iCalendarReservationView` sets `$this->OrganizerEmail = 'Private'` when
privacy filtering hides the user, and `CalendarExportDisplay::Render()` must
check `!== 'Private'` before emitting the `ORGANIZER` property. Coupling the
renderer to a magic string inside a DTO is fragile.

**Resolution:** Set `$this->OrganizerEmail = null` when privacy filtering is
active. The renderer checks `if ($res->OrganizerEmail !== null)`. Update the
one test that currently asserts `=== 'Private'` on the raw property.

---

## 4. Three independent construction paths for `ReservationItemView`

The branch surfaces three places that build a `ReservationItemView` from
non-database sources:

- `ReservationItemView::FromReservationView()` — from a full `ReservationView`
- `PopulateIcsAttachment()` — built manually from `ReservationSeries` +
  `Reservation` + pre-fetched users (12-argument constructor call)
- Test code — bare instances with fields set directly

Each path populates a different subset of fields with different fidelity,
making it hard to know which fields are reliably present at any call site.

**Resolution:** Add a factory method (e.g. `ReservationItemView::FromReservationSeries()`)
that encapsulates the `PopulateIcsAttachment` construction path. Centralises
field-population and makes the contract explicit.

---

## 5. Double docblock on `PopulateIcsAttachment`

`ReservationEmailMessage.php` has two consecutive `/** ... */` blocks before
`PopulateIcsAttachment()` — a leftover from the signature-change refactor.

**Resolution:** Delete the shorter, stale first block (lines 185–188).

---

## 6. `method_exists()` duck-typing on `ExportFactory`

```php
method_exists($this->ExportFactory, 'GetIcalendarClassification') ? ... : 'PUBLIC'
method_exists($this->ExportFactory, 'GetIcalendarExtraLines')     ? ... : null
```

The plugin interface does not declare these methods, so the code falls back
silently at runtime rather than failing loudly during development.

**Resolution:** Add `GetIcalendarClassification()` and `GetIcalendarExtraLines()`
to the `IExportFactory` interface (or a dedicated `IIcalendarExportFactory`),
with default implementations in a base class. Remove the `method_exists` guards.

---

## 7. `iCalendarReservationView` constructor handles too many concerns

The constructor currently: applies privacy filtering, formats the summary via
`SlotLabelFactory`, generates the reservation URL, builds the recurrence rule
string, reads three config keys, calls `Resources::GetInstance()` for the
default description label, and delegates to `PluginManager`. That is data
mapping, privacy policy, URL construction, i18n, config, and plugin extension
all inline.

**Resolution:** Extract privacy application and URL generation into the
caller or into dedicated private methods. The constructor should do
straightforward field assignment; each extracted method is independently
testable.

---

*Branch:* `bugfix/1336-reservation-ics-itip-method`  
*Reviewed:* 2026-07-26
