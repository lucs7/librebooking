<?php

class MySqlReader implements IReader
{
    /**
     * @var array<int, array<string, string|null>>
     */
    private $_rows;

    private $_position = 0;

    /**
     * Rows are read eagerly so the reader does not keep the connection alive after Disconnect().
     *
     * @param array<int, array<string, string|null>> $rows
     */
    public function __construct(array $rows)
    {
        $this->_rows = $rows;
    }

    public function GetRow()
    {
        if ($this->_position >= count($this->_rows)) {
            return false;
        }

        return $this->_rows[$this->_position++];
    }

    public function NumRows()
    {
        return count($this->_rows);
    }

    public function Free()
    {
        $this->_rows = [];
        $this->_position = 0;
    }
}
