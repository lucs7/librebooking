<?php

namespace LibreBooking\Pages\Reservation;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'lib/Application/Authorization/GuestPermissionServiceFactory.php');

class ReadOnlyReservationPage extends ExistingReservationPage
{
    public function __construct()
    {
        $this->permissionServiceFactory = new GuestPermissionServiceFactory();
        parent::__construct();
        $this->IsEditable = false;
        $this->IsApprovable = false;
    }

    public function PageLoad()
    {
        parent::PageLoad();
    }

    public function SetIsEditable($canBeEdited)
    {
        // no-op
    }

    public function SetIsApprovable($canBeApproved)
    {
        // no-op
    }
}
class_alias(__NAMESPACE__ . '\\ReadOnlyReservationPage', 'ReadOnlyReservationPage');
