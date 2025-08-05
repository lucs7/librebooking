<?php

require_once ROOT_DIR.'Pages/ParticipationPage.php';
require_once ROOT_DIR.'Domain/Access/namespace.php';
require_once ROOT_DIR.'lib/Application/Reservation/Validation/namespace.php';
require_once ROOT_DIR.'lib/Application/Reservation/Notification/namespace.php';

class ParticipationPresenter
{
    /**
     * @var IParticipationPage
     */
    private $page;

    /**
     * @var IReservationRepository
     */
    private $reservationRepository;

    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;
    /**
     * @var IParticipationNotification
     */
    private $participationNotification;

    public function __construct(
        IParticipationPage $page,
        IReservationRepository $reservationRepository,
        IReservationViewRepository $reservationViewRepository,
        IParticipationNotification $participationNotification,
    ) {
        $this->page = $page;
        $this->reservationRepository = $reservationRepository;
        $this->reservationViewRepository = $reservationViewRepository;
        $this->participationNotification = $participationNotification;
    }

    public function PageLoad()
    {
        $invitationAction = $this->page->GetInvitationAction();

        if (!empty($invitationAction)) {
            $resultString = $this->HandleInvitationAction($invitationAction);

            if ('json' == $this->page->GetResponseType()) {
                $this->page->DisplayResult($resultString);

                return;
            }

            $this->page->SetResult($resultString);
        }

        $startDate = Date::Now();
        $endDate = $startDate->AddDays(30);
        $user = ServiceLocator::GetServer()->GetUserSession();
        $userId = $user->UserId;

        $reservations = $this->reservationViewRepository->GetReservations($startDate, $endDate, $userId, ReservationUserLevel::INVITEE);

        $this->page->SetTimezone($user->Timezone);
        $this->page->BindReservations($reservations);
        $this->page->DisplayParticipation();
    }

    /**
     * @return string|null
     */
    private function HandleInvitationAction($invitationAction)
    {
        $user = ServiceLocator::GetServer()->GetUserSession();

        $referenceNumber = $this->page->GetInvitationReferenceNumber();
        $userId = $this->page->GetUserId();

        Log::Debug('Invitation action %s for user %s and reference number %s', $invitationAction, $userId, $referenceNumber);

        $series = $this->reservationRepository->LoadByReferenceNumber($referenceNumber);

        if (InvitationAction::Join == $invitationAction || InvitationAction::CancelInstance == $invitationAction) {
            $rules = [new ReservationStartTimeRule(new ScheduleRepository()), new ResourceMinimumNoticeCurrentInstanceRuleUpdate($user), new ResourceMaximumNoticeCurrentInstanceRule($user)];
        } else {
            $rules = [new ReservationStartTimeRule(new ScheduleRepository()), new ResourceMinimumNoticeRuleAdd($user), new ResourceMaximumNoticeRule($user)];
        }

        /** @var IReservationValidationRule $rule */
        foreach ($rules as $rule) {
            $ruleResult = $rule->Validate($series, null);

            if (!$ruleResult->IsValid()) {
                return $ruleResult->ErrorMessage();
            }
        }

        $error = null;
        if (InvitationAction::Accept == $invitationAction) {
            $series->AcceptInvitation($userId);

            $error = $this->CheckCapacityAndReturnAnyError($series);
        }
        if (InvitationAction::Decline == $invitationAction) {
            $series->DeclineInvitation($userId);
        }
        if (InvitationAction::CancelInstance == $invitationAction) {
            $series->CancelInstanceParticipation($userId);
        }
        if (InvitationAction::CancelAll == $invitationAction) {
            $series->CancelAllParticipation($userId);
        }
        if (InvitationAction::Join == $invitationAction) {
            if (!$series->GetAllowParticipation()) {
                $error = Resources::GetInstance()->GetString('ParticipationNotAllowed');
            } else {
                $series->JoinReservation($userId);
                $error = $this->CheckCapacityAndReturnAnyError($series);
            }
        }
        if (InvitationAction::JoinAll == $invitationAction) {
            if (!$series->GetAllowParticipation()) {
                $error = Resources::GetInstance()->GetString('ParticipationNotAllowed');
            } else {
                $series->JoinReservationSeries($userId);
                $error = $this->CheckCapacityAndReturnAnyError($series);
            }
        }

        if (empty($error)) {
            $this->reservationRepository->Update($series);
            $this->participationNotification->Notify($series, $userId, $invitationAction);
        }

        return $error;
    }

    /**
     * @param ExistingReservationSeries $series
     *
     * @return mixed|string|null
     */
    private function CheckCapacityAndReturnAnyError($series)
    {
        foreach ($series->AllResources() as $resource) {
            if (!$resource->HasMaxParticipants()) {
                continue;
            }

            /** @var Reservation $instance */
            foreach ($series->Instances() as $instance) {
                $numberOfParticipants = count($instance->Participants()) + count($instance->ParticipatingGuests());
                if ($numberOfParticipants > $resource->GetMaxParticipants()) {
                    return Resources::GetInstance()->GetString('MaxParticipantsError', [$resource->GetName(), $resource->GetMaxParticipants()]);
                }
            }
        }

        return null;
    }
}
