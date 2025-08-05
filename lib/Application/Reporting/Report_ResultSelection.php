<?php

class Report_ResultSelection
{
    public const COUNT = 'COUNT';
    public const TIME = 'TIME';
    public const FULL_LIST = 'LIST';
    public const UTILIZATION = 'UTILIZATION';

    /**
     * @var Report_ResultSelection|string
     */
    private $selection;

    /**
     * @param $selection string|Report_ResultSelection
     */
    public function __construct($selection)
    {
        $this->selection = $selection;
    }

    public function Add(ReportCommandBuilder $builder)
    {
        if (self::FULL_LIST == $this->selection) {
            $builder->SelectFullList();
        }
        if (self::COUNT == $this->selection) {
            $builder->SelectCount();
        }
        if (self::TIME == $this->selection) {
            $builder->SelectTime();
        }
        if (self::UTILIZATION == $this->selection) {
            $builder->SelectDuration()->IncludingBlackouts()->OfResources();
        }
    }

    /**
     * @param $selection string
     *
     * @return bool
     */
    public function Equals($selection)
    {
        return $this->selection == $selection;
    }

    public function __toString()
    {
        return $this->selection;
    }
}
