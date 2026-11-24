<?php

declare(strict_types=1);

namespace LibreBooking\Logging;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FallbackGroupHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;

/**
 * Builds the PSR-3 loggers for the 'app' and 'sql' channels.
 *
 * - every write falls back to PHP's error_log when the target fails, and never throws
 * - entries carry the user id and, on 'app', the calling file/line as extra
 * - 'sql' logs at Debug and only follows logging.sql, not logging.level
 *
 * Loggers and settings are cached for the life of the process; long-running workers or
 * code that swaps Configuration must call reset().
 */
final class LoggerFactory
{
    public const APP = 'app';
    public const SQL = 'sql';

    /** @var array<string, Logger> */
    private static array $channels = [];
    private static ?LoggingSettings $settings = null;
    private static bool $building = false;
    private static string $stderr = 'php://stderr';

    /** The logger for $name, built from the current configuration on first use. */
    public static function channel(string $name): Logger
    {
        if (isset(self::$channels[$name])) {
            return self::$channels[$name];
        }
        if (self::$building) {
            // configuration or session lookup logged while we were building: drop that entry
            return new Logger(name: $name);
        }
        self::$building = true;
        try {
            self::$settings ??= self::settingsFromConfiguration();
            return self::$channels[$name] = self::create(
                name: $name,
                settings: self::$settings,
                userId: self::currentUserId(...),
            );
        } finally {
            self::$building = false;
        }
    }

    /**
     * @param callable(): (int|string|null) $userId
     */
    public static function create(string $name, LoggingSettings $settings, callable $userId): Logger
    {
        $logger = new Logger(name: $name);
        $threshold = $name === self::SQL
            ? ($settings->sqlEnabled ? Level::Debug : null)
            : $settings->level;
        if ($threshold === null) {
            return $logger;
        }

        $logger->pushHandler(new FallbackGroupHandler(handlers: [
            self::primaryHandler(name: $name, settings: $settings, threshold: $threshold),
            new ErrorLogHandler(level: $threshold),
        ]));
        // always also to stderr: the container output (docker logs) or the web server's error log
        $logger->pushHandler(new StreamHandler(stream: self::$stderr, level: $threshold));
        $logger->pushProcessor(new WebProcessor());
        if ($name !== self::SQL) {
            // one debug_backtrace per entry; skipped for SQL, which is logged per statement
            $logger->pushProcessor(new CallerProcessor());
        }
        $logger->pushProcessor(static function (LogRecord $record) use ($userId): LogRecord {
            // attribute by user id only, never name or email
            try {
                $record->extra['user_id'] = $userId();
            } catch (\Throwable) {
                // no session (CLI, early bootstrap): log without a user
            }
            return $record;
        });
        $logger->pushProcessor(new PsrLogMessageProcessor());

        return $logger;
    }

    /** @internal lets tests capture what would go to stderr */
    public static function setStderrTarget(string $stream): void
    {
        self::$stderr = $stream;
        self::reset();
    }

    /** Drops the cached loggers and settings so the next call re-reads the configuration. */
    public static function reset(): void
    {
        self::$channels = [];
        self::$settings = null;
    }

    private static function primaryHandler(string $name, LoggingSettings $settings, Level $threshold): HandlerInterface
    {
        if ($settings->rotateSize > LoggingSettings::NO_ROTATION) {
            return new SizeRotatingFileHandler(
                file: $settings->target($name),
                maxBytes: $settings->rotateSize,
                level: $threshold,
            );
        }

        return new StreamHandler(stream: $settings->target($name), level: $threshold);
    }

    private static function settingsFromConfiguration(): LoggingSettings
    {
        $config = \Configuration::Instance();

        return LoggingSettings::fromValues(
            level: $config->GetKey(\ConfigKeys::LOGGING_LEVEL),
            folder: $config->GetKey(\ConfigKeys::LOGGING_FOLDER),
            sqlEnabled: (bool) $config->GetKey(\ConfigKeys::LOGGING_SQL, new \BooleanConverter()),
            rotateSize: (int) $config->GetKey(\ConfigKeys::LOGGING_ROTATE_SIZE, new \IntConverter()),
        );
    }

    private static function currentUserId(): int|string|null
    {
        $userId = \ServiceLocator::GetServer()->GetUserSession()->UserId;

        return $userId === '' ? null : $userId;
    }
}
