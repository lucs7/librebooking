<?php

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationCreatedEmail extends ReservationEmailMessage
{
    public function Subject()
    {
        return $this->Translate('ReservationCreatedSubjectWithResource', [$this->primaryResource->GetName()]);
    }

    protected function GetTemplateName()
    {
        return 'ReservationCreated.tpl';
    }
}
