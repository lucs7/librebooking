<?php

use Sabre\VObject\Component\VAlarm;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;

require_once(ROOT_DIR . 'Pages/Page.php');

class CalendarExportDisplay extends Page
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * A single reservation with real attendees is a scheduling request a client can act
     * on (Accept/Decline). Anything else (no attendees, or a multi-event export/subscription
     * feed) stays PUBLISH; see commit dfd28fef ("remove METHOD:REQUEST ... fix Add to Outlook")
     * for why REQUEST without attendee data broke basic calendar import.
     *
     * @param $reservations iCalendarReservationView[]
     * @return string 'REQUEST' or 'PUBLISH'
     */
    public static function DetermineMethod(array $reservations): string
    {
        $hasSingleReservationWithAttendees = count($reservations) === 1 && !empty($reservations[0]->Attendees);
        return $hasSingleReservationWithAttendees ? 'REQUEST' : 'PUBLISH';
    }

    /**
     * @param $reservations iCalendarReservationView[]
     * @param string|null $calendarName Optional display name rendered as X-WR-CALNAME
     * @param string|null $forceMethod Overrides DetermineMethod(), e.g. reservation invite emails
     *                                 always send METHOD:REQUEST regardless of attendee count.
     * @return string
     */
    public function Render(array $reservations, ?string $calendarName = null, ?string $forceMethod = null): string
    {
        $vcal = new VCalendar();
        $vcal->PRODID = '-//LibreBooking//NONSGML ' . Configuration::VERSION . '//EN';
        $vcal->add('METHOD', $forceMethod ?? self::DetermineMethod($reservations));

        if ($calendarName !== null && $calendarName !== '') {
            $vcal->add('NAME', $calendarName);
            $vcal->add('X-WR-CALNAME', $calendarName);
        }

        // ScriptUrl is used to generate iCal UIDs. Avoid slashes per
        // https://bugzilla.mozilla.org/show_bug.cgi?id=465853
        $uid = parse_url(Configuration::Instance()->GetScriptUrl(), PHP_URL_HOST) ?? '';
        $isoFormat = 'Ymd\THis\Z';

        foreach ($reservations as $res) {
            /** @var iCalendarReservationView $res */
            $event = new VEvent($vcal, 'VEVENT');
            $vcal->add($event);
            // VEvent auto-generates UID and DTSTAMP in getDefaults(); use = to replace them.
            $event->UID = $res->ReferenceNumber . '@' . $uid;
            $event->DTSTAMP = $res->DateCreated->Format($isoFormat);
            $event->add('CLASS', $res->Classification);
            $event->add('CREATED', $res->DateCreated->Format($isoFormat));
            $event->add('DESCRIPTION', $res->Description);
            $event->add('DTSTART', $res->DateStart->Format($isoFormat));
            $event->add('DTEND', $res->DateEnd->Format($isoFormat));
            $event->add('LAST-MODIFIED', $res->LastModified->Format($isoFormat));
            $event->add('LOCATION', $res->Location);
            if (!empty($res->OrganizerEmail) && $res->OrganizerEmail !== 'Private') {
                $event->add('ORGANIZER', 'mailto:' . $res->OrganizerEmail, ['CN' => $res->Organizer]);
            }
            foreach ($res->Attendees as $attendee) {
                $event->add('ATTENDEE', 'mailto:' . $attendee['Email'], [
                    'CN' => $attendee['Name'],
                    'ROLE' => 'REQ-PARTICIPANT',
                    'PARTSTAT' => 'NEEDS-ACTION',
                    'RSVP' => 'TRUE',
                ]);
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

    public function PageLoad()
    {
        // no-op
    }
}
