<?php

class ReservationStartTimeConstraint
{
    public const _DEFAULT = 'future';
    public const FUTURE = 'future';
    public const CURRENT = 'current';
    public const NONE = 'none';

    /**
     * @static
     *
     * @return bool
     */
    public static function IsCurrent(?string $startTimeConstraint)
    {
        return self::CURRENT == strtolower($startTimeConstraint ?? '');
    }

    /**
     * @static
     *
     * @return bool
     */
    public static function IsNone(?string $startTimeConstraint)
    {
        return self::NONE == strtolower($startTimeConstraint ?? '');
    }

    /**
     * @static
     *
     * @return bool
     */
    public static function IsFuture(?string $startTimeConstraint)
    {
        return self::FUTURE == strtolower($startTimeConstraint ?? '');
    }
}
