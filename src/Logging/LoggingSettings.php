<?php

declare(strict_types=1);

namespace LibreBooking\Logging;

use Monolog\Level;

/**
 * The logging.* configuration, parsed once.
 *
 * - level: error, warning, info, debug or 'none', case-insensitive, default 'error'; an unknown
 *   level falls back to 'error' with one warning
 * - SQL logging is its own switch, independent of the level
 * - rotateSize > 0: a log file of that many bytes is renamed to <file>.<timestamp>; 0 disables rotation
 */
final readonly class LoggingSettings
{
    public const NO_ROTATION = 0;
    private const DEFAULT_LEVEL = Level::Error;
    private const NONE = 'none';
    /** The levels LibreBooking writes; other PSR-3 levels are not accepted as a threshold. */
    private const LEVELS = [Level::Error, Level::Warning, Level::Info, Level::Debug];

    /**
     * @param Level|null $level null means logging is off ('none')
     */
    public function __construct(
        public ?Level $level,
        public string $folder,
        public bool $sqlEnabled,
        public int $rotateSize = self::NO_ROTATION,
    ) {
    }

    /**
     * @param ?callable(string): void $warn receives a warning when the level is not recognized
     */
    public static function fromValues(
        ?string $level,
        ?string $folder,
        bool $sqlEnabled,
        int $rotateSize = self::NO_ROTATION,
        ?callable $warn = null,
    ): self {
        return new self(
            level: self::parseLevel(level: $level, warn: $warn ?? error_log(...)),
            folder: rtrim(string: (string) $folder, characters: '/'),
            sqlEnabled: $sqlEnabled,
            rotateSize: max(self::NO_ROTATION, $rotateSize),
        );
    }

    /** The file a channel writes to: <folder>/<channel>.log. */
    public function target(string $channel): string
    {
        return "{$this->folder}/{$channel}.log";
    }

    /** @param callable(string): void $warn */
    private static function parseLevel(?string $level, callable $warn): ?Level
    {
        $name = strtolower(trim((string) $level));
        if ($name === '') {
            return self::DEFAULT_LEVEL;
        }
        if ($name === self::NONE) {
            return null;
        }
        foreach (self::LEVELS as $candidate) {
            if (strtolower($candidate->getName()) === $name) {
                return $candidate;
            }
        }
        $warn(sprintf(
            "[LOGGING] Unknown logging.level '%s', using '%s'. Valid: none, %s",
            $level,
            strtolower(self::DEFAULT_LEVEL->getName()),
            implode(', ', array_map(static fn (Level $l): string => strtolower($l->getName()), self::LEVELS)),
        ));
        return self::DEFAULT_LEVEL;
    }
}
