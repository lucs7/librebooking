<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'Pages/Pages.php');
require_once(ROOT_DIR . 'Pages/Export/CalendarExportDisplay.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailTemplateContext.php');

abstract class ReservationEmailMessage extends EmailMessage
{
    /**
     * @var User
     */
    protected $reservationOwner;

    /**
     * @var ReservationSeries
     */
    protected $reservationSeries;

    /**
     * @var BookableResource
     */
    protected $primaryResource;

    protected ?string $timezone;

    /**
     * @var IAttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var IUserRepository
     */
    protected $userRepository;

    public function __construct(
        User $reservationOwner,
        ReservationSeries $reservationSeries,
        $language,
        IAttributeRepository $attributeRepository,
        IUserRepository $userRepository
    ) {
        if (empty($language)) {
            $language = $reservationOwner->Language();
        }
        parent::__construct($language);

        $this->reservationOwner = $reservationOwner;
        $this->reservationSeries = $reservationSeries;
        $ownerTimezone = $reservationOwner->Timezone();
        $this->timezone = !empty($ownerTimezone) ? $ownerTimezone : Configuration::Instance()->GetDefaultTimezone();
        $this->attributeRepository = $attributeRepository;
        $this->primaryResource = $reservationSeries->Resource();
        $this->userRepository = $userRepository;
    }

    /**
     * @abstract
     * @return string
     */
    abstract protected function GetTemplateName();

    public function To()
    {
        $address = $this->reservationOwner->EmailAddress();
        $name = $this->reservationOwner->FullName();

        return [new EmailAddress($address, $name)];
    }

    public function Body()
    {
        $this->PopulateTemplate();
        return $this->FetchTemplate($this->GetTemplateName());
    }

    public function From()
    {
        $bookedBy = $this->reservationSeries->BookedBy();
        if ($bookedBy != null) {
            $name = new FullName($bookedBy->FirstName, $bookedBy->LastName);
            return new EmailAddress($bookedBy->Email, $name->__toString());
        }
        return new EmailAddress($this->reservationOwner->EmailAddress(), $this->reservationOwner->FullName());
    }

