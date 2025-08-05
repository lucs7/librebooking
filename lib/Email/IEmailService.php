<?php

interface IEmailService
{
    public function Send(IEmailMessage $emailMessage);
}
