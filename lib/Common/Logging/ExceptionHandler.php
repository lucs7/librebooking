<?php

abstract class ExceptionHandler
{
    /**
     * @var ExceptionHandler $handler
     */
    private static $handler;

    public static function SetExceptionHandler(ExceptionHandler $handler)
    {
        self::$handler = $handler;
    }

    abstract public function HandleException($exception);

    public static function Handle($exception)
    {
        Log::Error('Uncaught exception: %s', $exception);

        if (isset(self::$handler)) {
            self::$handler->HandleException($exception);
        }
    }
}

class WebExceptionHandler extends ExceptionHandler
{
    /**
     * @var callable
     */
    private $callback;

    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    public function HandleException($exception)
    {
        // Written directly to stderr (not error_log()) so the trace keeps real
        // newlines and ANSI colors instead of Apache stamping its
        // [timestamp][pid][client] prefix on every line and escaping control
        // characters (including newlines and the color escape byte).
        $stderr = fopen('php://stderr', 'ab');
        if ($stderr !== false) {
            $header = sprintf(
                "\033[1;31mUncaught exception: %s: %s in %s:%d\033[0m",
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );

            $trace = array_map(
                static fn($line) => "\033[2m => {$line}\033[0m",
                explode("\n", $exception->getTraceAsString())
            );

            fwrite($stderr, $header . "\n" . implode("\n", $trace) . "\n");
            fclose($stderr);
        }

        // Uncaught exceptions indicate a server-side failure.
        // Set 500 only while headers are still mutable.
        if (!headers_sent() && !connection_aborted()) {
            $currentStatus = http_response_code();
            if ($currentStatus === false || $currentStatus < 400) {
                http_response_code(500);
            }
        }

        $errorMessageId = ErrorMessages::UNKNOWN_ERROR;
        if (is_a($exception, 'DatabaseConnectionException')) {
            $errorMessageId = ErrorMessages::DATABASE_CONNECTION;
        } elseif (is_a($exception, 'DatabaseNotFoundException')) {
            $errorMessageId = ErrorMessages::DATABASE_NOT_FOUND;
        }

        call_user_func($this->callback, $errorMessageId);
    }
}

set_exception_handler(['ExceptionHandler', 'Handle']);
