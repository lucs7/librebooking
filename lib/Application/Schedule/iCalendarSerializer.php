<?php

use Sabre\VObject\Component\VAlarm;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;

class iCalendarSerializer
{
    /**
     * A single reservation with real attendees is a scheduling request a client can act
     * on (Accept/Decline). Anything else (no attendees, or a multi-event export/subscription
     * feed) stays PUBLISH; see commit dfd28fef ("remove METHOD:REQUEST ... fix Add to Outlook")
     * for why REQUEST without attendee data broke basic calendar import.
     *
     * @param iCalendarReservationView[] $reservations
     * @return string 'REQUEST' or 'PUBLISH'
     */
    public static function DetermineMethod(array $reservations): string
    {
        $hasSingleReservationWithAttendees = count($reservations) === 1 && !empty($reservations[0]->Attendees);
        return $hasSingleReservationWithAttendees ? 'REQUEST' : 'PUBLISH';
    }

    /**
     * @param iCalendarReservationView[] $reservations
     * @param string|null $calendarName Optional display name rendered as X-WR-CALNAME
     * @param string|null $forceMethod Overrides DetermineMethod(), e.g. reservation invite emails
     *                                 always send METHOD:REQUEST regardless of attendee count.
     */
    public static function Render(array $reservations, ?string $calendarName = null, ?string $forceMethod = null): string
    {
        // Values passed as constructor children are merged over getDefaults(),
        // replacing the PRODID that VCalendar would otherwise generate.
        $vcal = new VCalendar([
            'PRODID' => '-//LibreBooking//NONSGML ' . Configuration::VERSION . '//EN',
            'METHOD' => $forceMethod ?? self::DetermineMethod($reservations),
        ]);

        if ($calendarName !== null && $calendarName !== '') {
            $vcal->add('NAME', $calendarName);
            $vcal->add('X-WR-CALNAME', $calendarName);
        }

        // ScriptUrl is used to generate iCal UIDs. Avoid slashes per
        // https://bugzilla.mozilla.org/show_bug.cgi?id=465853
        $uid = parse_url(Configuration::Instance()->GetScriptUrl(), PHP_URL_HOST) ?: '';
        $isoFormat = Resources::GetInstance()->GetDateFormat('ical');

        foreach ($reservations as $res) {
            /** @var iCalendarReservationView $res */
            // Constructor children replace the UID and DTSTAMP that VEvent
            // would otherwise auto-generate in getDefaults().
            $event = new VEvent($vcal, 'VEVENT', [
                'UID' => $res->ReferenceNumber . '&' . $uid,
                'DTSTAMP' => $res->DateCreated->Format($isoFormat),
            ]);
            $vcal->add($event);
            $event->add('CLASS', $res->Classification);
            $event->add('CREATED', $res->DateCreated->Format($isoFormat));
            $event->add('DESCRIPTION', $res->Description);
            $event->add('DTSTART', $res->DateStart->Format($isoFormat));
            $event->add('DTEND', $res->DateEnd->Format($isoFormat));
            $event->add('LAST-MODIFIED', $res->LastModified->Format($isoFormat));
            $event->add('LOCATION', $res->Location);
            if (!empty($res->OrganizerEmail)) {
                $event->add('ORGANIZER', 'mailto:' . $res->OrganizerEmail, ['CN' => $res->Organizer]);
            }
            // RFC 5546 §3.2.1: a PUBLISH VEVENT's ATTENDEE list MUST be empty, since PUBLISH
            // doesn't solicit a reply. ATTENDEE is only meaningful for a single-event render —
            // either a one-to-one scheduling email (REQUEST/CANCEL, always exactly one
            // reservation) or an export of exactly one reservation. A multi-event feed always
            // resolves to PUBLISH (see DetermineMethod()) and must never carry ATTENDEE data.
            if (count($reservations) === 1) {
                foreach ($res->Attendees as $attendee) {
                    $event->add('ATTENDEE', 'mailto:' . $attendee['Email'], [
                        'CN' => $attendee['Name'],
                        'ROLE' => 'REQ-PARTICIPANT',
                        'PARTSTAT' => 'NEEDS-ACTION',
                        'RSVP' => 'TRUE',
                    ]);
                }
            }
            $event->add('STATUS', $res->IsCancelled ? 'CANCELLED' : ($res->IsPending ? 'TENTATIVE' : 'CONFIRMED'));
            $event->add('SUMMARY', $res->Summary);
            $event->add('SEQUENCE', 0);
            $event->add('URL', $res->ReservationUrl);
            $event->add('X-MICROSOFT-CDO-BUSYSTATUS', 'BUSY');

            if ($res->RecurRule) {
                $event->add('RRULE', $res->RecurRule);
            }

            if (!empty($res->ExtraIcalLines)) {
                // Parse with Sabre's own reader (wrapped in a throwaway VCALENDAR/VEVENT
                // shell) rather than hand-splitting on ':', so parameters (e.g.
                // ATTENDEE;CN=...), nested components (e.g. BEGIN:VALARM), and RFC 5545
                // line folding round-trip correctly instead of becoming malformed
                // flat properties.
                try {
                    $fragment = Reader::read(
                        "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\n"
                        . rtrim((string)$res->ExtraIcalLines, "\r\n")
                        . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
                    );
                    foreach ($fragment->getComponents()[0]->children() as $child) {
                        $event->add($child);
                    }
                } catch (ParseException $e) {
                    // ExtraIcalLines is plugin-supplied extension data. A malformed
                    // fragment must not take down the whole export/subscription feed;
                    // skip it for this event and keep going.
                    Log::Error('Failed to parse ExtraIcalLines for reservation %s: %s', $res->ReferenceNumber, $e->getMessage());
                }
            }

            if ($res->StartReminder !== null) {
                $alarm = new VAlarm($vcal, 'VALARM');
                $event->add($alarm);
                $alarm->add('TRIGGER', '-PT' . $res->StartReminder->MinutesPrior() . 'M', ['RELATED' => 'START']);
                $alarm->add('ACTION', 'DISPLAY');
                // Start alarm shows full notes; end alarm shows only the title (short wrap-up reminder).
                $alarm->add('DESCRIPTION', $res->Description);
            }

            if ($res->EndReminder !== null) {
                $alarm = new VAlarm($vcal, 'VALARM');
                $event->add($alarm);
                $alarm->add('TRIGGER', '-PT' . $res->EndReminder->MinutesPrior() . 'M', ['RELATED' => 'END']);
                $alarm->add('ACTION', 'DISPLAY');
                $alarm->add('DESCRIPTION', $res->Summary);
            }
        }

        return $vcal->serialize();
    }
}
