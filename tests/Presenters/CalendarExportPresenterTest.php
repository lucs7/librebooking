<?php

declare(strict_types=1);

use LibreBooking\Calendar\IcsMethod;

require_once(ROOT_DIR . 'Pages/Export/CalendarExportPage.php');
require_once(ROOT_DIR . 'Presenters/CalendarExportPresenter.php');

class CalendarExportPresenterTest extends TestBase
{
    /**
     * @var IReservationViewRepository|PHPUnit\Framework\MockObject\MockObject
     */
    private $repo;

    /**
     * @var ICalendarExportPage|PHPUnit\Framework\MockObject\MockObject
     */
    private $page;

    /**
     * @var CalendarExportPresenter
     */
    private $presenter;

    /**
     * @var ICalendarExportValidator|PHPUnit\Framework\MockObject\MockObject
     */
    private $validator;

    /**
     * @var FakePrivacyFilter
     */
    private $privacyFilter;

    public function setUp(): void
    {
        parent::setup();

        $this->repo = $this->createMock('IReservationViewRepository');
        $this->page = $this->createMock('ICalendarExportPage');
        $this->validator = $this->createMock('ICalendarExportValidator');
        $this->privacyFilter = new FakePrivacyFilter();

        $this->presenter = new CalendarExportPresenter($this->page, $this->repo, $this->validator, $this->privacyFilter);
    }

    public function testLoadsReservationByReferenceNumber()
    {
        $referenceNumber = 'ref';
        $reservationResult = new ReservationView();

        $this->validator->expects($this->atLeastOnce())
                ->method('IsValid')
                ->willReturn(true);

        $this->page->expects($this->once())
                ->method('GetReferenceNumber')
                ->willReturn($referenceNumber);

        $this->repo->expects($this->once())
                ->method('GetReservationForEditing')
                ->with($this->equalTo($referenceNumber))
                ->willReturn($reservationResult);

        $this->page->expects($this->once())
                ->method('SetReservations')
                ->with($this->arrayHasKey(0));

        $this->presenter->PageLoad($this->fakeUser);
    }

    public function testCannotSeeReservationDetailsIfConfiguredOff()
    {
        $referenceNumber = 'ref';
        $reservationResult = new ReservationView();

        $this->validator->expects($this->atLeastOnce())
                ->method('IsValid')
                ->willReturn(true);

        $this->page->expects($this->once())
                ->method('GetReferenceNumber')
                ->willReturn($referenceNumber);

        $this->repo->expects($this->once())
                ->method('GetReservationForEditing')
                ->with($this->equalTo($referenceNumber))
                ->willReturn($reservationResult);

        $this->page->expects($this->once())
                ->method('SetReservations')
                ->with($this->arrayHasKey(0));

        $this->presenter->PageLoad($this->fakeUser);
    }

