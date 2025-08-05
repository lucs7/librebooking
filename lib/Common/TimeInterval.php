<?php

class TimeInterval
{
    /**
     * @var DateDiff
     */
    private $interval;

    /**
     * @param int $seconds
     */
    public function __construct($seconds)
    {
        $this->interval = null;

        if (!empty($seconds)) {
            $this->interval = new DateDiff($seconds);
        }
    }

    /**
     * @static
     *
     * @param string|int $interval string interval in format: #d#h#m ie: 22d4h12m or total seconds
     *
     * @return TimeInterval
     */
    public static function Parse($interval)
    {
        if (is_a($interval, 'TimeInterval')) {
            return $interval;
        }

        if (empty($interval)) {
            return new TimeInterval(0);
        }

        if (!is_numeric($interval)) {
            $seconds = DateDiff::FromTimeString($interval)->TotalSeconds();
        } else {
            $seconds = $interval;
        }

        return new TimeInterval($seconds);
    }

    /**
     * @return TimeInterval
     */
    public static function FromMinutes($minutes)
    {
        return TimeInterval::Parse($minutes * 60);
    }

    /**
     * @return TimeInterval
     */
    public static function FromHours($hours)
    {
        return TimeInterval::Parse($hours * 60 * 60);
    }

    /**
     * @return TimeInterval
     */
    public static function FromDays($days)
    {
        return TimeInterval::Parse($days * 60 * 60 * 24);
    }

    /**
     * @return TimeInterval
     */
    public static function None()
    {
        return new TimeInterval(0);
    }

    /**
     * @return int
     */
    public function Days()
    {
        return $this->Interval()->Days();
    }

    /**
     * @return int
     */
    public function Hours()
    {
        return $this->Interval()->Hours();
    }

    /**
     * @return int
     */
    public function Minutes()
    {
        return $this->Interval()->Minutes();
    }

    /**
     * @return DateDiff
     */
    public function Interval()
    {
        return $this->Diff();
    }

    /**
     * @return DateDiff
     */
    public function Diff()
    {
        if (null != $this->interval) {
            return $this->interval;
        }

        return DateDiff::Null();
    }

    /**
     * @return int|null
     */
    public function ToDatabase()
    {
        if (null != $this->interval && !$this->interval->IsNull()) {
            return $this->interval->TotalSeconds();
        }

        return null;
    }

    /**
     * @return int
     */
    public function TotalSeconds()
    {
        if (null != $this->interval) {
            return $this->interval->TotalSeconds();
        }

        return 0;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (null != $this->interval) {
            return $this->interval->__toString();
        }

        return '';
    }

    /**
     * @return string
     */
    public function ToShortString()
    {
        if (null != $this->interval) {
            return $this->interval->ToString(true);
        }

        return '';
    }

    /**
     * @param bool $includeTotalHours
     *
     * @return string
     */
    public function ToString($includeTotalHours)
    {
        if ($includeTotalHours) {
            return $this->__toString().' ('.$this->TotalSeconds() / 3600 .'h)';
        }

        return $this->__toString();
    }
}
