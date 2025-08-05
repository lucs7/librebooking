<?php

require_once ROOT_DIR.'Domain/Values/ReservationStartTimeConstraint.php';

class ReservationStartTimeRule implements IReservationValidationRule
{
    /**
     * @var IScheduleRepository
     */
    private $scheduleRepository;

    public function __construct(IScheduleRepository $scheduleRepository)
    {
        $this->scheduleRepository = $scheduleRepository;
    }

    /**
     * @param ReservationSeries $reservationSeries
     *
     * @return ReservationRuleResult
     *
     * @throws Exception
     */
    public function Validate($reservationSeries, $retryParameters)
    {
        $constraint = Configuration::Instance()->GetSectionKey(ConfigSection::RESERVATION, ConfigKeys::RESERVATION_START_TIME_CONSTRAINT);

        if (empty($constraint)) {
            $constraint = ReservationStartTimeConstraint::_DEFAULT;
        }

        if (ReservationStartTimeConstraint::NONE == $constraint) {
            return new ReservationRuleResult();
        }

        $currentInstance = $reservationSeries->CurrentInstance();

        $dateThatShouldBeLessThanNow = $currentInstance->StartDate();
        if (ReservationStartTimeConstraint::CURRENT == $constraint) {
            $timezone = $dateThatShouldBeLessThanNow->Timezone();
            /** @var SchedulePeriod $currentPeriod */
            $currentPeriod = $this->scheduleRepository
                    ->GetLayout($reservationSeries->ScheduleId(), new ScheduleLayoutFactory($timezone))
                    ->GetPeriod($currentInstance->EndDate());
            $dateThatShouldBeLessThanNow = $currentPeriod->BeginDate();
        }
        Log::Debug('Start Time Rule: Comparing %s to %s', $dateThatShouldBeLessThanNow, Date::Now());

        $startIsInFuture = $dateThatShouldBeLessThanNow->Compare(Date::Now()) >= 0;

        return new ReservationRuleResult($startIsInFuture, Resources::GetInstance()->GetString('StartIsInPast'));
    }
}
