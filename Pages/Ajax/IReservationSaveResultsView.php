<?php

namespace LibreBooking\Pages\Ajax;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

interface IReservationSaveResultsView
{
    /**
     * @param bool $succeeded
     */
    public function SetSaveSuccessfulMessage($succeeded);

    /**
     * @param array|string[] $errors
     */
    public function SetErrors($errors);

    /**
     * @param array|string[] $warnings
     */
    public function SetWarnings($warnings);

    /**
     * @param array|string[] $messages
     */
    public function SetRetryMessages($messages);

    /**
     * @param bool $canBeRetried
     */
    public function SetCanBeRetried($canBeRetried);

    /**
     * @param ReservationRetryParameter[] $retryParameters
     */
    public function SetRetryParameters($retryParameters);

    /**
     * @return ReservationRetryParameter[]
     */
    public function GetRetryParameters();

    /**
     * @param bool $canJoinWaitlist
     */
    public function SetCanJoinWaitList($canJoinWaitlist);
}
class_alias(__NAMESPACE__ . '\\IReservationSaveResultsView', 'IReservationSaveResultsView');
