<?php

class Parameter
{
    /**
     * @var string
     */
    public $Name;

    public $Value;

    public function __construct($name = null, $value = null)
    {
        $this->Name = $name;
        $this->Value = $value;
    }

    public function QuotedValue($value)
    {
        return "'$value'";
    }
}

class ParameterRaw extends Parameter
{
    public function QuotedValue($value)
    {
        return $value;
    }
}
