<?php

class MySqlLimitCommand extends SqlCommand
{
    /**
     * @var SqlCommand
     */
    private $baseCommand;

    private $limit;
    private $offset;

    public function __construct(SqlCommand $baseCommand, $limit, $offset)
    {
        parent::__construct();

        $this->baseCommand = $baseCommand;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->Parameters = $baseCommand->Parameters;
    }

    public function GetQuery()
    {
        return $this->baseCommand->GetQuery() . sprintf(' LIMIT %s OFFSET %s', $this->limit, $this->offset);
    }

    public function ContainsGroupConcat()
    {
        return $this->baseCommand->ContainsGroupConcat();
    }
}
