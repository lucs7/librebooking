<?php

require_once(ROOT_DIR . 'Pages/Export/SubscriptionPage.php');

class CalendarSubscriptionPage extends SubscriptionPage
{
    public function __construct()
    {
        parent::__construct();
    }

    public function PageLoad(): void
    {
        if (!$this->presenter->PageLoad()) {
            http_response_code(404);
            return;
        }

        header('Content-Type: text/Calendar');
        header('Content-Disposition: inline; filename=calendar.ics');

        echo iCalendarSerializer::Render($this->reservations, $this->calendarName);
    }
}
