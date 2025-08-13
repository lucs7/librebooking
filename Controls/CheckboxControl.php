<?php

namespace LibreBooking\Controls;

class CheckboxControl extends Control
{
    public function PageLoad()
    {
        $this->Set('name', \FormKeys::Evaluate($this->Get('name-key')));
        $this->Set('label', \Resources::GetInstance()->GetString($this->Get('label-key')));
        $this->Display('Controls/Checkbox.tpl');
    }
}

class_alias(CheckboxControl::class, 'CheckboxControl');
