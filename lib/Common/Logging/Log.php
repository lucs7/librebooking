<?php

if (file_exists(filename: ROOT_DIR . 'vendor/autoload.php')) {
    // Web/Services/index.php requires this file directly
    require_once ROOT_DIR . 'vendor/autoload.php';
}

use LibreBooking\Logging\LoggerFactory;
use Monolog\Level;

/**
 * Static facade over the PSR-3 'app' and 'sql' loggers. Code that can take a
 * dependency should prefer an injected Psr\Log\LoggerInterface from LoggerFactory.
 *
 * Levels: Error (an operation failed), Warning (unexpected or security-relevant, but
 * handled), Info (business events) and Debug (tracing). Messages use sprintf-style
 * formatting. Logging never throws and never writes to the output.
 */
class Log
{
    /**
     * @param string $message sprintf-style format
     * @param mixed $args
     */
    public static function Debug($message, $args = [])
    {
        self::write(channel: LoggerFactory::APP, level: Level::Debug, message: $message, args: array_slice(func_get_args(), 1));
    }

    /**
     * @param string $message sprintf-style format
     * @param mixed $args
     */
    public static function Error($message, $args = [])
    {
        self::write(channel: LoggerFactory::APP, level: Level::Error, message: $message, args: array_slice(func_get_args(), 1));
    }

    /**
     * @param string $message sprintf-style format
     * @param mixed $args
     */
    public static function Warning($message, $args = [])
    {
        self::write(channel: LoggerFactory::APP, level: Level::Warning, message: $message, args: array_slice(func_get_args(), 1));
    }

    /**
     * @param string $message sprintf-style format
     * @param mixed $args
     */
    public static function Info($message, $args = [])
    {
        self::write(channel: LoggerFactory::APP, level: Level::Info, message: $message, args: array_slice(func_get_args(), 1));
    }

    /**
     * Logs a SQL statement when logging.sql is on, whatever logging.level is.
     *
     * @param string $message sprintf-style format
     * @param mixed $args
     */
    public static function Sql($message, $args = [])
    {
        self::write(channel: LoggerFactory::SQL, level: Level::Debug, message: $message, args: array_slice(func_get_args(), 1));
    }

    /** True only when debug entries are actually written. */
    public static function DebugEnabled()
    {
        return LoggerFactory::channel(LoggerFactory::APP)->isHandling(level: Level::Debug);
    }

    /** @internal for tests */
    public static function resetForTests(): void
    {
        LoggerFactory::reset();
    }

    /** @param array<mixed> $args */
    private static function write(string $channel, Level $level, $message, array $args): void
    {
        try {
            $logger = LoggerFactory::channel($channel);
            if ($logger->isHandling(level: $level)) {
                $logger->log(level: $level, message: self::format(message: (string) $message, args: $args));
            }
        } catch (\Throwable $e) {
            // last resort; never echo or throw from logging
            error_log('[LOGGING] Could not write log entry: ' . get_class($e));
        }
    }

    /** @param array<mixed> $args */
    private static function format(string $message, array $args): string
    {
        try {
            // always formatted, even without args: callers pre-escape '%' as '%%' (MySqlConnection)
            return vsprintf(format: $message, values: $args);
        } catch (\ValueError) {
            // a stray '%' (e.g. "50% off") must not break logging
            if ($args === []) {
                return $message;
            }
            $values = array_map(
                static fn (mixed $a): string => is_scalar($a) || $a instanceof \Stringable ? (string) $a : get_debug_type($a),
                $args,
            );
            return $message . ' ' . json_encode(value: $values, flags: JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }
}
