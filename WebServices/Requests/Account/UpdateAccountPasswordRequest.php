<?php

namespace LibreBooking\WebServices\Requests\Account;

use JsonRequest;

class UpdateAccountPasswordRequest extends JsonRequest
{
    public $currentPassword;
    public $newPassword;

    public static function Example()
    {
        $request = new UpdateAccountPasswordRequest();
        $request->currentPassword = 'plain.text.current.password';
        $request->newPassword = 'plain.text.new.password';

        return $request;
    }
}

class_alias(UpdateAccountPasswordRequest::class, 'UpdateAccountPasswordRequest');
