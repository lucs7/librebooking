<?php

use LibreBooking\Calendar\IcsMethod;
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
     * @param iCalendarReservationView[] $reservations
     * @param string|null $calendarName Optional display name rendered as X-WR-CALNAME
     * @param IcsMethod $method PUBLISH by default. Pull-based exports and subscription feeds
     *                          (CalendarExportPage, CalendarSubscriptionPage) always use the
     *                          PUBLISH default. Reservation notification emails
     *                          (see ReservationEmailMessage::GetIcsMethod()) use PUBLISH for
     *                          create/update and CANCEL for deletion — never REQUEST, since
     *                          this codebase never emits ATTENDEE data for a REQUEST to solicit
     *                          a response from.
     */
    public function Render(array $reservations, ?string $calendarName = null, IcsMethod $method = IcsMethod::PUBLISH): string
    {
        // Values passed as constructor children are merged over getDefaults(),
        // replacing the PRODID that VCalendar would otherwise generate.
        $vcal = new VCalendar([
            'PRODID' => '-//LibreBooking//NONSGML ' . Configuration::VERSION . '//EN',
            'METHOD' => $method->value,
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
                $organizerParams = ['CN' => $res->Organizer];
                if (!empty($res->OrganizerSentBy)) {
                    // RFC 5545 §3.2.18: SENT-BY names the calendar user acting on the
                    // organizer's behalf — the site's own address, technically responsible
                    // for delivering this message.
                    $organizerParams['SENT-BY'] = 'mailto:' . $res->OrganizerSentBy;
                }
                $event->add('ORGANIZER', 'mailto:' . $res->OrganizerEmail, $organizerParams);
            }
            $event->add('STATUS', $res->IsCancelled ? 'CANCELLED' : ($res->IsPending ? 'TENTATIVE' : 'CONFIRMED'));
            $event->add('SUMMARY', $res->Summary);
            // RFC 5546 §3.2.5: CANCEL must carry a SEQUENCE strictly greater than the last
            // REQUEST for the same UID so calendar clients know to apply it. Since LibreBooking
            // always emits SEQUENCE:0 on REQUEST, CANCEL uses 1.
            $event->add('SEQUENCE', $method === IcsMethod::CANCEL ? 1 : 0);
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

            // RFC 5546 §3.2.1: a PUBLISH VEVENT's ATTENDEE list MUST be empty. LibreBooking
            // doesn't add ATTENDEE itself; this only guards against plugin-supplied
            // ExtraIcalLines carrying one.
            if ($method === IcsMethod::PUBLISH) {
                $event->remove('ATTENDEE');
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

    public function PageLoad()
    {
        // no-op
    }
}
