<?php

namespace LibreBooking\Pages\Ajax;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

require_once(ROOT_DIR . 'Presenters/AvailableAccessoriesPresenter.php');

interface IAvailableAccessoriesPage
{
    public function GetStartDate();

    public function GetEndDate();

    public function GetStartTime();

    public function GetEndTime();

    public function GetReferenceNumber();

    /**
     * @param AccessoryAvailability[] $realAvailability
     */
    public function BindAvailability($realAvailability);
}

class AvailableAccessoriesPage extends Page implements IAvailableAccessoriesPage
{
    public function __construct()
    {
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        $presenter = new AvailableAccessoriesPresenter(
            $this,
            new AccessoryRepository(),
            new ReservationViewRepository(),
            ServiceLocator::GetServer()->GetUserSession()
        );
        $presenter->PageLoad();
    }

    public function GetStartDate()
    {
        return $this->GetQuerystring(QueryStringKeys::START_DATE);
    }

    public function GetEndDate()
    {
        return $this->GetQuerystring(QueryStringKeys::END_DATE);
    }

    public function GetReferenceNumber()
    {
        return $this->GetQuerystring(QueryStringKeys::REFERENCE_NUMBER);
    }

    public function GetStartTime()
    {
        return $this->GetQuerystring(QueryStringKeys::START_TIME);
    }

    public function GetEndTime()
    {
        return $this->GetQuerystring(QueryStringKeys::END_TIME);
    }

    public function BindAvailability($realAvailability)
    {
        $this->SetJson($realAvailability);
    }
}

class AccessoryAvailability
{
    public $id;
    public $quantity;

    public function __construct($id, $quantity)
    {
        $this->id = $id;
        $this->quantity = $quantity;
    }
}
class_alias(__NAMESPACE__ . '\\IAvailableAccessoriesPage', 'IAvailableAccessoriesPage');
class_alias(__NAMESPACE__ . '\\AvailableAccessoriesPage', 'AvailableAccessoriesPage');
class_alias(__NAMESPACE__ . '\\AccessoryAvailability', 'AccessoryAvailability');
