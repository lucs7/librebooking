<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Database;

use LibreBooking\Database\PreparedQueryBuilder;
use PHPUnit\Framework\TestCase;

class PreparedQueryBuilderTest extends TestCase
{
    public function testScalarsBecomePositionalPlaceholdersBoundInOrder(): void
    {
        $query = PreparedQueryBuilder::build(
            'SELECT * FROM t WHERE a = @a AND b = @b',
            ['@a' => "O'Brien", '@b' => 5]
        );

        $this->assertSame('SELECT * FROM t WHERE a = ? AND b = ?', $query->sql);
        $this->assertSame(["O'Brien", '5'], $query->values);
    }

    public function testValueNeverEntersTheSql(): void
    {
        $query = PreparedQueryBuilder::build(
            'INSERT INTO t (a, b) VALUES (@a, @b)',
            ['@a' => 'x@b', '@b' => ',(SELECT secret FROM s))#']
        );

        $this->assertSame('INSERT INTO t (a, b) VALUES (?, ?)', $query->sql);
        $this->assertSame(['x@b', ',(SELECT secret FROM s))#'], $query->values);
    }

    public function testPlaceholderUsedTwiceIsBoundTwice(): void
    {
        $query = PreparedQueryBuilder::build('WHERE a = @id OR b = @id', ['@id' => 7]);

        $this->assertSame('WHERE a = ? OR b = ?', $query->sql);
        $this->assertSame(['7', '7'], $query->values);
    }

    public function testNullIsBoundAsNull(): void
    {
        $query = PreparedQueryBuilder::build('SET a = @a', ['@a' => null]);

        $this->assertSame('SET a = ?', $query->sql);
        $this->assertSame([null], $query->values);
    }

    public function testArrayExpandsToOnePlaceholderPerItem(): void
    {
        $query = PreparedQueryBuilder::build('WHERE id IN (@ids)', ['@ids' => [1, 2, 3]]);

        $this->assertSame('WHERE id IN (?,?,?)', $query->sql);
        $this->assertSame(['1', '2', '3'], $query->values);
    }

    public function testEmptyArrayBindsOneEmptyString(): void
    {
        $query = PreparedQueryBuilder::build('WHERE id IN (@ids)', ['@ids' => []]);

        $this->assertSame('WHERE id IN (?)', $query->sql);
        $this->assertSame([''], $query->values);
    }

    public function testRawNameIsInsertedAsIs(): void
    {
        $query = PreparedQueryBuilder::build(
            'ORDER BY @sort_params asc LIMIT 1',
            ['@sort_params' => 'lname'],
            ['@sort_params']
        );

        $this->assertSame('ORDER BY lname asc LIMIT 1', $query->sql);
        $this->assertSame([], $query->values);
    }

    public function testLongestNameWinsOverItsPrefix(): void
    {
        $query = PreparedQueryBuilder::build('WHERE a = @user AND b = @userid', ['@user' => 'u', '@userid' => 'id']);

        $this->assertSame('WHERE a = ? AND b = ?', $query->sql);
        $this->assertSame(['u', 'id'], $query->values);
    }

    public function testNoParametersLeavesTemplateUntouched(): void
    {
        $query = PreparedQueryBuilder::build('SELECT 1', []);

        $this->assertSame('SELECT 1', $query->sql);
        $this->assertSame([], $query->values);
    }

    public function testSplitStatementsDropsEmptyOnes(): void
    {
        $this->assertSame(['INSERT a', 'UPDATE b'], PreparedQueryBuilder::splitStatements("INSERT a;\n UPDATE b;\n"));
    }
}
