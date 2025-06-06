<?php

require_once(ROOT_DIR . 'Pages/Admin/ManageResourcesPage.php');
require_once(ROOT_DIR . 'Presenters/Admin/EditResourcePresenter.php');

class EditResourcePage extends ManageResourcesPage
{
    public function __construct()
    {
        parent::__construct();
        $this->presenter = new EditResourcePresenter(
            $this,
            new ResourceRepository(),
            new ScheduleRepository(),
            new ImageFactory(),
            new GroupRepository(),
            new AttributeService(new AttributeRepository()),
            new UserPreferenceRepository(),
            new ReservationViewRepository()
        );
    }
}
