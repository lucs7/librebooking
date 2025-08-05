<?php

class ReservationReminderView
{
    /**
     * @var int
     */
    private $value;

    /**
     * @var ReservationReminderInterval|string
     */
    private $interval;

    /**
     * @var int
     */
    private $minutes;

    public function GetValue()
    {
        return $this->value;
    }

    public function GetInterval()
    {
        return $this->interval;
    }

    public function __construct($minutes)
    {
        $this->minutes = $minutes;
        if (0 == $minutes % 1440) {
            $this->value = $minutes / 1440;
            $this->interval = ReservationReminderInterval::Days;
        } elseif (0 == $minutes % 60) {
            $this->value = $minutes / 60;
            $this->interval = ReservationReminderInterval::Hours;
        } else {
            $this->value = $minutes;
            $this->interval = ReservationReminderInterval::Minutes;
        }
    }

    /**
     * @return int
     */
    public function MinutesPrior()
    {
        return $this->minutes;
    }
}
