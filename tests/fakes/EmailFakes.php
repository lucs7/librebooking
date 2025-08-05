<?php

@define('BASE_DIR', dirname(__FILE__).'/../..');
require_once ROOT_DIR.'lib/Email/namespace.php';

use PHPMailer\PHPMailer\PHPMailer;

class FakeMailer extends PHPMailer
{
    public $addresses = [];
    public $Subject;
    public $Body;
    public $sendWasCalled = false;
    public $isHtml = true;

    public function __construct()
    {
    }

    public function AddAddress($address, $name = '')
    {
        $this->addresses[] = $address;
    }

    public function Send()
    {
        $this->sendWasCalled = true;
    }

    public function IsHTML($bool = true)
    {
        $this->isHtml = $bool;
    }
}
