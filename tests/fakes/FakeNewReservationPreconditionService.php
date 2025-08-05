<?php

require_once ROOT_DIR.'lib/Application/Reservation/namespace.php';

class FakeNewReservationPreconditionService implements INewReservationPreconditionService
{
    public function CheckAll(INewReservationPage $page, UserSession $user)
    {
        // TODO: Implement CheckAll() method.
    }
}
