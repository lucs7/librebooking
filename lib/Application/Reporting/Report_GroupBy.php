<?php

class Report_GroupBy
{
    public const NONE = 'NONE';
    public const RESOURCE = 'RESOURCE';
    public const SCHEDULE = 'SCHEDULE';
    public const USER = 'USER';
    public const GROUP = 'GROUP';

    /**
     * @var Report_GroupBy|string
     */
    private $groupBy;

    /**
     * @param $groupBy string|Report_GroupBy
     */
    public function __construct($groupBy)
    {
        $this->groupBy = $groupBy;
    }

    public function Add(ReportCommandBuilder $builder)
    {
        if (self::GROUP == $this->groupBy) {
            $builder->GroupByGroup();
        }
        if (self::SCHEDULE == $this->groupBy) {
            $builder->GroupBySchedule();
        }
        if (self::USER == $this->groupBy) {
            $builder->GroupByUser();
        }
        if (self::RESOURCE == $this->groupBy) {
            $builder->GroupByResource();
        }
    }

    public function __toString()
    {
        return $this->groupBy;
    }
}
