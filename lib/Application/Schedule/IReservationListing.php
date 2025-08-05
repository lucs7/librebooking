<?php

interface IDateReservationListing extends IResourceReservationListing
{
    /**
     * @param int $resourceId
     *
     * @return IResourceReservationListing
     */
    public function ForResource($resourceId);
}

interface IResourceReservationListing
{
    /**
     * @return int
     */
    public function Count();

    /**
     * @return array|ReservationListItem[]
     */
    public function Reservations();
}

interface IReservationListing extends IResourceReservationListing
{
    /**
     * @param Date $date
     *
     * @return IReservationListing
     */
    public function OnDate($date);

    /**
     * @param int $resourceId
     *
     * @return IReservationListing
     */
    public function ForResource($resourceId);

    /**
     * @param int $resourceId
     *
     * @return array|ReservationListItem[]
     */
    public function OnDateForResource(Date $date, $resourceId);
}

interface IMutableReservationListing extends IReservationListing
{
    /**
     * @param ReservationItemView $reservation
     *
     * @return void
     */
    public function Add($reservation);

    /**
     * @param BlackoutItemView $blackout
     *
     * @return void
     */
    public function AddBlackout($blackout);
}

class EmptyReservationListing implements IReservationListing
{
    public function Count()
    {
        return 0;
    }

    public function Reservations()
    {
        return [];
    }

    public function OnDate($date)
    {
        return new EmptyReservationListing();
    }

    public function ForResource($resourceId)
    {
        return new EmptyReservationListing();
    }

    public function OnDateForResource(Date $date, $resourceId)
    {
        return new EmptyReservationListing();
    }
}
