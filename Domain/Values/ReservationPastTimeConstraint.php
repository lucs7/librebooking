<?php

class ReservationPastTimeConstraint
{
    public static function IsPast(Date $begin, Date $end): bool
    {
        $constraint = Configuration::Instance()->GetSectionKey(
            ConfigSection::RESERVATION,
            ConfigKeys::RESERVATION_START_TIME_CONSTRAINT
        );

        if (empty($constraint)) {
            $constraint = ReservationStartTimeConstraint::_DEFAULT;
        }

        if (ReservationStartTimeConstraint::NONE == $constraint) {
            return false;
        }

        if (ReservationStartTimeConstraint::CURRENT == $constraint) {
            return $end->LessThan(Date::Now());
        }

        return $begin->LessThan(Date::Now());
    }
}
