<?php

declare(strict_types=1);

require_once(__DIR__ . '/DatabaseTestCase.php');
require_once(ROOT_DIR . 'lib/Database/MySQL/namespace.php');

/**
 * Runs Database/MySqlConnection against a real database. Uses its own scratch
 * tables, so it does not need the LibreBooking schema to be loaded.
 */
class MySqlConnectionDatabaseTest extends DatabaseTestCase
{
    private const TABLE = 'integration_pdo_values';
    private const SECRET_TABLE = 'integration_pdo_secrets';
    private const SECRET = 'top-secret-value';

    private Database $database;

    public function setUp(): void
    {
        parent::setUp();

        $hostSpec = $this->dbPort === '3306' ? $this->dbHost : $this->dbHost . ':' . $this->dbPort;
        $this->database = new Database(new MySqlConnection($this->dbUser, $this->dbPassword, $hostSpec, $this->dbName));
        $this->dropTables();
        $this->database->Execute(new AdHocCommand(
            'CREATE TABLE `' . self::TABLE . '` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `a` VARCHAR(100), `b` VARCHAR(100)) ENGINE=InnoDB'
        ));
        $this->database->Execute(new AdHocCommand(
            'CREATE TABLE `' . self::SECRET_TABLE . '` (`secret` VARCHAR(100) NOT NULL) ENGINE=InnoDB'
        ));
        $this->database->Execute($this->command('INSERT INTO `' . self::SECRET_TABLE . '` (`secret`) VALUES (@secret)', ['@secret' => self::SECRET]));
    }

    public function tearDown(): void
    {
        if (isset($this->database)) {
            $this->dropTables();
        }

        parent::tearDown();
    }

    public function testRowsComeBackAsStringsAndNull(): void
    {
        $this->insert('x', null);

        $row = $this->database->Query($this->command('SELECT `id`, `a`, `b` FROM `' . self::TABLE . '` WHERE `a` = @a', ['@a' => 'x']))->GetRow();

        $this->assertSame(['id' => '1', 'a' => 'x', 'b' => null], $row);
    }

    public function testReaderCountsRowsAndEndsWithFalse(): void
    {
        $this->insert('one', '1');
        $this->insert('two', '2');

        $reader = $this->database->Query(new AdHocCommand('SELECT `a` FROM `' . self::TABLE . '` ORDER BY `id`'));

        $this->assertSame(2, $reader->NumRows());
        $this->assertSame(['a' => 'one'], $reader->GetRow());
        $this->assertSame(['a' => 'two'], $reader->GetRow());
        $this->assertFalse($reader->GetRow());
    }

    public function testExecuteInsertReturnsTheNewId(): void
    {
        $id = $this->database->ExecuteInsert($this->insertCommand('x', 'y'));

        $this->assertSame(1, $id);
    }

    public function testExecuteAffectedRowsCountsOnlyChangedRows(): void
    {
        $this->insert('one', 'x');
        $update = 'UPDATE `' . self::TABLE . '` SET `a` = @a WHERE `id` = @id';

        $this->assertSame(1, $this->database->ExecuteAffectedRows($this->command($update, ['@id' => 1, '@a' => 'uno'])));
        $this->assertSame(0, $this->database->ExecuteAffectedRows($this->command($update, ['@id' => 1, '@a' => 'uno'])));
    }

    public function testExecuteAffectedRowsCountsADeletedRow(): void
    {
        $this->insert('one', '1');
        $this->insert('two', '2');

        $affected = $this->database->ExecuteAffectedRows($this->command('DELETE FROM `' . self::TABLE . '` WHERE `id` = @id', ['@id' => 1]));

        $this->assertSame(1, $affected);
        $this->assertSame(1, $this->database->Query(new AdHocCommand('SELECT `id` FROM `' . self::TABLE . '`'))->NumRows());
    }

    public function testExecuteAffectedRowsIsZeroWhenNothingMatches(): void
    {
        $this->insert('one', '1');

        $this->assertSame(0, $this->database->ExecuteAffectedRows($this->command('DELETE FROM `' . self::TABLE . '` WHERE `id` = @id', ['@id' => 99])));
    }

    public function testExecuteAffectedRowsCountsAConditionalInsert(): void
    {
        $insertIfMissing = 'INSERT INTO `' . self::TABLE . '` (`id`, `a`) SELECT @id, @a FROM DUAL '
            . 'WHERE NOT EXISTS (SELECT 1 FROM `' . self::TABLE . '` WHERE `a` = @a)';

        $this->assertSame(1, $this->database->ExecuteAffectedRows($this->command($insertIfMissing, ['@id' => 3, '@a' => 'three'])));
        $this->assertSame(0, $this->database->ExecuteAffectedRows($this->command($insertIfMissing, ['@id' => 4, '@a' => 'three'])));
    }

