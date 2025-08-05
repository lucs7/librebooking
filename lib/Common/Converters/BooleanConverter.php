<?php

class BooleanConverter implements IConvert
{
    public function Convert($value)
    {
        return self::ConvertValue($value);
    }

    /**
     * @return bool
     */
    public static function ConvertValue($value)
    {
        return true === $value || 'true' == strtolower($value) || 1 === $value || '1' === $value;
    }
}
