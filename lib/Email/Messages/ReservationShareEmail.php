<?php

use LibreBooking\Calendar\IcsMethod;

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationShareEmail extends ReservationEmailMessage
{
    /**
     * @var string
     */
    private $emailToShare;

    public function __construct(User $reservationOwner, $emailToShare, ReservationSeries $reservationSeries, IAttributeRepository $attributeRepository, IUserRepository $userRepository)
    {
        parent::__construct($reservationOwner, $reservationSeries, $reservationOwner->Language(), $attributeRepository, $userRepository);

        $this->reservationOwner = $reservationOwner;
        $this->reservationSeries = $reservationSeries;
        $this->timezone = $reservationOwner->Timezone();
        $this->emailToShare = $emailToShare;
    }

    public function To()
    {
        return [new EmailAddress($this->emailToShare)];
    }

    public function Subject()
    {
        return $this->Translate('ReservationShareSubject', [$this->reservationSeries->BookedBy()->FullName(), $this->primaryResource->GetName()]);
    }

    public function ReplyTo()
    {
        return new EmailAddress($this->reservationOwner->EmailAddress(), $this->reservationOwner->FullName());
    }

    public function GetTemplateName()
    {
        return 'ReservationCreated.tpl';
    }

    protected function GetIcsMethod(Reservation $currentInstance): IcsMethod
    {
        return IcsMethod::PUBLISH;
    }
}
