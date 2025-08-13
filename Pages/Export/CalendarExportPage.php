<?php

namespace LibreBooking\Pages\Export;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use LibreBooking\Application\Schedule\ICalendarExportValidator;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'Presenters/CalendarExportPresenter.php');

interface ICalendarExportPage
{
    /**
     * @return string
     */
    public function GetReferenceNumber();

    /**
     * @param array|iCalendarReservationView[] $reservations
     */
    public function SetReservations($reservations);

    /**
     * @return int
     */
    public function GetScheduleId();

    /**
     * @return int
     */
    public function GetResourceId();

    /**
     * @return int
     */
    public function GetAccessoryName();
}

class CalendarExportPage extends Page implements ICalendarExportPage
{
    /**
     * @var \CalendarExportPresenter
     */
    private $presenter;

    /**
     * @var array|iCalendarReservationView[]
     */
    private $reservations = [];

    public function __construct()
    {
        $authorization = new ReservationAuthorization(PluginManager::Instance()->LoadAuthorization());
        $this->presenter = new CalendarExportPresenter(
            $this,
            new ReservationViewRepository(),
            new NullCalendarExportValidator(),
            new PrivacyFilter($authorization)
        );
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        $this->presenter->PageLoad(ServiceLocator::GetServer()->GetUserSession());

        header("Content-Type: text/Calendar");
        header("Content-Disposition: inline; filename=calendar.ics");

        $display = new CalendarExportDisplay();
        echo $display->Render($this->reservations);
    }

    public function GetReferenceNumber()
    {
        return $this->GetQuerystring(QueryStringKeys::REFERENCE_NUMBER);
    }

    public function SetReservations($reservations)
    {
        $this->reservations = $reservations;
    }

    public function GetScheduleId()
    {
        return $this->GetQuerystring(QueryStringKeys::SCHEDULE_ID);
    }

    public function GetResourceId()
    {
        return $this->GetQuerystring(QueryStringKeys::RESOURCE_ID);
    }

    public function GetAccessoryName()
    {
        return $this->GetQuerystring(QueryStringKeys::ACCESSORY_NAME);
    }
}

class NullCalendarExportValidator implements ICalendarExportValidator
{
    public function IsValid()
    {
        return true;
    }
}
class_alias(__NAMESPACE__ . '\\ICalendarExportPage', 'ICalendarExportPage');
class_alias(__NAMESPACE__ . '\\CalendarExportPage', 'CalendarExportPage');
class_alias(__NAMESPACE__ . '\\NullCalendarExportValidator', 'NullCalendarExportValidator');
