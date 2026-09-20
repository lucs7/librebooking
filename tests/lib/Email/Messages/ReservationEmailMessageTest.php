<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationEmailMessageTest extends TestBase
{
    public function setUp(): void
    {
        parent::setup();

        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS, 'bookings@example.com');
        $this->fakeConfig->SetKey(ConfigKeys::EMAIL_DEFAULT_FROM_NAME, 'Example Bookings');
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testIcsAttachmentHasNoAttendeesForPublishEmailsEvenWhenParticipantsExist()
    {
        // RFC 5546 §3.2.1: ATTENDEE MUST NOT appear in a PUBLISH VEVENT. This exercises
        // the base ReservationEmailMessage's PUBLISH default directly (see
        // testReservationCreatedIcsAttachmentUsesRequestMethodAndListsOwnerAsChair below
        // for the concrete owner-facing classes, which override to REQUEST instead).
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $participantId = 2;
        $inviteeId = 3;

        $userRepo = new FakeUserRepository();
        $userRepo->_UserDtos[$participantId] = new UserDto($participantId, 'Part', 'One', 'part1@example.com');
        $userRepo->_UserDtos[$inviteeId] = new UserDto($inviteeId, 'Invite', 'Two', 'invite2@example.com');

        $instance = new TestReservation();
        $instance->WithParticipant($participantId);
        $instance->WithInvitee($inviteeId);
        $instance->WithParticipatingGuest('guest1@example.com');
        $instance->WithInvitedGuest('guest2@example.com');

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationEmailMessage($owner, $series, null, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=PUBLISH', $message->AttachmentMimeType());
        $this->assertStringNotContainsString('ATTENDEE', $ics);
        // ORGANIZER identifies the real reservation owner, SENT-BY (RFC 5545 §3.2.18) names
        // the site's own configured address since it technically sent this message.
        $this->assertStringContainsString('ORGANIZER;CN=Owner Person;SENT-BY="mailto:bookings@example.com":mailto:owner@example.com', $unfolded);
    }

    public function testIcsAttachmentIsPublishWhenReservationHasNoParticipants()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationEmailMessage($owner, $series, null, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('ATTENDEE', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=PUBLISH', $message->AttachmentMimeType());
    }

    public function testDeletedReservationIcsAttachmentUsesCancelMethodAndCancelledStatus()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationDeletedEmail($owner, $series, null, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
        // RFC 5546 §3.2.5: CANCEL SEQUENCE must exceed the original REQUEST's SEQUENCE (0).
        $this->assertStringContainsString('SEQUENCE:1', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=CANCEL', $message->AttachmentMimeType());
    }

    public function testSharedReservationIcsAttachmentUsesPublishMethod()
    {
        // The share recipient is an arbitrary external address, not represented
        // anywhere in the attendee list (not the owner/CHAIR, not a participant/
        // invitee/guest), so PUBLISH is the only method that fits — and per RFC 5546
        // §3.2.1, that means no ATTENDEE data at all, even though the reservation
        // owner is set on the message.
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationShareEmail($owner, 'friend@example.com', $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('STATUS:CANCELLED', $ics);
        $this->assertStringNotContainsString('ATTENDEE', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=PUBLISH', $message->AttachmentMimeType());
    }

    public function testReservationCreatedIcsAttachmentUsesRequestMethodAndListsOwnerAsChair()
    {
        // The owner is represented in the attendee list as CHAIR (RSVP=FALSE), so
        // ReservationCreatedEmail uses REQUEST rather than the PUBLISH base default —
        // otherwise the owner's own calendar entry would lose the real
        // participant/invitee list that PopulateIcsAttachment() built for them.
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $participantId = 2;
        $userRepo = new FakeUserRepository();
        $userRepo->_UserDtos[$participantId] = new UserDto($participantId, 'Part', 'One', 'part1@example.com');

        $instance = new TestReservation();
        $instance->WithParticipant($participantId);

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationCreatedEmail($owner, $series, null, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=REQUEST', $message->AttachmentMimeType());
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*ROLE=CHAIR[^\r\n]*mailto:owner@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*RSVP=FALSE[^\r\n]*mailto:owner@example\.com/', $unfolded);
        // The real point of this change: the owner's own calendar entry must still
        // show who else is on the reservation.
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:part1@example\.com/', $unfolded);
    }

    public function testReservationCreatedIcsAttachmentUsesPublishMethodWhenNoAttendees()
    {
        // The owner is always listed as CHAIR, but with no participants/invitees/guests
        // there's no one else to REQUEST a response from — PUBLISH is correct here so
        // the owner's own mail client doesn't treat their own booking confirmation as
        // an invitation awaiting a reply.
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationCreatedEmail($owner, $series, null, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('ATTENDEE', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=PUBLISH', $message->AttachmentMimeType());
    }

    public function testInviteeAddedIcsAttachmentUsesRequestMethod()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $inviteeId = 2;
        $invitee = new FakeUser($inviteeId, 'invitee@example.com');
        $invitee->ChangeName('Invitee', 'Person');

        $instance = new TestReservation();
        $instance->WithInvitee($inviteeId);

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $userRepo->_UserDtos[$inviteeId] = new UserDto($inviteeId, 'Invitee', 'Person', 'invitee@example.com');
        $attributeRepo = new FakeAttributeRepository();

        // This attachment is addressed to the invitee being invited — a genuine ATTENDEE
        // asked to accept/decline — so it must use REQUEST, unlike the owner-facing emails.
        $message = new TestInviteeAddedEmail($owner, $invitee, $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=REQUEST', $message->AttachmentMimeType());
    }

    public function testGuestAddedIcsAttachmentUsesRequestMethod()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $instance = new TestReservation();
        $instance->WithParticipatingGuest('guest@example.com');

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestGuestAddedEmail($owner, 'guest@example.com', $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=REQUEST', $message->AttachmentMimeType());
    }

    public function testInviteeRemovedIcsAttachmentListsTheRemovedInviteeAsAttendee()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $inviteeId = 2;
        $invitee = new FakeUser($inviteeId, 'invitee@example.com');
        $invitee->ChangeName('Invitee', 'Person');

        // The invitee has already been removed from the reservation by the time
        // this CANCEL notification fires, so the current instance no longer lists
        // them as an invitee.
        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestInviteeRemovedEmail($owner, $invitee, $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();
        // RFC 5545 §3.1 folds lines longer than 75 octets; unfold before matching.
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:invitee@example\.com/', $unfolded);
    }

    public function testInviteeAddedIcsAttachmentUsesRealOrganizerAndListsOwnerAsChair()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $inviteeId = 2;
        $invitee = new FakeUser($inviteeId, 'invitee@example.com');
        $invitee->ChangeName('Invitee', 'Person');

        $instance = new TestReservation();
        $instance->WithInvitee($inviteeId);

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $userRepo->_UserDtos[$inviteeId] = new UserDto($inviteeId, 'Invitee', 'Person', 'invitee@example.com');
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestInviteeAddedEmail($owner, $invitee, $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();
        $unfolded = str_replace("\r\n ", '', $ics);

        // ORGANIZER identifies the real reservation owner (SENT-BY the site's configured
        // default address, RFC 5545 §3.2.18). The owner also appears as a CHAIR attendee
        // who never needs to RSVP — they're the reservation owner, not the invitee being
        // asked to accept/decline.
        $this->assertStringContainsString('ORGANIZER;CN=Owner Person;SENT-BY="mailto:bookings@example.com":mailto:owner@example.com', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*ROLE=CHAIR[^\r\n]*mailto:owner@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE;[^\r\n]*RSVP=FALSE[^\r\n]*mailto:owner@example\.com/', $unfolded);
    }

    public function testIcsAttachmentRendersWithoutBookedBySet()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);
        $series->WithBookedBy(null);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestReservationEmailMessage($owner, $series, null, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('ORGANIZER;CN=Owner Person;SENT-BY="mailto:bookings@example.com":mailto:owner@example.com', $unfolded);
    }

    public function testGuestDeletedIcsAttachmentListsTheRemovedGuestAsAttendee()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        // The guest has already been removed from the reservation by the time
        // this CANCEL notification fires, so the current instance no longer lists
        // them as a guest.
        $instance = new TestReservation();

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $userRepo = new FakeUserRepository();
        $attributeRepo = new FakeAttributeRepository();

        $message = new TestGuestDeletedEmail($owner, 'guest@example.com', $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:guest@example\.com/', $unfolded);
    }

    public function testFromIsAlwaysTheConfiguredDefaultAddressAndReplyToIsTheBookedByUser()
    {
        // From() is always the site's own configured address, never a real user's — sending
        // "on behalf of" an arbitrary user's personal email domain fails DMARC alignment on
        // most mail servers. ReplyTo() carries the real contact so a reply still reaches them.
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);

        $message = new TestReservationEmailMessage($owner, $series, null, new FakeAttributeRepository(), new FakeUserRepository());

        $from = $message->From();
        $this->assertEquals('bookings@example.com', $from->Address());
        $this->assertEquals('Example Bookings', $from->Name());

        // TestReservationSeries defaults BookedBy() to a FakeUserSession.
        $replyTo = $message->ReplyTo();
        $this->assertEquals('first.last@email.com', $replyTo->Address());
        $this->assertEquals('first last', $replyTo->Name());
    }

    public function testInviteeAddedFromIsTheConfiguredDefaultAddressAndReplyToIsTheOwner()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $inviteeId = 2;
        $invitee = new FakeUser($inviteeId, 'invitee@example.com');
        $invitee->ChangeName('Invitee', 'Person');

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);

        $message = new TestInviteeAddedEmail($owner, $invitee, $series, new FakeAttributeRepository(), new FakeUserRepository());

        $from = $message->From();
        $this->assertEquals('bookings@example.com', $from->Address());
        $this->assertEquals('Example Bookings', $from->Name());

        // Addressed to the invitee, so a reply should reach the reservation owner, not
        // whoever booked the reservation on the owner's behalf.
        $replyTo = $message->ReplyTo();
        $this->assertEquals('owner@example.com', $replyTo->Address());
        $this->assertEquals('Owner Person', $replyTo->Name());
    }
}
