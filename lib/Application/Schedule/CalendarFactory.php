<?php

class CalendarTypes
{
    public const Month = 'month';
    public const Week = 'agendaWeek';
    public const Day = 'agendaDay';
}

interface ICalendarFactory
{
    /**
     * @abstract
     *
     * @return ICalendarSegment
     */
    public function Create($type, $year, $month, $day, $timezone);
}

class CalendarFactory implements ICalendarFactory
{
    public function Create($type, $year, $month, $day, $timezone)
    {
        if (CalendarTypes::Day == $type) {
            return new CalendarDay(Date::Create($year, $month, $day, 0, 0, 0, $timezone));
        }

        if (CalendarTypes::Week == $type) {
            return CalendarWeek::FromDate($year, $month, $day, $timezone);
        }

        return new CalendarMonth($month, $year, $timezone);
    }
}
