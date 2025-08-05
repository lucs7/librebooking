<?php

class ReservationDateTimeRule implements IReservationValidationRule
{
    /**
     * @param ReservationSeries $reservationSeries
     *
     * @return ReservationRuleResult
     *
     * @throws Exception
     */
    public function Validate($reservationSeries, $retryParameters)
    {
        $currentInstance = $reservationSeries->CurrentInstance();

        $startIsBeforeEnd = $currentInstance->StartDate()->LessThan($currentInstance->EndDate());

        return new ReservationRuleResult($startIsBeforeEnd, Resources::GetInstance()->GetString('StartDateBeforeEndDateRule'));
    }
}