    public function testExecuteAffectedRowsRejectsMultiQueryCommands(): void
    {
        $command = new class ('query one; query two') extends SqlCommand {
            public function IsMultiQuery()
            {
                return true;
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->database->ExecuteAffectedRows($command);
    }

    public function testArrayParameterBecomesAnInList(): void
    {
        $this->insert('one', '1');
        $this->insert('two', '2');
        $this->insert('three', '3');

        $reader = $this->database->Query($this->command('SELECT `a` FROM `' . self::TABLE . '` WHERE `a` IN (@names) ORDER BY `id`', ['@names' => ['one', 'three']]));

        $this->assertSame(2, $reader->NumRows());
    }

    public function testNullParameterIsStoredAsNull(): void
    {
        $this->database->Execute($this->insertCommand('x', null));

        $row = $this->database->Query(new AdHocCommand('SELECT `b` FROM `' . self::TABLE . '`'))->GetRow();

        $this->assertNull($row['b']);
    }

    public function testValueWithQuotesAndBackslashesIsStoredVerbatim(): void
    {
        $value = "it's a \\ \"test\" ; DROP TABLE x";
        $this->insert($value, null);

        $row = $this->database->Query(new AdHocCommand('SELECT `a` FROM `' . self::TABLE . '`'))->GetRow();

        $this->assertSame($value, $row['a']);
    }

    public function testValueContainingAPlaceholderNameCannotChangeTheStatement(): void
    {
        $payload = ',(SELECT `secret` FROM `' . self::SECRET_TABLE . '` LIMIT 1))#';
        $this->insert('x@b', $payload);

        $row = $this->database->Query(new AdHocCommand('SELECT `a`, `b` FROM `' . self::TABLE . '`'))->GetRow();

        $this->assertSame(['a' => 'x@b', 'b' => $payload], $row);
    }

    public function testRawParameterIsInsertedAsAnIdentifier(): void
    {
        $this->insert('b-row', '2');
        $this->insert('a-row', '1');

        $command = new SqlCommand('SELECT `a` FROM `' . self::TABLE . '` ORDER BY @sort_params ASC');
        $command->AddParameter(new ParameterRaw('@sort_params', 'b'));

        $this->assertSame(['a' => 'a-row'], $this->database->Query($command)->GetRow());
    }

    public function testMultiQueryCommandRunsEachStatement(): void
    {
        $this->insert('x', '1');
        $command = new class ('INSERT INTO `' . self::TABLE . '` (`a`, `b`) VALUES (@a, @b); UPDATE `' . self::TABLE . '` SET `b` = @b WHERE `id` = 1') extends SqlCommand {
            public function IsMultiQuery()
            {
                return true;
            }
        };
        $command->AddParameter(new Parameter('@a', 'new'));
        $command->AddParameter(new Parameter('@b', 'shared'));

        $this->database->Execute($command);

        $reader = $this->database->Query(new AdHocCommand('SELECT `b` FROM `' . self::TABLE . '` ORDER BY `id`'));
        $this->assertSame(['b' => 'shared'], $reader->GetRow());
        $this->assertSame(['b' => 'shared'], $reader->GetRow());
    }

    public function testLimitQueryLimitsRows(): void
    {
        $this->insert('one', '1');
        $this->insert('two', '2');

        $reader = $this->database->LimitQuery(new AdHocCommand('SELECT `a` FROM `' . self::TABLE . '` ORDER BY `id`'), 1, 1);

        $this->assertSame(['a' => 'two'], $reader->GetRow());
    }

    public function testErrorsAreThrownAndTheConnectionStillWorks(): void
    {
        try {
            $this->database->Execute(new AdHocCommand('DELETE FROM `no_such_table_for_pdo_test`'));
            $this->fail('Expected an exception for a query against a missing table');
        } catch (Exception $exception) {
            $this->assertStringContainsString('no_such_table_for_pdo_test', $exception->getMessage());
        }

        $this->insert('still', 'works');
        $this->assertSame(1, $this->database->Query(new AdHocCommand('SELECT `a` FROM `' . self::TABLE . '`'))->NumRows());
    }

    public function testUnknownDatabaseThrowsDatabaseNotFound(): void
    {
        $connection = new Database(new MySqlConnection($this->dbUser, $this->dbPassword, $this->dbHost, 'no_such_database_test'));

        $this->expectException(DatabaseException::class);

        $connection->Query(new AdHocCommand('SELECT 1'));
    }

    private function insert(string $a, ?string $b): void
    {
        $this->database->Execute($this->insertCommand($a, $b));
    }

    private function insertCommand(string $a, ?string $b): SqlCommand
    {
        return $this->command('INSERT INTO `' . self::TABLE . '` (`a`, `b`) VALUES (@a, @b)', ['@a' => $a, '@b' => $b]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function command(string $sql, array $parameters): SqlCommand
    {
        $command = new SqlCommand($sql);
        foreach ($parameters as $name => $value) {
            $command->AddParameter(new Parameter($name, $value));
        }

        return $command;
    }

    private function dropTables(): void
    {
        $this->database->Execute(new AdHocCommand('DROP TABLE IF EXISTS `' . self::TABLE . '`'));
        $this->database->Execute(new AdHocCommand('DROP TABLE IF EXISTS `' . self::SECRET_TABLE . '`'));
    }
}