    protected function PopulateTemplate()
    {
        $currentInstance = $this->reservationSeries->CurrentInstance();
        $context = new ReservationEmailTemplateContext(
            reservationSeries: $this->reservationSeries,
            reservationOwner: $this->reservationOwner,
            primaryResource: $this->primaryResource,
            attributeRepository: $this->attributeRepository
        );

        $this->Set('UserName', $this->reservationOwner->FullName());
        $this->Set('StartDate', $currentInstance->StartDate()->ToTimezone($this->timezone));
        $this->Set('EndDate', $currentInstance->EndDate()->ToTimezone($this->timezone));
        $this->Set('ScheduleId', $this->reservationSeries->ScheduleId());
        $this->Set('ResourceName', $this->reservationSeries->Resource()->GetName());
        $resourceImage = ReservationEmailTemplateContext::BuildResourceImageUrl($this->reservationSeries->Resource()->GetImage());
        if ($resourceImage != null) {
            $this->Set('ResourceImage', $resourceImage);
        }

        $this->Set('Title', $this->reservationSeries->Title());
        $this->Set('Description', $this->reservationSeries->Description());

        $repeatVariables = $context->GetRecurrenceInstances($this->timezone);
        $this->Set('RepeatDates', $repeatVariables['RepeatDates']);
        $this->Set('RepeatRanges', $repeatVariables['RepeatRanges']);
        $this->Set('RequiresApproval', $this->reservationSeries->RequiresApproval());

        $this->Set('ReservationUrl', sprintf('%s?%s=%s', Pages::RESERVATION, QueryStringKeys::REFERENCE_NUMBER, $currentInstance->ReferenceNumber()));

        $icalUrl = sprintf('export/%s?%s=%s', Pages::CALENDAR_EXPORT, QueryStringKeys::REFERENCE_NUMBER, $currentInstance->ReferenceNumber());
        $this->Set('ICalUrl', $icalUrl);

        $googleDateFormat = Resources::GetInstance()->GetDateFormat('google');
        $googleCalendarUrl = sprintf(
            'https://www.google.com/calendar/event?action=TEMPLATE&text=%s&dates=%s/%s&ctz=%s&details=%s&location=%s&trp=false&sprop=&sprop=name:',
            urlencode($this->reservationSeries->Title()),
            $currentInstance->StartDate()->ToUtc()->Format($googleDateFormat),
            $currentInstance->EndDate()->ToUtc()->Format($googleDateFormat),
            $currentInstance->StartDate()->Timezone(),
            urlencode($this->reservationSeries->Description()),
            urlencode($this->reservationSeries->Resource()->GetName())
        );
        $this->Set('GoogleCalendarUrl', $googleCalendarUrl);

        $this->Set('ResourceNames', $context->ResourceNames());
        $this->Set('Resources', $context->Resources());
        $this->Set('Accessories', $this->reservationSeries->Accessories());

        $attributeValues = $context->ReservationAttributes();
        $this->Set('Attributes', $attributeValues);

        $createdBy = $context->CreatedBy();
        if ($createdBy != null) {
            $this->Set('CreatedBy', $createdBy);
        }

        $minimumAutoRelease = null;
        foreach ($this->reservationSeries->AllResources() as $resource) {
            if ($resource->IsCheckInEnabled()) {
                $this->Set('CheckInEnabled', true);
            }

            if ($resource->IsAutoReleased()) {
                if ($minimumAutoRelease == null || $resource->GetAutoReleaseMinutes() < $minimumAutoRelease) {
                    $minimumAutoRelease = $resource->GetAutoReleaseMinutes();
                }
            }
        }

        $participantUsers = [];
        foreach ($currentInstance->Participants() as $id) {
            $participantUsers[$id] = $this->userRepository->GetById($id);
        }

        $inviteeUsers = [];
        foreach ($currentInstance->Invitees() as $id) {
            $inviteeUsers[$id] = $this->userRepository->GetById($id);
        }

        $this->PopulateIcsAttachment($currentInstance, $attributeValues, $participantUsers, $inviteeUsers);

        $this->Set('AutoReleaseMinutes', $minimumAutoRelease);
        $this->Set('ReferenceNumber', $currentInstance->ReferenceNumber());

        $this->Set('Participants', array_values($participantUsers));
        $this->Set('ParticipatingGuests', $currentInstance->ParticipatingGuests());
        $this->Set('Invitees', array_values($inviteeUsers));
        $this->Set('InvitedGuests', $currentInstance->InvitedGuests());

        $this->Set('CreditsCurrent', $currentInstance->GetCreditsRequired());
        $this->Set('CreditsTotal', $this->reservationSeries->GetCreditsRequired());
    }

