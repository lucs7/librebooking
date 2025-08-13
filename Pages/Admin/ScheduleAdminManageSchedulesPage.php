<?php

namespace LibreBooking\Pages\Admin;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'Presenters/Admin/ManageSchedulesPresenter.php');
require_once(ROOT_DIR . 'lib/Application/Admin/namespace.php');


class ScheduleAdminManageSchedulesPage extends ManageSchedulesPage
{
    public function __construct()
    {
        parent::__construct();

        $userRepository = new UserRepository();
        $user = ServiceLocator::GetServer()->GetUserSession();
        $this->_presenter = new ManageSchedulesPresenter(
            $this,
            new ScheduleAdminManageScheduleService(
                new ScheduleAdminScheduleRepository($userRepository, $user),
                new ScheduleRepository(),
                new ResourceAdminResourceRepository($userRepository, $user)
            ),
            new GroupRepository()
        );
    }
}

class ScheduleAdminManageScheduleService extends ManageScheduleService
{
    /**
     * @var IScheduleRepository
     */
    private $adminScheduleRepo;
    /**
     * @var IScheduleRepository
     */
    private $scheduleRepo;
    /**
     * @var IResourceRepository
     */
    private $adminResourceRepo;

    public function __construct(IScheduleRepository $adminScheduleRepo, IScheduleRepository $scheduleRepo, IResourceRepository $adminResourceRepo)
    {
        $this->adminScheduleRepo = $adminScheduleRepo;
        $this->scheduleRepo = $scheduleRepo;
        $this->adminResourceRepo = $adminResourceRepo;
        parent::__construct($adminScheduleRepo, $adminResourceRepo);
    }

    public function GetAll()
    {
        return $this->adminScheduleRepo->GetAll();
    }

    public function GetSourceSchedules()
    {
        return $this->scheduleRepo->GetAll();
    }

    public function GetResources()
    {
        $resources = [];

        $all = $this->adminResourceRepo->GetResourceList();
        /** @var BookableResource $resource */
        foreach ($all as $resource) {
            $resources[$resource->GetScheduleId()][] = $resource;
        }

        return $resources;
    }
}
class_alias(__NAMESPACE__ . '\\ScheduleAdminManageSchedulesPage', 'ScheduleAdminManageSchedulesPage');
class_alias(__NAMESPACE__ . '\\ScheduleAdminManageScheduleService', 'ScheduleAdminManageScheduleService');
