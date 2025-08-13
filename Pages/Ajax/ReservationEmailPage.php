<?php

namespace LibreBooking\Pages\Ajax;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

require_once(ROOT_DIR . 'Presenters/Reservation/ReservationEmailPresenter.php');

interface IReservationEmailPage
{
    /**
     * @return string
     */
    public function GetReferenceNumber();

    /**
     * @return string[]
     */
    public function GetEmailAddresses();
}

class ReservationEmailPage extends Page implements IReservationEmailPage
{
    /**
     * @var ReservationEmailPresenter
     */
    private $presenter;


    public function __construct()
    {
        parent::__construct();

        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $this->presenter = new ReservationEmailPresenter(
            $this,
            $userSession,
            new ReservationRepository(),
            new UserRepository(),
            new AttributeRepository(),
            PluginManager::Instance()->LoadPermission()
        );
    }

    public function PageLoad()
    {
        $this->EnforceCSRFCheck();

        $this->presenter->PageLoad();
    }

    public function GetReferenceNumber()
    {
        return $this->GetQuerystring(QueryStringKeys::REFERENCE_NUMBER);
    }

    public function GetEmailAddresses()
    {
        $email = implode(',',$this->GetForm('email')); 
        return preg_split('/, ?/', $email);
    }
}
class_alias(__NAMESPACE__ . '\\IReservationEmailPage', 'IReservationEmailPage');
class_alias(__NAMESPACE__ . '\\ReservationEmailPage', 'ReservationEmailPage');
