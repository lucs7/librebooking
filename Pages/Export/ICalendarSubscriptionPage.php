<?php

interface ICalendarSubscriptionPage
{
    /**
     * @return string
     */
    public function GetSubscriptionKey();

    /**
     * @return string
     */
    public function GetUserId();

    /**
     * @param iCalendarReservationView[] $reservations
     */
    public function SetReservations($reservations);

    /**
     * Signal that the current request should be aborted (e.g. when the
     * subscription key is invalid). Implementations set a flag that PageLoad()
     * checks after the presenter runs and returns the appropriate HTTP status.
     */
    public function SetIsNotFound(): void;

    /**
     * @return string
     */
    public function GetScheduleId();

    /**
     * @return string
     */
    public function GetResourceId();

    /**
     * @return string
     */
    public function GetResourceGroupId();

    /**
     * @return int
     */
    public function GetAccessoryIds();

    /**
     * @return int
     */
    public function GetPastNumberOfDays();

    /**
     * @return int
     */
    public function GetFutureNumberOfDays();
}
