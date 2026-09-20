<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Domain/namespace.php');

class ReservationItemViewTest extends TestBase
{
    public function setUp(): void
    {
        parent::setup();
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testFromReservationViewIncludesParticipantAndInviteeEmails()
    {
        $reservationView = new ReservationView();
        $reservationView->ReferenceNumber = 'ref';
        $reservationView->StartDate = Date::Now();
        $reservationView->EndDate = Date::Now()->AddHours(1);
        $reservationView->Participants = [
            new ReservationUserView(1, 'Part', 'One', 'part1@example.com', ReservationUserLevel::PARTICIPANT),
        ];
        $reservationView->Invitees = [
            new ReservationUserView(2, 'Invite', 'Two', 'invite2@example.com', ReservationUserLevel::INVITEE),
        ];

        $item = ReservationItemView::FromReservationView($reservationView);

        $this->assertEquals('part1@example.com', $item->ParticipantEmails[1]);
        $this->assertEquals('invite2@example.com', $item->InviteeEmails[2]);
        $this->assertEquals('Part One', $item->ParticipantNames[1]);
        $this->assertEquals('Invite Two', $item->InviteeNames[2]);
    }

    public function testFromReservationViewOmitsEmailEntryForUsersWithNoEmailOnFile()
    {
        // Matches the constructor-based Populate() path below, which only ever sets a
        // ParticipantEmails/InviteeEmails entry when the email is non-empty. Without this,
        // the two construction paths disagree on whether "no email" means the key is absent
        // or present with an empty string.
        $reservationView = new ReservationView();
        $reservationView->ReferenceNumber = 'ref';
        $reservationView->StartDate = Date::Now();
        $reservationView->EndDate = Date::Now()->AddHours(1);
        $reservationView->Participants = [
            new ReservationUserView(1, 'Part', 'One', '', ReservationUserLevel::PARTICIPANT),
        ];
        $reservationView->Invitees = [
            new ReservationUserView(2, 'Invite', 'Two', '', ReservationUserLevel::INVITEE),
        ];

        $item = ReservationItemView::FromReservationView($reservationView);

        $this->assertArrayNotHasKey(1, $item->ParticipantEmails);
        $this->assertArrayNotHasKey(2, $item->InviteeEmails);
    }

    public function testFromReservationViewIncludesParticipatingAndInvitedGuests()
    {
        // CalendarExportPresenter uses FromReservationView() for the single-reservation
        // export, and iCalendarReservationView::BuildAttendees() reads ParticipatingGuests/
        // InvitedGuests, so guest attendees must survive this conversion just like
        // registered participants/invitees do above.
        $reservationView = new ReservationView();
        $reservationView->ReferenceNumber = 'ref';
        $reservationView->StartDate = Date::Now();
        $reservationView->EndDate = Date::Now()->AddHours(1);
        $reservationView->ParticipatingGuests = ['guest1@example.com'];
        $reservationView->InvitedGuests = ['guest2@example.com'];

        $item = ReservationItemView::FromReservationView($reservationView);

        $this->assertEquals(['guest1@example.com'], $item->ParticipatingGuests);
        $this->assertEquals(['guest2@example.com'], $item->InvitedGuests);
    }

    public function testConstructorParsesParticipantAndInviteeListsIncludingEmail()
    {
        // This is the format produced by Queries::$SELECT_LIST_FRAGMENT's GROUP_CONCAT and
        // consumed by ReservationItemView::Populate($row) for every list/subscription query —
        // distinct from FromReservationView(), which is only used for single-reservation export.
        $item = new ReservationItemView(
            'ref',
            Date::Now(),
            Date::Now()->AddHours(1),
            'Resource',
            1,
            1,
            null,
            'title',
            'description',
            1,
            'Owner',
            'Person',
            1,
            null,
            null,
            null,
            '1=Part One=part1@example.com',
            '2=Invite Two=invite2@example.com',
            null,
            null
        );

        $this->assertEquals('part1@example.com', $item->ParticipantEmails[1]);
        $this->assertEquals('invite2@example.com', $item->InviteeEmails[2]);
        $this->assertEquals('Part One', $item->ParticipantNames[1]);
        $this->assertEquals('Invite Two', $item->InviteeNames[2]);
    }
}
