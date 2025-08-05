<?php

define('ROOT_DIR', '../../');

require_once ROOT_DIR.'Pages/Ajax/ReservationDeletePage.php';

$page = DeleteReservationPageFactory::Create();
$page->PageLoad();

class DeleteReservationPageFactory
{
    public static function Create()
    {
        if ('json' == ServiceLocator::GetServer()->GetQuerystring(QueryStringKeys::RESPONSE_TYPE)) {
            return new ReservationDeleteJsonPage();
        } else {
            return new ReservationDeletePage();
        }
    }
}
