<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationEmailMessageTest extends TestBase
{
    public function setUp(): void
    {
        parent::setup();
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testIcsAttachmentIncludesAttendeesForParticipantsInviteesAndGuests()
    {
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
        // RFC 5545 §3.1 folds lines longer than 75 octets; unfold before matching.
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=REQUEST', $message->AttachmentMimeType());
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:part1@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:invite2@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:guest1@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:guest2@example\.com/', $unfolded);
        $this->assertStringContainsString('ORGANIZER;CN=Owner Person:mailto:owner@example.com', $unfolded);
    }

    public function testIcsAttachmentFallsBackToPublishWhenReservationHasNoParticipants()
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
        $this->assertEquals('text/calendar; charset=UTF-8; method=CANCEL', $message->AttachmentMimeType());
    }

    public function testSharedReservationIcsAttachmentUsesPublishMethod()
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

        $message = new TestReservationShareEmail($owner, 'friend@example.com', $series, $attributeRepo, $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, []);

        $ics = $message->AttachmentContents();

        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringNotContainsString('STATUS:CANCELLED', $ics);
        $this->assertEquals('text/calendar; charset=UTF-8; method=PUBLISH', $message->AttachmentMimeType());
    }

    public function testParticipantAndInviteeDataIsFetchedOnlyOnceWhenPreloadedUsersAreProvided()
    {
        $ownerId = 1;
        $owner = new FakeUser($ownerId, 'owner@example.com');
        $owner->ChangeName('Owner', 'Person');

        $participantId = 2;
        $inviteeId = 3;

        $userRepo = $this->createMock('IUserRepository');
        $userRepo->expects($this->never())->method('GetById');

        $instance = new TestReservation();
        $instance->WithParticipant($participantId);
        $instance->WithInvitee($inviteeId);

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithCurrentInstance($instance);

        $preloadedParticipants = [$participantId => new UserDto($participantId, 'Part', 'One', 'part1@example.com')];
        $preloadedInvitees = [$inviteeId => new UserDto($inviteeId, 'Invite', 'Two', 'invite2@example.com')];

        $message = new TestReservationEmailMessage($owner, $series, null, new FakeAttributeRepository(), $userRepo);
        $message->PopulateIcsAttachmentForTest($instance, [], $preloadedParticipants, $preloadedInvitees);

        $ics = $message->AttachmentContents();
        $unfolded = str_replace("\r\n ", '', $ics);

        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:part1@example\.com/', $unfolded);
        $this->assertMatchesRegularExpression('/ATTENDEE[^\r\n]*mailto:invite2@example\.com/', $unfolded);
    }
}
