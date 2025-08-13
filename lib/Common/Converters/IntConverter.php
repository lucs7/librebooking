<?php

namespace LibreBooking\Common\Converters;

class IntConverter implements IConvert
{
    public function Convert($value)
    {
        return intval($value);
    }

    public function IsValid($value): bool
    {
        return is_numeric($value) && intval($value) == $value;
    }
}

class_alias(IntConverter::class, 'IntConverter');
