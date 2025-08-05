<?php

class TermsOfServiceRule implements IReservationValidationRule
{
    /**
     * @var ITermsOfServiceRepository
     */
    private $termsOfServiceRepository;

    public function __construct(ITermsOfServiceRepository $termsOfServiceRepository)
    {
        $this->termsOfServiceRepository = $termsOfServiceRepository;
    }

    /**
     * @see IReservationValidationRule::Validate()
     *
     * @param ReservationSeries                $reservationSeries
     * @param ReservationRetryParameter[]|null $retryParameters
     *
     * @return ReservationRuleResult
     */
    public function Validate($reservationSeries, $retryParameters)
    {
        if (!$reservationSeries->HasAcceptedTerms()) {
            $terms = $this->termsOfServiceRepository->Load();
            if (null != $terms && $terms->AppliesToReservation()) {
                return new ReservationRuleResult(false, Resources::GetInstance()->GetString('TermsOfServiceError'));
            }
        }

        return new ReservationRuleResult();
    }
}