    public function testOrganizerIsTheReservationOwnerWithSentByAsTheConfiguredDefaultAddress()
    {
        // ORGANIZER identifies the reservation owner — the actual user the reservation
        // belongs to — while SENT-BY (RFC 5545 §3.2.18) names the site's own configured
        // address, since the system technically sent this message on the owner's behalf.
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS, 'bookings@example.com');
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_NAME, 'Example Bookings');

        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->OwnerId = $user->UserId;
        $res->OwnerFirstName = 'f';
        $res->OwnerLastName = 'l';
        $res->OwnerEmailAddress = 'e@m.com';

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $this->assertEquals('e@m.com', $reservationView->OrganizerEmail);
        $this->assertEquals('f l', $reservationView->Organizer);
        $this->assertEquals('bookings@example.com', $reservationView->OrganizerSentBy);
    }

    public function testOrganizerFallsBackToTheDefaultAddressWithNoSentByWhenPrivacyHidesTheOwner()
    {
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS, 'bookings@example.com');
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_NAME, 'Example Bookings');

        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->OwnerId = $user->UserId;
        $res->OwnerFirstName = 'f';
        $res->OwnerLastName = 'l';
        $res->OwnerEmailAddress = 'e@m.com';

        $this->privacyFilter->_CanViewUser = false;
        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals('bookings@example.com', $reservationView->OrganizerEmail);
        $this->assertEquals('Private', $reservationView->Organizer);
        // No real identity is shown, so there's nothing for the site's address to be
        // "sent by" on behalf of.
        $this->assertNull($reservationView->OrganizerSentBy);
    }

    public function testOrganizerIsOmittedFromRenderedOutputWhenOwnerHasNoEmailOnFile()
    {
        // A user record with no email on file participates in a reservation. ORGANIZER
        // has nothing to point at in that case, so it must be omitted rather than
        // rendering an invalid empty "mailto:".
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringNotContainsString('ORGANIZER', $ics);
    }

    public function testOwnerAppearsAsChairAttendeeWithRsvpFalse()
    {
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS, 'bookings@example.com');

        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->OwnerId = $user->UserId;
        $res->OwnerFirstName = 'f';
        $res->OwnerLastName = 'l';
        $res->OwnerEmailAddress = 'e@m.com';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;
        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertCount(1, $reservationView->Attendees);
        $chair = $reservationView->Attendees[0];
        $this->assertEquals('e@m.com', $chair['Email']);
        $this->assertTrue($chair['IsChair']);

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView], null, IcsMethod::REQUEST);
        $unfolded = str_replace("\r\n ", '', $ics);

        // ORGANIZER is the same owner identity as the CHAIR attendee, marked SENT-BY the
        // site's own address (RFC 5545 §3.2.18).
        $this->assertMatchesRegularExpression('/ORGANIZER;CN=f l;SENT-BY="mailto:bookings@example\.com":mailto:e@m\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:e@m\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*ROLE=CHAIR[^\r\n]*mailto:e@m\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*PARTSTAT=ACCEPTED[^\r\n]*mailto:e@m\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*RSVP=FALSE[^\r\n]*mailto:e@m\.com/', $unfolded);
    }

    public function testViewHidesDetailsWhenNoAccess()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();

        $this->privacyFilter->_CanViewDetails = false;
        $this->privacyFilter->_CanViewUser = false;
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS, 'noreply@example.com');
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_NAME, 'LB');

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals($user, $this->privacyFilter->_LastViewDetailsUserSession);
        $this->assertEquals($user, $this->privacyFilter->_LastViewUserUserSession);

        $this->assertEquals($res, $this->privacyFilter->_LastViewDetailsReservation);
        $this->assertEquals($res, $this->privacyFilter->_LastViewUserReservation);

        // Privacy hides the owner's real identity: ORGANIZER falls back to the site's
        // configured default address with no SENT-BY, since there's no real person left
        // to attribute it to.
        $this->assertEquals('Private', $reservationView->Organizer);
        $this->assertEquals('noreply@example.com', $reservationView->OrganizerEmail);
        $this->assertNull($reservationView->OrganizerSentBy);
        $this->assertEquals('Private', $reservationView->Summary);
        $this->assertEquals('Private', $reservationView->Description);
    }

    public function testViewShowsFormattedSummaryWhenDetailsVisible()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->UserId = $user->UserId;
        $res->UserLevelId = ReservationUserLevel::OWNER;
        $res->Title = 'My Booking Title';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->OwnerFirstName = 'Test';
        $res->OwnerLastName = 'User';
        $res->OwnerEmailAddress = 'test@example.com';

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');

        $this->assertEquals('My Booking Title', $reservationView->Summary);
    }

    public function testViewShowsDescriptionFromReservationNotesWhenDetailsVisible()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->Description = 'Booking notes';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals('Booking notes', $reservationView->Description);
    }

    public function testAnonymousUserSeesPrivateWhenPublicReservationViewingIsDisabled()
    {
        $user = new NullUserSession();
        $res = new ReservationItemView();
        $res->OwnerId = 42;
        $res->OwnerFirstName = 'Alice';
        $res->OwnerLastName = 'Smith';
        $res->OwnerEmailAddress = 'alice@example.com';
        $res->Title = 'Secret title';
        $res->Description = 'Secret notes';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        // privacy.view.reservations=false (default) means anonymous users must not see any details
        $this->fakeConfig->SetKey(ConfigKeys::PRIVACY_VIEW_RESERVATIONS, false);
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS, 'noreply@example.com');
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_NAME, 'LB');
        $this->privacyFilter->_CanViewDetails = true;
        $this->privacyFilter->_CanViewUser = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals('Private', $reservationView->Summary);
        $this->assertEquals('Private', $reservationView->Description);
        // The anonymous-viewer override forces CanViewUser false too, so ORGANIZER falls
        // back to the site's configured default address with no real identity/SENT-BY.
        $this->assertEquals('Private', $reservationView->Organizer);
        $this->assertEquals('noreply@example.com', $reservationView->OrganizerEmail);
        $this->assertNull($reservationView->OrganizerSentBy);
    }

    public function testViewStoresRawTextInSummaryAndDescription()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->UserId = $user->UserId;
        $res->UserLevelId = ReservationUserLevel::OWNER;
        $res->Title = "First line\r\nSecond line\nThird line";
        $res->Description = "Alpha\r\nBeta\nGamma";
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');

        // View stores raw values; RFC 5545 escaping is handled by Sabre at serialization time.
        $this->assertEquals("First line\r\nSecond line\nThird line", $reservationView->Summary);
        $this->assertEquals("Alpha\r\nBeta\nGamma", $reservationView->Description);
    }

    public function testViewStoresRawSpecialCharactersInSummaryAndDescription()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->UserId = $user->UserId;
        $res->UserLevelId = ReservationUserLevel::OWNER;
        $res->Title = 'x\\y;z,w';
        $res->Description = 'a\\b;c,d';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');

        // View stores raw values; Sabre escapes backslash, semicolon, and comma during serialization.
        $this->assertEquals('x\\y;z,w', $reservationView->Summary);
        $this->assertEquals('a\\b;c,d', $reservationView->Description);
    }

    public function testSerializedOutputEscapesRFC5545ReservedCharactersInDescriptionAndSummary()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->UserId = $user->UserId;
        $res->UserLevelId = ReservationUserLevel::OWNER;
        $res->Title = 'Title; with, reserved\\ chars';
        $res->Description = 'Desc; with, reserved\\ chars';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->OwnerId = $user->UserId + 1;
        $res->OwnerEmailAddress = 'owner@example.com';

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');
        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        // RFC 5545 §3.3.11: backslash, comma, semicolon are reserved in TEXT values.
        $this->assertStringContainsString('SUMMARY:Title\; with\, reserved\\\\ chars', $ics);
        $this->assertStringContainsString('DESCRIPTION:Desc\; with\, reserved\\\\ chars', $ics);
    }

    public function testCalendarExportProdIdUsesApplicationVersionInsteadOfConfigValue()
    {
        $this->fakeConfig->SetKey('version', '9.9.9-user-config');
        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';

        $display = new CalendarExportDisplay();
        $calendar = $display->Render([]);

        $this->assertStringContainsString(
            'PRODID:-//LibreBooking//NONSGML ' . Configuration::VERSION . '//EN',
            $calendar
        );
        $this->assertStringNotContainsString('9.9.9-user-config', $calendar);
    }

    public function testCalendarNameIsRenderedAsNameAndXWrCalname()
    {
        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';

        $display = new CalendarExportDisplay();
        $calendar = $display->Render([], 'Engineering Schedule');

        // NAME: is checked with line boundaries since X-WR-CALNAME: also contains "NAME:" as a substring.
        $this->assertStringContainsString("\r\nNAME:Engineering Schedule\r\n", $calendar);
        $this->assertStringContainsString('X-WR-CALNAME:Engineering Schedule', $calendar);
    }

    public function testCalendarNameIsOmittedWhenNotProvided()
    {
        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';

        $display = new CalendarExportDisplay();
        $calendar = $display->Render([]);

        $this->assertStringNotContainsString('NAME:', $calendar);
        $this->assertStringNotContainsString('X-WR-CALNAME:', $calendar);
    }

    public function testExtraIcalLinesPreservesPropertyParametersAndNestedComponents()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->UserId = $user->UserId;
        $res->UserLevelId = ReservationUserLevel::OWNER;
        $res->Title = 'Title';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');
        // ATTENDEE carries parameters; VALARM is a nested component. Both must round-trip
        // through Reader::read() rather than becoming malformed flat text. Rendered as
        // REQUEST since a PUBLISH render strips ATTENDEE from ExtraIcalLines (see
        // testPublishRenderStripsAttendeePropertiesInjectedViaExtraIcalLines below).
        $reservationView->ExtraIcalLines = "ATTENDEE;CN=JaneDoe;ROLE=REQ-PARTICIPANT:mailto:jane@example.com\r\n"
            . "BEGIN:VALARM\r\nACTION:AUDIO\r\nTRIGGER:-PT15M\r\nEND:VALARM";

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView], null, IcsMethod::REQUEST);

        $this->assertStringContainsString('ATTENDEE;CN=JaneDoe;ROLE=REQ-PARTICIPANT:mailto:jane@example.com', $ics);
        $this->assertStringContainsString('BEGIN:VALARM', $ics);
        $this->assertStringContainsString('ACTION:AUDIO', $ics);
    }

    public function testPublishRenderStripsAttendeePropertiesInjectedViaExtraIcalLines()
    {
        // RFC 5546 §3.2.1: a PUBLISH VEVENT's ATTENDEE list MUST be empty. ExtraIcalLines is
        // plugin-supplied and could otherwise smuggle attendee data past the PUBLISH guard
        // that already covers $res->Attendees.
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->Title = 'Title';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');
        $reservationView->ExtraIcalLines = "ATTENDEE;CN=JaneDoe;ROLE=REQ-PARTICIPANT:mailto:jane@example.com\r\n"
            . "BEGIN:VALARM\r\nACTION:AUDIO\r\nTRIGGER:-PT15M\r\nEND:VALARM";

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('ATTENDEE', $ics);
        // Non-attendee ExtraIcalLines content must still survive the PUBLISH filter.
        $this->assertStringContainsString('BEGIN:VALARM', $ics);
        $this->assertStringContainsString('ACTION:AUDIO', $ics);
    }

    public function testExtraIcalLinesSupportsRfc5545FoldedContinuationLines()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->UserId = $user->UserId;
        $res->UserLevelId = ReservationUserLevel::OWNER;
        $res->Title = 'Title';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter, '{title}');
        // RFC 5545 §3.1: a line starting with a single space is a folded continuation
        // of the previous line, joined without the leading space.
        $reservationView->ExtraIcalLines = "X-CUSTOM-FIELD:Hello\r\n World";

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringContainsString('X-CUSTOM-FIELD:HelloWorld', $ics);
    }

    public function testMalformedExtraIcalLinesIsSkippedWithoutBreakingTheExport()
    {
        $user = new FakeUserSession();

        $goodRes = new ReservationItemView();
        $goodRes->UserId = $user->UserId;
        $goodRes->UserLevelId = ReservationUserLevel::OWNER;
        $goodRes->ReferenceNumber = 'good-ref';
        $goodRes->Title = 'Good reservation';
        $goodRes->StartDate = Date::Now();
        $goodRes->EndDate = Date::Now()->AddHours(1);

        $badRes = new ReservationItemView();
        $badRes->UserId = $user->UserId;
        $badRes->UserLevelId = ReservationUserLevel::OWNER;
        $badRes->ReferenceNumber = 'bad-ref';
        $badRes->Title = 'Bad reservation';
        $badRes->StartDate = Date::Now();
        $badRes->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $goodView = new iCalendarReservationView($goodRes, $user, $this->privacyFilter, '{title}');
        $badView = new iCalendarReservationView($badRes, $user, $this->privacyFilter, '{title}');
        // Not valid iCalendar syntax: no property name/value separator.
        $badView->ExtraIcalLines = 'this is not a valid ical line';

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$goodView, $badView]);

        // The malformed fragment on one reservation must not prevent the other
        // reservation (or the rest of the malformed one's own properties) from rendering.
        $this->assertStringContainsString('good-ref', $ics);
        $this->assertStringContainsString('bad-ref', $ics);
    }

    public function testAttendeesAreOmittedWhenPrivacyFilteringHidesUserDetailsEvenIfReservationDetailsAreVisible()
    {
        // CanViewDetails and CanViewUser are independent privacy.hide.* settings. Attendee
        // names/emails are user identity, not reservation detail, so a viewer who can see
        // the reservation's details but not user identity must not get attendee PII either.
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->ParticipantIds = [2];
        $res->ParticipantNames = [2 => 'Part One'];
        $res->ParticipantEmails = [2 => 'part1@example.com'];

        $privacyFilter = new FakePrivacyFilter();
        $privacyFilter->_CanViewDetails = true;
        $privacyFilter->_CanViewUser = false;

        $reservationView = new iCalendarReservationView($res, $user, $privacyFilter);

        $this->assertEmpty($reservationView->Attendees);
    }

    public function testSingleReservationWithExplicitRequestMethodListsAttendees()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->ParticipantIds = [2];
        $res->ParticipantNames = [2 => 'Part One'];
        $res->ParticipantEmails = [2 => 'part1@example.com'];

        $this->privacyFilter->_CanViewDetails = true;
        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        // REQUEST is only ever forced explicitly by a notification email addressed to a
        // known attendee (see ReservationEmailMessage::GetIcsMethod()) — never inferred.
        $ics = $display->Render([$reservationView], null, IcsMethod::REQUEST);
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:part1@example\.com/', $unfolded);
        // Participants have already accepted — PARTSTAT must be ACCEPTED, not NEEDS-ACTION.
        $this->assertStringContainsString('PARTSTAT=ACCEPTED', $ics);
        $this->assertStringContainsString('RSVP=FALSE', $ics);
    }

    public function testSingleReservationExportWithAttendeesDefaultsToPublishAndOmitsAttendees()
    {
        // A pull-based export/subscription-feed render (no forceMethod) is never a
        // scheduling action addressed to a specific attendee, even when it happens to
        // contain exactly one reservation that has real attendees on it — it must stay
        // PUBLISH and must not leak ATTENDEE data.
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->ParticipantIds = [2];
        $res->ParticipantNames = [2 => 'Part One'];
        $res->ParticipantEmails = [2 => 'part1@example.com'];

        $this->privacyFilter->_CanViewDetails = true;
        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('ATTENDEE', $ics);
    }

    public function testMultipleReservationsWithAttendeesStayPublish()
    {
        $user = new FakeUserSession();

        $res1 = new ReservationItemView();
        $res1->ReferenceNumber = 'ref-1';
        $res1->StartDate = Date::Now();
        $res1->EndDate = Date::Now()->AddHours(1);
        $res1->ParticipantIds = [2];
        $res1->ParticipantNames = [2 => 'Part One'];
        $res1->ParticipantEmails = [2 => 'part1@example.com'];

        $res2 = new ReservationItemView();
        $res2->ReferenceNumber = 'ref-2';
        $res2->StartDate = Date::Now();
        $res2->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;
        $view1 = new iCalendarReservationView($res1, $user, $this->privacyFilter);
        $view2 = new iCalendarReservationView($res2, $user, $this->privacyFilter);

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$view1, $view2]);

        // RFC 5546 §3.2.1: a PUBLISH VEVENT's ATTENDEE property list MUST be empty, since
        // PUBLISH doesn't solicit a reply. A multi-event feed always defaults to PUBLISH,
        // so it must never carry ATTENDEE data even when one of its reservations has real
        // attendees.
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('ATTENDEE', $ics);
    }
}
