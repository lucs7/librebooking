<?php

namespace LibreBooking\Pages\Admin;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'lib/Application/Admin/namespace.php');

class GroupAdminManageGroupsPage extends ManageGroupsPage
{
    public function __construct()
    {
        parent::__construct();

        $this->CanChangeRoles = false;
        $this->presenter = new ManageGroupsPresenter(
            $this,
            new GroupAdminGroupRepository(new UserRepository(), ServiceLocator::GetServer()->GetUserSession()),
            new ResourceRepository(),
            new ScheduleRepository(),
            new GroupAdminUserRepository(new GroupRepository(), ServiceLocator::GetServer()->GetUserSession())
        );
    }

    public function ProcessPageLoad()
    {
        parent::ProcessPageLoad();
    }
}
class_alias(__NAMESPACE__ . '\\GroupAdminManageGroupsPage', 'GroupAdminManageGroupsPage');
