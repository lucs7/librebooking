<?php

class ReservationValidationResult implements IReservationValidationResult
{
    private $_canBeSaved;
    private $_errors;
    private $_warnings;
    private $_canBeRetried;
    private $_retryParams;
    private $_retryMessages;
    private $_canJoinWaitList;

    /**
     * @param                                   $canBeSaved      bool
     * @param                                   $errors          string[]
     * @param                                   $warnings        string[]
     * @param bool                              $canBeRetried
     * @param array|ReservationRetryParameter[] $retryParams
     * @param array|string[]                    $retryMessages
     * @param bool                              $canJoinWaitList
     */
    public function __construct($canBeSaved = true, $errors = null, $warnings = null, $canBeRetried = false, $retryParams = [], $retryMessages = [], $canJoinWaitList = false)
    {
        $this->_canBeSaved = $canBeSaved;
        $this->_errors = null == $errors ? [] : $errors;
        $this->_warnings = null == $warnings ? [] : $warnings;
        $this->_canBeRetried = $canBeRetried;
        $this->_retryParams = null == $retryParams ? [] : $retryParams;
        $this->_retryMessages = null == $retryMessages ? [] : $retryMessages;
        $this->_canJoinWaitList = null == $canJoinWaitList ? false : $canJoinWaitList;
    }

    public function CanBeSaved()
    {
        return $this->_canBeSaved;
    }

    public function GetErrors()
    {
        return $this->_errors;
    }

    public function GetWarnings()
    {
        return $this->_warnings;
    }

    public function CanBeRetried()
    {
        return $this->_canBeRetried;
    }

    public function GetRetryParameters()
    {
        return $this->_retryParams;
    }

    public function GetRetryMessages()
    {
        return $this->_retryMessages;
    }

    public function CanJoinWaitList()
    {
        return $this->_canJoinWaitList;
    }
}
