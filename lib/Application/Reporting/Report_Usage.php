<?php

class Report_Usage
{
    public const RESOURCES = 'RESOURCES';
    public const ACCESSORIES = 'ACCESSORIES';

    /**
     * @var Report_Usage|string
     */
    private $usage;

    /**
     * @param $usage string|Report_Usage
     */
    public function __construct($usage)
    {
        $this->usage = $usage;
    }

    public function Add(ReportCommandBuilder $builder)
    {
        if (self::ACCESSORIES == $this->usage) {
            $builder->OfAccessories();
        } else {
            $builder->OfResources();
        }
    }

    public function __toString()
    {
        return $this->usage;
    }
}
