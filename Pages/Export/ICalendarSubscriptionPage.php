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
     * @param string $calendarName
     */
    public function SetCalendarName($calendarName);

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
