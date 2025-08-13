<?php

namespace LibreBooking\Pages;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

class HelpPage extends Page
{
    public function __construct()
    {
        parent::__construct('Help');
    }

    public function PageLoad()
    {
        $this->Set('RemindersPath', realpath(ROOT_DIR . 'Jobs/sendreminders.php'));
        $this->Set('AutoReleasePath', realpath(ROOT_DIR . 'Jobs/autorelease.php'));
        $this->Set('WaitListPath', realpath(ROOT_DIR . 'Jobs/sendwaitlist.php'));
        $this->Set('MissedCheckinPath', realpath(ROOT_DIR . 'Jobs/sendmissedcheckin.php'));
        $this->Set('ServerTimezone', date_default_timezone_get());

        $this->DisplayLocalized('support-and-credits.tpl');
    }
}
class_alias(__NAMESPACE__ . '\\HelpPage', 'HelpPage');
