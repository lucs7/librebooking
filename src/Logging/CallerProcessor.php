<?php

declare(strict_types=1);

namespace LibreBooking\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds the file and line of the code that called the logger.
 *
 * Monolog's IntrospectionProcessor skips classes by substring, so skipping the
 * global `Log` facade would also skip LoginPresenter, LogTest and friends. This
 * skips Monolog/PSR-3 internals and an exact list of our own logging classes, so
 * other LibreBooking\Logging classes (e.g. a future error handler) still count as callers.
 */
final class CallerProcessor implements ProcessorInterface
{
    private const LIBRARY_NAMESPACES = ['Monolog\\', 'Psr\\Log\\'];
    private const LOGGING_CLASSES = ['Log', LoggerFactory::class, self::class];

    public function __invoke(LogRecord $record): LogRecord
    {
        $caller = null;
        foreach (debug_backtrace(options: DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['class']) && self::isLoggingClass($frame['class'])) {
                $caller = $frame;
            } elseif ($caller !== null) {
                break;
            }
        }
        if (isset($caller['file'])) {
            $record->extra['file'] = $caller['file'];
            $record->extra['line'] = $caller['line'] ?? null;
        }

        return $record;
    }

    private static function isLoggingClass(string $class): bool
    {
        if (in_array(needle: $class, haystack: self::LOGGING_CLASSES, strict: true)) {
            return true;
        }
        foreach (self::LIBRARY_NAMESPACES as $namespace) {
            if (str_starts_with(haystack: $class, needle: $namespace)) {
                return true;
            }
        }
        return false;
    }
}
