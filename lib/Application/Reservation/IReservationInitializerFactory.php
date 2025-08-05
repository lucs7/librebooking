<?php

interface IReservationInitializerFactory
{
    /**
     * @return IReservationInitializer
     */
    public function GetNewInitializer(INewReservationPage $page);

    /**
     * @return IReservationInitializer
     */
    public function GetExistingInitializer(IExistingReservationPage $page, ReservationView $reservationView);
}
