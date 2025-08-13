<?php

namespace LibreBooking\Pages\Export;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

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
class_alias(__NAMESPACE__ . '\\ICalendarSubscriptionPage', 'ICalendarSubscriptionPage');
