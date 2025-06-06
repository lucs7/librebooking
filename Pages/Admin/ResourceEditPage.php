<?php

require_once(ROOT_DIR . 'Pages/Admin/ManageResourcesPage.php');
require_once(ROOT_DIR . 'Presenters/Admin/ResourceEditPresenter.php');

class ResourceEditPage extends ManageResourcesPage
{
    public function __construct()
    {
        parent::__construct();
        $this->presenter = new ResourceEditPresenter(
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

    public function ProcessPageLoad()
    {
        $this->presenter->PageLoad();

        $this->Display('Admin/Resources/resource_edit.tpl');
    }

    public function ProcessAction()
    {
        parent::ProcessAction();
    }

    public function ProcessDataRequest($dataRequest)
    {
        parent::ProcessDataRequest($dataRequest);
    }
}
