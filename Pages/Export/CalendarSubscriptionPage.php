<?php

require_once(ROOT_DIR . 'Pages/Export/SubscriptionPage.php');
require_once(ROOT_DIR . 'Pages/Export/CalendarExportDisplay.php');

class CalendarSubscriptionPage extends SubscriptionPage
{
    public function __construct()
    {
        parent::__construct();
    }

    public function PageLoad()
    {
        $this->presenter->PageLoad();
        if ($this->notFound) {
            http_response_code(404);
            return;
        }

        header('Content-Type: text/Calendar');
        header('Content-Disposition: inline; filename=calendar.ics');

        $display = new CalendarExportDisplay();
        echo preg_replace('~\R~u', "\r\n", $display->Render($this->reservations));
    }
}
