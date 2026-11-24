<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Logging;

use LibreBooking\Logging\LoggingSettings;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoggingSettingsTest extends TestCase
{
    /** @return array<string, array{string, ?Level}> */
    public static function levels(): array
    {
        return [
            'none' => ['none', null],
            'upper-case NONE' => ['NONE', null],
            'error' => ['error', Level::Error],
            'mixed-case DeBuG' => ['DeBuG', Level::Debug],
            'warning' => ['warning', Level::Warning],
            'empty uses default' => ['', Level::Error],
            'other PSR-3 level falls back' => ['notice', Level::Error],
            'critical falls back' => ['critical', Level::Error],
        ];
    }

    // error, warning, info, debug and 'none', case-insensitive, default 'error'
    #[DataProvider('levels')]
    public function testLevelIsParsedCaseInsensitively(string $value, ?Level $expected): void
    {
        $settings = LoggingSettings::fromValues(
            level: $value,
            folder: '/tmp',
            sqlEnabled: false,
            warn: static function (string $message): void {
            },
        );

        $this->assertSame($expected, $settings->level);
    }

    // an unknown level falls back to exactly 'error' with one warning naming the bad value
    public function testUnknownLevelFallsBackToErrorWithOneWarning(): void
    {
        $warnings = [];
        $settings = LoggingSettings::fromValues(
            level: 'verbose',
            folder: '/tmp',
            sqlEnabled: false,
            warn: static function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            },
        );

        $this->assertSame(Level::Error, $settings->level);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("'verbose'", $warnings[0]);
    }

    // entries go to <folder>/<channel>.log; a trailing slash on the folder is trimmed
    public function testFileTargetIsChannelFileInFolder(): void
    {
        $settings = LoggingSettings::fromValues(level: 'error', folder: '/var/log/lb/', sqlEnabled: false);

        $this->assertSame('/var/log/lb/app.log', $settings->target('app'));
        $this->assertSame('/var/log/lb/sql.log', $settings->target('sql'));
    }

    // rotation is off by default; a negative size also means no rotation
    public function testRotateSizeDefaultsToNoRotationAndNegativeMeansOff(): void
    {
        $this->assertSame(LoggingSettings::NO_ROTATION, LoggingSettings::fromValues(level: 'error', folder: '/tmp', sqlEnabled: false)->rotateSize);
        $settings = LoggingSettings::fromValues(level: 'error', folder: '/tmp', sqlEnabled: false, rotateSize: -3);

        $this->assertSame(LoggingSettings::NO_ROTATION, $settings->rotateSize);
    }
}
