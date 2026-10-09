<?php

use LibreBooking\Database\PreparedQuery;
use LibreBooking\Database\PreparedQueryBuilder;

/**
 * IDbConnection on PDO with real (server-side) prepared statements. Values are
 * bound, never spliced into the SQL text.
 */
class MySqlConnection implements IDbConnection
{
    private const SQL_MODE = "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";
    private const ERROR_UNKNOWN_DATABASE = 1049;

    private $_dbUser = '';
    private $_dbPassword = '';
    private $_hostSpec = '';
    private $_dbName = '';
    private $_port = null;

    /**
     * @var PDO|null
     */
    private $_db = null;

    /**
     * @param string $dbUser
     * @param string $dbPassword
     * @param string $hostSpec host or host:port
     * @param string $dbName
     */
    public function __construct($dbUser, $dbPassword, $hostSpec, $dbName)
    {
        $this->_dbUser = $dbUser;
        $this->_dbPassword = $dbPassword;
        $this->_hostSpec = $hostSpec;
        $this->_dbName = $dbName;
    }

    public function Connect()
    {
        if (!is_null($this->_db)) {
            return;
        }

        $host = $this->_hostSpec;
        if (BookedStringHelper::Contains($host, ':')) {
            $parts = explode(':', $host);
            $host = $parts[0];
            $this->_port = intval($parts[1]);
        }

        $dsn = 'mysql:host=' . $host . ($this->_port ? ';port=' . $this->_port : '') . ';dbname=' . $this->_dbName . ';charset=utf8mb4';

        try {
            $this->_db = new PDO($dsn, $this->_dbUser, $this->_dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Keep every column a string (or null), as the mysqli text protocol returned them.
                PDO::ATTR_STRINGIFY_FETCHES => true,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === self::ERROR_UNKNOWN_DATABASE) {
                Log::Error("Error selecting database '%s'\nCheck your database settings in the config file\n%s", $this->_dbName, $e->getMessage());
                throw new DatabaseNotFoundException("Error selecting database\nError: " . $e->getMessage());
            }

            Log::Error("Error connecting to database\nCheck your database settings in the config file\n%s", $e->getMessage());
            throw new DatabaseConnectionException("Error connecting to database\nError: " . $e->getMessage());
        }
    }

    public function Disconnect()
    {
        $this->_db = null;
    }

    public function Query(ISqlCommand $sqlCommand)
    {
        $query = $this->Prepare($sqlCommand->GetQuery(), $sqlCommand);

        if ($sqlCommand->ContainsGroupConcat()) {
            $this->_db->exec('SET SESSION group_concat_max_len = 1000000;');
        }
        $this->_db->exec(self::SQL_MODE);

        $statement = $this->Run($query, 'MySql Query: ');
        $rows = $statement->fetchAll();
        $statement->closeCursor();

        return new MySqlReader($rows);
    }

    public function LimitQuery(ISqlCommand $command, $limit, $offset = 0)
    {
        if (!$command instanceof SqlCommand) {
            throw new InvalidArgumentException(
                sprintf('MySqlConnection::LimitQuery requires %s, got %s', SqlCommand::class, get_debug_type($command))
            );
        }

        return $this->Query(new MySqlLimitCommand($command, $limit, $offset));
    }

    public function Execute(ISqlCommand $sqlCommand)
    {
        $this->_db->exec(self::SQL_MODE);

        // A prepared statement holds one statement, so a multi-query command runs its statements in turn.
        $templates = $sqlCommand->IsMultiQuery()
            ? PreparedQueryBuilder::splitStatements($sqlCommand->GetQuery())
            : [$sqlCommand->GetQuery()];

        foreach ($templates as $template) {
            $this->Run($this->Prepare($template, $sqlCommand), 'MySql Execute: ');
        }
    }

    public function ExecuteAffectedRows(ISqlCommand $sqlCommand): int
    {
        if ($sqlCommand->IsMultiQuery()) {
            throw new InvalidArgumentException(
                sprintf('MySqlConnection::ExecuteAffectedRows does not support multi-query commands, got %s', get_debug_type($sqlCommand))
            );
        }

        $this->_db->exec(self::SQL_MODE);

        // Like mysqli_affected_rows, rowCount() counts only rows whose values actually changed.
        return $this->Run($this->Prepare($sqlCommand->GetQuery(), $sqlCommand), 'MySql ExecuteAffectedRows: ')->rowCount();
    }

    public function GetLastInsertId()
    {
        return (int)$this->_db->lastInsertId();
    }

    private function Prepare(string $template, SqlCommand $sqlCommand): PreparedQuery
    {
        $parameters = [];
        $rawNames = [];

        for ($p = 0; $p < $sqlCommand->Parameters->Count(); $p++) {
            $parameter = $sqlCommand->Parameters->Items($p);
            if (array_key_exists($parameter->Name, $parameters)) {
                continue; // the first parameter of a name wins, as before
            }

            $parameters[$parameter->Name] = $parameter->Value;
            if ($parameter instanceof ParameterRaw) {
                $rawNames[] = $parameter->Name;
            }
        }

        return PreparedQueryBuilder::build($template, $parameters, $rawNames);
    }

    private function Run(PreparedQuery $query, string $logPrefix): PDOStatement
    {
        if (Log::DebugEnabled()) {
            Log::Sql($logPrefix . str_replace('%', '%%', $query->sql));
        }

        try {
            $statement = $this->_db->prepare($query->sql);
            $statement->execute($query->values);
        } catch (PDOException $e) {
            Log::Error('Error executing PDO query %s', $e->getMessage());

            throw new Exception('There was an error executing your query\n' . $e->getMessage());
        }

        return $statement;
    }
}
