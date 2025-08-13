<?php

namespace LibreBooking\Controls\Dashboard;

use SmartyPage;
use LibreBooking\Controls\Control;

require_once(ROOT_DIR . 'Controls/Control.php');

abstract class DashboardItem extends Control
{
    public function __construct(SmartyPage $smarty)
    {
        parent::__construct($smarty);
    }

    protected function Display($templateName)
    {
        parent::Display("Dashboard/$templateName");
    }

    protected function Assign($name, $value)
    {
        $this->Set($name, $value);
    }
}

class_alias(DashboardItem::class, 'DashboardItem');
