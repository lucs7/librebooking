<?php

require_once ROOT_DIR.'Domain/Access/BlackoutRepository.php';

class FakeBlackoutRepository implements IBlackoutRepository
{
    /**
     * @var BlackoutSeries
     */
    public $_Added;
    /**
     * @var BlackoutSeries
     */
    public $_Updated;
    /**
     * @var int
     */
    public $_DeletedId;
    /**
     * @var int
     */
    public $_DeletedSeriesId;
    /**
     * @var BlackoutSeries
     */
    public $_Series;
    /**
     * @var int
     */
    public $_LoadedBlackoutId;

    public function Add(BlackoutSeries $blackoutSeries)
    {
        $this->_Added = $blackoutSeries;

        return null;
    }

    public function Update(BlackoutSeries $blackoutSeries)
    {
        $this->_Updated = $blackoutSeries;
    }

    public function Delete($blackoutId)
    {
        $this->_DeletedId = $blackoutId;
    }

    public function DeleteSeries($blackoutId)
    {
        $this->_DeletedSeriesId = $blackoutId;
    }

    public function LoadByBlackoutId($blackoutId)
    {
        $this->_LoadedBlackoutId = $blackoutId;

        return $this->_Series;
    }
}
