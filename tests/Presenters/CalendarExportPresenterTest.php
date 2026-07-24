<?php

declare(strict_types=1);

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

    public function testOrganizerIsOwnerIfCurrentUserIsNotOrganizer()
    {
        // this fixes a bug in outlook which prevents you from adding a meeting that you are the organizer of
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->OwnerId = $user->UserId + 1;
        $res->OwnerFirstName = 'f';
        $res->OwnerLastName = 'l';
        $res->OwnerEmailAddress = 'e@m.com';

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $this->assertEquals($res->OwnerEmailAddress, $reservationView->OrganizerEmail);
        $fullName = new FullName($res->OwnerFirstName, $res->OwnerLastName);
        $this->assertEquals($fullName->__toString(), $reservationView->Organizer);
    }

    public function testOrganizerIsRealAddressEvenIfCurrentUserIsOrganizer()
    {
        // Previously this address was mangled (e.g. "e-noreply@m.com") to work around an
        // old Outlook quirk with self-organized events. Issue #1336 reported that this made
        // the ORGANIZER look like an invalid/synthesized address. Now that ATTENDEE lines
        // are emitted for participants/invitees, the real organizer address is used instead.
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->OwnerId = $user->UserId;
        $res->OwnerFirstName = 'f';
        $res->OwnerLastName = 'l';
        $res->OwnerEmailAddress = 'e@m.com';

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $this->assertEquals('e@m.com', $reservationView->OrganizerEmail);
        $fullName = new FullName($res->OwnerFirstName, $res->OwnerLastName);
        $this->assertEquals($fullName->__toString(), $reservationView->Organizer);
    }

    public function testAttendeesIncludeParticipantsInviteesAndGuests()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->ParticipantIds = [1];
        $res->ParticipantNames = [1 => 'Part One'];
        $res->ParticipantEmails = [1 => 'part1@example.com'];
        $res->InviteeIds = [2];
        $res->InviteeNames = [2 => 'Invite Two'];
        $res->InviteeEmails = [2 => 'invite2@example.com'];
        $res->ParticipatingGuests = ['guest1@example.com'];
        $res->InvitedGuests = ['guest2@example.com'];

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals([
            ['Email' => 'part1@example.com', 'Name' => 'Part One'],
            ['Email' => 'invite2@example.com', 'Name' => 'Invite Two'],
            ['Email' => 'guest1@example.com', 'Name' => 'guest1@example.com'],
            ['Email' => 'guest2@example.com', 'Name' => 'guest2@example.com'],
        ], $reservationView->Attendees);
    }

    public function testAttendeesAreOmittedWhenDetailsAreNotVisible()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->ParticipantIds = [1];
        $res->ParticipantNames = [1 => 'Part One'];
        $res->ParticipantEmails = [1 => 'part1@example.com'];

        $this->privacyFilter->_CanViewDetails = false;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals([], $reservationView->Attendees);
    }

    public function testViewHidesDetailsWhenNoAccess()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();

        $this->privacyFilter->_CanViewDetails = false;
        $this->privacyFilter->_CanViewUser = false;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals($user, $this->privacyFilter->_LastViewDetailsUserSession);
        $this->assertEquals($user, $this->privacyFilter->_LastViewUserUserSession);

        $this->assertEquals($res, $this->privacyFilter->_LastViewDetailsReservation);
        $this->assertEquals($res, $this->privacyFilter->_LastViewUserReservation);

        $this->assertEquals('Private', $reservationView->Organizer);
        $this->assertEquals('Private', $reservationView->OrganizerEmail);
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
        $this->privacyFilter->_CanViewDetails = true;
        $this->privacyFilter->_CanViewUser = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);

        $this->assertEquals('Private', $reservationView->Summary);
        $this->assertEquals('Private', $reservationView->Description);
        $this->assertEquals('Private', $reservationView->Organizer);
        $this->assertEquals('Private', $reservationView->OrganizerEmail);
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

    public function testUidUsesAtSignRatherThanAmpersand()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->ReferenceNumber = 'abc123';
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        // RFC 5545 §3.8.4.7 UIDs conventionally follow the <unique>@<host> form so that
        // clients can reliably treat them as globally-unique identifiers.
        $this->assertStringContainsString('UID:abc123@example.com', $ics);
        $this->assertStringNotContainsString('abc123&example.com', $ics);
    }

    public function testAttendeeLinesAreRenderedForParticipantsInviteesAndGuests()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->ParticipantIds = [1];
        $res->ParticipantNames = [1 => 'Part One'];
        $res->ParticipantEmails = [1 => 'part1@example.com'];
        $res->InvitedGuests = ['guest@example.com'];

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);
        // RFC 5545 §3.1 folds lines longer than 75 octets; unfold before matching.
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:part1@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:guest@example\.com/', $unfolded);
        $this->assertStringContainsString('CN=Part One', $ics);
        $this->assertStringContainsString('CN=guest@example.com', $ics);
        $this->assertStringContainsString('ROLE=REQ-PARTICIPANT', $ics);
        $this->assertStringContainsString('PARTSTAT=NEEDS-ACTION', $ics);
        $this->assertStringContainsString('RSVP=TRUE', $ics);
    }

    public function testMethodIsRequestForSingleReservationWithAttendees()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);
        $res->ParticipantIds = [1];
        $res->ParticipantNames = [1 => 'Part One'];
        $res->ParticipantEmails = [1 => 'part1@example.com'];

        $this->privacyFilter->_CanViewDetails = true;

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertStringNotContainsString('METHOD:PUBLISH', $ics);
    }

    public function testMethodIsPublishWhenThereAreNoAttendees()
    {
        $user = new FakeUserSession();
        $res = new ReservationItemView();
        $res->StartDate = Date::Now();
        $res->EndDate = Date::Now()->AddHours(1);

        $reservationView = new iCalendarReservationView($res, $user, $this->privacyFilter);
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('METHOD:REQUEST', $ics);
    }

    public function testMethodIsPublishForMultipleReservationsEvenWithAttendees()
    {
        $user = new FakeUserSession();
        $res1 = new ReservationItemView();
        $res1->StartDate = Date::Now();
        $res1->EndDate = Date::Now()->AddHours(1);
        $res1->ParticipantIds = [1];
        $res1->ParticipantEmails = [1 => 'part1@example.com'];
        $res2 = new ReservationItemView();
        $res2->StartDate = Date::Now();
        $res2->EndDate = Date::Now()->AddHours(1);

        $this->privacyFilter->_CanViewDetails = true;

        $view1 = new iCalendarReservationView($res1, $user, $this->privacyFilter);
        $view2 = new iCalendarReservationView($res2, $user, $this->privacyFilter);

        $display = new CalendarExportDisplay();
        $ics = $display->Render([$view1, $view2]);

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('METHOD:REQUEST', $ics);
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
        // through Reader::read() rather than becoming malformed flat text.
        $reservationView->ExtraIcalLines = "ATTENDEE;CN=JaneDoe;ROLE=REQ-PARTICIPANT:mailto:jane@example.com\r\n"
            . "BEGIN:VALARM\r\nACTION:AUDIO\r\nTRIGGER:-PT15M\r\nEND:VALARM";

        $this->fakeConfig->_ScriptUrl = 'https://example.com/Web';
        $display = new CalendarExportDisplay();
        $ics = $display->Render([$reservationView]);

        $this->assertStringContainsString('ATTENDEE;CN=JaneDoe;ROLE=REQ-PARTICIPANT:mailto:jane@example.com', $ics);
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
}
