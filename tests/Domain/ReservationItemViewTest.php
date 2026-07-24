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
}
