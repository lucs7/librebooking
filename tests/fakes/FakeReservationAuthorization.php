<?php

class FakeReservationAuthorization implements IReservationAuthorization
{
    public $_CanChangeUsers = true;
    public $_CanEdit = true;
    public $_CanApprove = true;
    public $_CanViewDetails = true;

    /**
     * @return bool
     */
    public function CanChangeUsers(UserSession $currentUser)
    {
        return $this->_CanChangeUsers;
    }

    /**
     * @return bool
     */
    public function CanEdit(ReservationView $reservationView, UserSession $currentUser)
    {
        return $this->_CanEdit;
    }

    /**
     * @return bool
     */
    public function CanApprove(ReservationView $reservationView, UserSession $currentUser)
    {
        return $this->_CanApprove;
    }

    /**
     * @return bool
     */
    public function CanViewDetails(ReservationView $reservationView, UserSession $currentUser)
    {
        return $this->_CanViewDetails;
    }
}
