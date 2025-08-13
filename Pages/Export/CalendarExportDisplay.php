<?php

namespace LibreBooking\Pages\Export;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

class CalendarExportDisplay extends Page
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $reservations iCalendarReservationView[]
     * @return string
     */
    public function Render($reservations)
    {
        $config = Configuration::Instance();

        $this->Set('bookedVersion', $config->GetKey(ConfigKeys::VERSION));
        $this->Set('DateStamp', Date::Now());

        /**
         * ScriptUrl is used to generate iCal UID's. As a workaround to this bug
         * https://bugzilla.mozilla.org/show_bug.cgi?id=465853
         * we need to avoid using any slashes "/"
         */
        $url = $config->GetScriptUrl();
        $this->Set('UID', parse_url($url, PHP_URL_HOST));
        $this->Set('Reservations', $reservations);

        return preg_replace('~\R~u', "\r\n", $this->smarty->fetch('Export/ical.tpl'));
    }

    public function PageLoad()
    {
        // no-op
    }
}
class_alias(__NAMESPACE__ . '\\CalendarExportDisplay', 'CalendarExportDisplay');
