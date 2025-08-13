<?php

namespace LibreBooking\Pages\Admin;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'Presenters/Admin/ManageReservationsPresenter.php');

class ScheduleAdminManageReservationsPage extends ManageReservationsPage
{
    public function __construct()
    {
        parent::__construct();

        $userRepository = new UserRepository();
        $this->presenter = new ManageReservationsPresenter(
            $this,
            new ScheduleAdminManageReservationsService(new ReservationViewRepository(), $userRepository, new ReservationAuthorization(PluginManager::Instance()->LoadAuthorization())),
            new ScheduleAdminScheduleRepository($userRepository, ServiceLocator::GetServer()->GetUserSession()),
            new ResourceAdminResourceRepository($userRepository, ServiceLocator::GetServer()->GetUserSession()),
            new AttributeService(new AttributeRepository()),
            $userRepository,
            new TermsOfServiceRepository()
        );
    }
}
class_alias(__NAMESPACE__ . '\\ScheduleAdminManageReservationsPage', 'ScheduleAdminManageReservationsPage');
