<?php

namespace LibreBooking\Pages;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'Presenters/LoginPresenter.php');
require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');

class LogoutPage extends LoginPage
{
    public function __construct()
    {
        parent::__construct();
    }

    public function PageLoad()
    {
        $this->presenter->Logout();
    }

    public function GetResumeUrl()
    {
        return $this->GetQuerystring(QueryStringKeys::REDIRECT);
    }
}
class_alias(__NAMESPACE__ . '\\LogoutPage', 'LogoutPage');
