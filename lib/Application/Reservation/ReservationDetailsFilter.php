<?php

class ReservationDetailsFilter
{
    /**
     * @param Date|null $reservationStart
     * @param Date|null $reservationEnd
     *
     * @return bool
     */
    public static function HideReservationDetails($reservationStart = null, $reservationEnd = null)
    {
        $hideReservationDetails = Configuration::Instance()->GetSectionKey(
            ConfigSection::PRIVACY,
            ConfigKeys::PRIVACY_HIDE_RESERVATION_DETAILS,
            new LowerCaseConverter()
        );
        if ('past' == $hideReservationDetails && null != $reservationEnd) {
            return $reservationEnd->LessThan(Date::Now());
        } elseif ('future' == $hideReservationDetails && null != $reservationEnd) {
            return $reservationEnd->GreaterThan(Date::Now());
        } elseif ('current' == $hideReservationDetails && null != $reservationStart) {
            return $reservationStart->LessThan(Date::Now());
        }

        $converter = new BooleanConverter();

        return $converter->Convert($hideReservationDetails);
    }
}
