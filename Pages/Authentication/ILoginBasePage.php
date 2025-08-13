<?php

namespace LibreBooking\Pages\Authentication;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

interface ILoginBasePage extends IPage
{
    /**
     * @return string
     */
    public function GetResumeUrl();
}
class_alias(__NAMESPACE__ . '\\ILoginBasePage', 'ILoginBasePage');
