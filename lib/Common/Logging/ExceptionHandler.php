<?php

use LibreBooking\Logging\LoggerFactory;
use LibreBooking\Logging\SafeLineFormatter;

/**
 * The one path for uncaught exceptions on web pages, the REST API and CLI jobs.
 *
 * Log() records each exception under a random reference id: one ERROR
 * record with the stack trace on the 'app' channel, and always one error_log line per trace
 * frame, so the server/container log (docker logs) gets it even at logging.level none or
 * when the logger itself fails. As the logger also writes to stderr, the container
 * output shows both copies under the same reference.
 */
abstract class ExceptionHandler
{
    /** Random bytes in the reference id that tags every log line of one exception. */
    private const REFERENCE_BYTES = 4;
    private const HTTP_SERVER_ERROR = 500;
    private const HTTP_SERVICE_UNAVAILABLE = 503;
    /** PHP's own exit code for an uncaught exception. */
    private const UNCAUGHT_EXIT_CODE = 255;

    private static ?ExceptionHandler $handler = null;

    /** Installs Handle() as PHP's exception handler; called once by the bootstrap. */
    public static function Register(): void
    {
        set_exception_handler([self::class, 'Handle']);
    }

    /** Sets the handler that renders the response after an uncaught exception was logged. */
    public static function SetExceptionHandler(?ExceptionHandler $handler)
    {
        self::$handler = $handler;
    }

    abstract public function HandleException(Throwable $exception, string $reference);

    public static function Handle(Throwable $exception)
    {
        $reference = self::Log($exception);

        if (self::$handler !== null) {
            try {
                self::$handler->HandleException($exception, $reference);
            } catch (Throwable $renderingFailure) {
                // the error page itself failed: log that too, still answer with the reference
                self::Log($renderingFailure);
                if (!headers_sent()) {
                    http_response_code(self::StatusFor($exception));
                }
                echo 'An error occurred. Reference: ' . $reference;
            }
        } elseif (PHP_SAPI === 'cli') {
            // a handled exception would otherwise end a CLI script (cron job) with exit code 0
            exit(self::UNCAUGHT_EXIT_CODE);
        }
    }

    /**
     * Logs $exception once per destination and returns its reference id. Never throws.
     */
    public static function Log(Throwable $exception): string
    {
        $reference = bin2hex(random_bytes(self::REFERENCE_BYTES));

        try {
            LoggerFactory::channel(LoggerFactory::APP)->error(
                message: '[exception {reference}] Uncaught exception: {class}: {message}',
                context: [
                    'reference' => $reference,
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ],
            );
        } catch (Throwable) {
            // the error_log copy below still records it
        }

        try {
            foreach (self::FormatException($exception) as $line) {
                error_log(message: "[exception {$reference}] {$line}");
            }
        } catch (Throwable) {
            // logging must never prevent the error response
        }

        return $reference;
    }

    /** HTTP status for an uncaught exception: 503 when the database is unavailable, else 500. */
    public static function StatusFor(Throwable $exception): int
    {
        return $exception instanceof DatabaseException ? self::HTTP_SERVICE_UNAVAILABLE : self::HTTP_SERVER_ERROR;
    }

    /**
     * Formats an exception and its previous-exception chain as log lines, one error_log()
     * call each, so no server has to escape embedded newlines and PHP-FPM's log_limit is
     * less likely to truncate the trace. Every line is escaped by SafeLineFormatter.
     *
     * @return list<string>
     */
    public static function FormatException(Throwable $exception): array
    {
        $lines = [];
        for ($e = $exception, $label = 'Uncaught exception'; $e !== null; $e = $e->getPrevious(), $label = 'Caused by') {
            $lines[] = SafeLineFormatter::escape(sprintf(
                '%s: %s: %s in %s:%d',
                $label,
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            foreach (explode("\n", $e->getTraceAsString()) as $line) {
                $lines[] = SafeLineFormatter::escape(' => ' . $line);
            }
        }

        return $lines;
    }
}

class WebExceptionHandler extends ExceptionHandler
{
    /**
     * @var callable(int $errorMessageId, string $reference): void
     */
    private $callback;

    /**
     * @param callable(int $errorMessageId, string $reference): void $callback renders the error page
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    public function HandleException(Throwable $exception, string $reference)
    {
        // Uncaught exceptions indicate a server-side failure.
        // Set the status only while headers are still mutable, and keep an error status already set.
        if (!headers_sent() && !connection_aborted()) {
            $currentStatus = http_response_code();
            if ($currentStatus === false || $currentStatus < 400) {
                http_response_code(response_code: self::StatusFor($exception));
            }
        }

        $errorMessageId = ErrorMessages::UNKNOWN_ERROR;
        if (is_a($exception, 'DatabaseConnectionException')) {
            $errorMessageId = ErrorMessages::DATABASE_CONNECTION;
        } elseif (is_a($exception, 'DatabaseNotFoundException')) {
            $errorMessageId = ErrorMessages::DATABASE_NOT_FOUND;
        }

        call_user_func($this->callback, $errorMessageId, $reference);
    }
}
