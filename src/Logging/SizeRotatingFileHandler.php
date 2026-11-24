<?php

declare(strict_types=1);

namespace LibreBooking\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Writes to one file and, once it reaches $maxBytes, renames it to "<file>.<timestamp>"
 * and starts a new file. Rotated files are kept; deleting old ones is up to the admin
 * (or logrotate). The current file name never changes, so tail -f and log shippers
 * keep working.
 */
final class SizeRotatingFileHandler extends StreamHandler
{
    private const TIMESTAMP = 'Ymd-His';

    public function __construct(private string $file, private int $maxBytes, int|string|Level $level)
    {
        parent::__construct(stream: $file, level: $level);
    }

    protected function write(LogRecord $record): void
    {
        clearstatcache(clear_realpath_cache: true, filename: $this->file);
        if (@filesize($this->file) >= $this->maxBytes) {
            $this->close();
            @rename(from: $this->file, to: $this->rotatedName());
        }

        parent::write($record);
    }

    /** "<file>.<timestamp>", with "-<n>" appended if that name is already taken. */
    private function rotatedName(): string
    {
        $base = $this->file . '.' . date(format: self::TIMESTAMP);
        $name = $base;
        for ($n = 1; file_exists($name); $n++) {
            $name = "{$base}-{$n}";
        }

        return $name;
    }
}