    /**
     * @param Reservation $currentInstance
     * @param Attribute[] $attributeValues
     */
    /**
     * @param Reservation $currentInstance
     * @param Attribute[] $attributeValues
     * @param array $participantUsers Map of user id => User, pre-fetched by caller to avoid duplicate DB queries.
     * @param array $inviteeUsers Map of user id => User, pre-fetched by caller.
     */
    protected function PopulateIcsAttachment($currentInstance, $attributeValues, array $participantUsers = [], array $inviteeUsers = [])
    {
        $rv = new ReservationItemView(
            $currentInstance->ReferenceNumber(),
            $currentInstance->StartDate()->ToUTC(),
            $currentInstance->EndDate()->ToUTC(),
            $this->reservationSeries->Resource()->GetName(),
            $this->reservationSeries->Resource()->GetResourceId(),
            $currentInstance->ReservationId(),
            null,
            $this->reservationSeries->Title(),
            $this->reservationSeries->Description(),
            $this->reservationSeries->ScheduleId(),
            $this->reservationOwner->FirstName(),
            $this->reservationOwner->LastName(),
            $this->reservationOwner->Id(),
            $this->reservationOwner->GetAttribute(UserAttribute::Phone),
            $this->reservationOwner->GetAttribute(UserAttribute::Organization),
            $this->reservationOwner->GetAttribute(UserAttribute::Position)
        );

        $ca = new CustomAttributes();
        /** @var LBAttribute $attribute */
        foreach ($attributeValues as $attribute) {
            $ca->Add($attribute->Id(), $attribute->Value());
        }
        $rv->Attributes = $ca;
        $rv->UserPreferences = $this->reservationOwner->GetPreferences();
        $rv->OwnerEmailAddress = $this->reservationOwner->EmailAddress();

        $rv->ParticipantIds = $currentInstance->Participants();
        foreach ($rv->ParticipantIds as $id) {
            $participant = $participantUsers[$id] ?? $this->userRepository->GetById($id);
            if ($participant !== null) {
                $rv->ParticipantNames[$id] = (new FullName($participant->FirstName, $participant->LastName))->__toString();
                $rv->ParticipantEmails[$id] = $participant->EmailAddress;
            }
        }

        $rv->InviteeIds = $currentInstance->Invitees();
        foreach ($rv->InviteeIds as $id) {
            $invitee = $inviteeUsers[$id] ?? $this->userRepository->GetById($id);
            if ($invitee !== null) {
                $rv->InviteeNames[$id] = (new FullName($invitee->FirstName, $invitee->LastName))->__toString();
                $rv->InviteeEmails[$id] = $invitee->EmailAddress;
            }
        }

        $rv->ParticipatingGuests = $currentInstance->ParticipatingGuests();
        $rv->InvitedGuests = $currentInstance->InvitedGuests();

        $icsView = new iCalendarReservationView($rv, $this->reservationSeries->BookedBy(), new NullPrivacyFilter());

        // GetIcsMethod() declares this email's iTip intent. REQUEST is provisional: whether
        // a reservation actually ships as METHOD:REQUEST or falls back to METHOD:PUBLISH
        // depends on attendee presence, and CalendarExportDisplay::DetermineMethod() is the
        // single place that rule lives (shared with the export/subscription pages) — call it
        // here rather than re-implementing the same attendee check inline.
        $method = $this->GetIcsMethod();
        if ($method === 'REQUEST') {
            $method = CalendarExportDisplay::DetermineMethod([$icsView]);
        }
        if ($method === 'CANCEL') {
            $icsView->IsCancelled = true;
        }

        $display = new CalendarExportDisplay();
        $icsContents = $display->Render([$icsView], null, $method);
        $this->AddStringAttachment($icsContents, 'reservation.ics', "text/calendar; charset=UTF-8; method={$method}");
    }

    /**
     * The iTip (RFC 5546) method this email's ICS attachment represents, before the
     * attendee-based REQUEST/PUBLISH check in PopulateIcsAttachment() runs. Defaults to
     * REQUEST: the actionable "here's a scheduling invite" case for owner/participant/
     * invitee/guest add & update notifications. PopulateIcsAttachment() downgrades this
     * to PUBLISH via CalendarExportDisplay::DetermineMethod() when the reservation has no
     * attendees to invite (see commit dfd28fef, "remove METHOD:REQUEST ... fix Add to
     * Outlook", for why a REQUEST with no attendee data broke calendar import).
     * Subclasses override this when the email isn't a REQUEST-shaped notification at all:
     * ReservationDeletedEmail (and its subclasses covering removed participants, invitees,
     * and guests) always uses CANCEL, and ReservationShareEmail — whose recipient is never
     * added as an ATTENDEE — always uses PUBLISH, bypassing the attendee check entirely
     * (only 'REQUEST' routes through DetermineMethod()).
     *
     * @return string 'REQUEST', 'CANCEL', or 'PUBLISH'
     */
    protected function GetIcsMethod(): string
    {
        return 'REQUEST';
    }
}
