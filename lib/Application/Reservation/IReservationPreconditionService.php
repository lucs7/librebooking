<?php

interface INewReservationPreconditionService
{
    public function CheckAll(INewReservationPage $page, UserSession $user);
}

interface IReservationPreconditionService
{
    public function CheckAll(IReservationPage $page, UserSession $user);
}
