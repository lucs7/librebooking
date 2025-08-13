<?php

namespace LibreBooking\Pages;

use LibreBooking\Controls\Dashboard\DashboardItem;
use LibreBooking\Presenters\DashboardPresenter;

class DashboardPage extends SecurePage implements IDashboardPage
{
    private $items = [];

    /**
     * @var DashboardPresenter
     */
    private $_presenter;

    public function __construct()
    {
        parent::__construct('MyDashboard');
        $this->_presenter = new DashboardPresenter($this);
    }

    public function PageLoad()
    {
        $this->_presenter->Initialize();

        $this->Set('items', $this->items);
        $this->Display('dashboard.tpl');
    }

    public function AddItem(DashboardItem $item)
    {
        $this->items[] = $item;
    }
}

interface IDashboardPage
{
    public function AddItem(DashboardItem $item);
}
class_alias(__NAMESPACE__ . '\\DashboardPage', 'DashboardPage');
class_alias(__NAMESPACE__ . '\\IDashboardPage', 'IDashboardPage');
