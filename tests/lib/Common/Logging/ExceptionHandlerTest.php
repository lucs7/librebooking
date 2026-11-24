<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/DatabaseExceptions.php');

use LibreBooking\Logging\LoggerFactory;

class ExceptionHandlerTest extends TestBase
{
    public function testIncludesPreviousExceptionChain(): void
    {
        $e = new RuntimeException('outer', 0, new LogicException('root cause'));

        $text = implode("\n", WebExceptionHandler::FormatException($e));

        $this->assertStringContainsString('Uncaught exception: RuntimeException: outer', $text);
        $this->assertStringContainsString('Caused by: LogicException: root cause', $text);
    }

    public function testEscapesControlCharactersAndNewlines(): void
    {
        $lines = WebExceptionHandler::FormatException(new Exception("bad\nUncaught exception: forged\033[31m"));

        $this->assertStringStartsWith('Uncaught exception: Exception: bad\x0AUncaught exception: forged\x1B[31m', $lines[0]);
        foreach ($lines as $line) {
            $this->assertStringNotContainsString("\n", $line);
            $this->assertStringNotContainsString("\033", $line);
        }
    }

    public function testEscapesC1ControlsAndUnicodeLineSeparators(): void
    {
        $e = new Exception("a\u{0085}b\u{2028}c\u{009B}d");

        $text = implode("\n", WebExceptionHandler::FormatException($e));

        $this->assertStringNotContainsString("\u{0085}", $text);
        $this->assertStringNotContainsString("\u{2028}", $text);
        $this->assertStringNotContainsString("\u{009B}", $text);
        $this->assertStringContainsString('\xC2\x85', $text);
    }

    // bytes that are not valid UTF-8, such as a lone 0x9B (CSI to
    // 8-bit terminals) or Latin-1 0xE9, become U+FFFD instead of \xNN escapes
    public function testReplacesInvalidUtf8WithReplacementCharacter(): void
    {
        $lines = WebExceptionHandler::FormatException(new Exception("a\x9B31mb caf\xE9"));

        $this->assertStringStartsWith("Uncaught exception: Exception: a\u{FFFD}31mb caf\u{FFFD}", $lines[0]);
        $this->assertStringNotContainsString("\x9B", $lines[0]);
    }

    public function testKeepsValidNonAsciiText(): void
    {
        // The UTF-8 encodings of € (E2 82 AC) and 日 (E6 97 A5) contain bytes in 0x80-0x9F.
        $lines = WebExceptionHandler::FormatException(new Exception('Préis 5 € 日本語 Ошибка'));

        $this->assertStringStartsWith('Uncaught exception: Exception: Préis 5 € 日本語 Ошибка', $lines[0]);
    }

    public function testKeepsTabsAndEscapesNulInClassNames(): void
    {
        $text = implode("\n", WebExceptionHandler::FormatException(new Exception("a\tb")));
        $this->assertStringContainsString("a\tb", $text);

        $anonymous = new class ('x') extends Exception {
        };
        $this->assertStringNotContainsString("\0", implode("\n", WebExceptionHandler::FormatException($anonymous)));
    }

    public function testFormatsNonExceptionThrowables(): void
    {
        $lines = WebExceptionHandler::FormatException(new TypeError('boom'));

        $this->assertStringStartsWith('Uncaught exception: TypeError: boom', $lines[0]);
    }

    // every error_log line of one exception carries the same reference id,
    // which is also in the single app.log record and is passed to the error page
    public function testHandleLogsOnceWithOneSharedReference(): void
    {
        $this->configureLogging(level: 'error');
        $exception = new RuntimeException('outer', 0, new LogicException('root cause'));
        $passed = null;
        ExceptionHandler::SetExceptionHandler(new WebExceptionHandler(
            static function (int $errorMessageId, string $reference) use (&$passed): void {
                $passed = [$errorMessageId, $reference];
            }
        ));

        $logged = $this->captureErrorLog(static fn () => ExceptionHandler::Handle($exception));

        $this->assertCount(count(WebExceptionHandler::FormatException($exception)), $logged);
        $ids = [];
        foreach ($logged as $line) {
            $this->assertMatchesRegularExpression('/\[exception ([0-9a-f]{8})\] /', $line);
            preg_match('/\[exception ([0-9a-f]{8})\] /', $line, $match);
            $ids[$match[1]] = true;
        }
        $this->assertCount(1, $ids, 'all lines of one exception share one id');
        $this->assertStringContainsString('Uncaught exception: RuntimeException: outer', $logged[0]);
        $this->assertSame([ErrorMessages::UNKNOWN_ERROR, array_key_first($ids)], $passed);

        $appLog = $this->appLogLines();
        $this->assertCount(1, $appLog, 'one app.log record per exception');
        $this->assertStringContainsString('app.ERROR: [exception ' . array_key_first($ids) . '] Uncaught exception: RuntimeException: outer', $appLog[0]);
        $this->assertStringContainsString('root cause', $appLog[0], 'the cause chain is in the record');
    }

    // a message cannot forge lines or inject escape codes, in either destination
    public function testLogCannotForgeLogLines(): void
    {
        $this->configureLogging(level: 'error');

        $logged = $this->captureErrorLog(static fn () => ExceptionHandler::Log(new Exception("bad\nUncaught exception: forged\033[31m")));

        $this->assertCount(1, preg_grep('/Uncaught exception:/', $logged));
        $this->assertStringNotContainsString("\033", implode("\n", $logged));
        $appLog = $this->appLogLines();
        $this->assertCount(1, $appLog);
        $this->assertStringNotContainsString("\033", $appLog[0]);
        $this->assertStringContainsString('bad\x0AUncaught exception: forged\x1B[31m', $appLog[0]);
    }

    // at logging.level none the exception still reaches error_log (docker logs)
    public function testLogWritesErrorLogEvenWhenLoggingIsOff(): void
    {
        $this->configureLogging(level: 'none');

        $logged = $this->captureErrorLog(static fn () => ExceptionHandler::Log(new RuntimeException('still visible')));

        $this->assertStringContainsString('Uncaught exception: RuntimeException: still visible', $logged[0]);
        $this->assertSame([], $this->appLogLines());
    }

    // when the logger itself fails, the error_log copy is still written
    public function testErrorLogCopySurvivesALoggerFailure(): void
    {
        LoggerFactory::reset();
        Configuration::SetInstance(new class () extends FakeConfig {
            public function GetKey($configDef, $converter = null)
            {
                throw new RuntimeException('configuration unavailable');
            }
        });

        $logged = $this->captureErrorLog(static fn () => ExceptionHandler::Log(new RuntimeException('still visible')));

        $this->assertNotEmpty(preg_grep('/\[exception [0-9a-f]{8}\] Uncaught exception: RuntimeException: still visible/', $logged));
    }

    // when rendering the error page fails, both exceptions are logged and the user still gets the reference
    public function testRenderingFailureStillAnswersWithReference(): void
    {
        $this->configureLogging(level: 'error');
        ExceptionHandler::SetExceptionHandler(new WebExceptionHandler(static function (): void {
            throw new LogicException('template missing');
        }));

        ob_start();
        $logged = $this->captureErrorLog(static fn () => ExceptionHandler::Handle(new RuntimeException('original')));
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/^An error occurred\. Reference: [0-9a-f]{8}$/', $output);
        $this->assertStringContainsString(substr($output, -8), implode("\n", preg_grep('/RuntimeException: original/', $logged)));
        $this->assertNotEmpty(preg_grep('/LogicException: template missing/', $logged));
    }

    // 503 when the database is unavailable, else 500
    public function testStatusIs503ForDatabaseErrorsAnd500Otherwise(): void
    {
        $this->assertSame(503, ExceptionHandler::StatusFor(new DatabaseConnectionException('down')));
        $this->assertSame(503, ExceptionHandler::StatusFor(new DatabaseNotFoundException('missing')));
        $this->assertSame(500, ExceptionHandler::StatusFor(new RuntimeException('boom')));
    }

    // database errors map to their own user-facing message
    public function testDatabaseErrorsMapToTheirMessage(): void
    {
        $passed = null;
        $handler = new WebExceptionHandler(static function (int $errorMessageId) use (&$passed): void {
            $passed = $errorMessageId;
        });

        $handler->HandleException(new DatabaseConnectionException('down'), 'abcd1234');

        $this->assertSame(ErrorMessages::DATABASE_CONNECTION, $passed);
    }

    private string $logDir = '';

    private function configureLogging(string $level): void
    {
        $this->logDir = sys_get_temp_dir() . '/lb-exception-test-' . bin2hex(random_bytes(6));
        mkdir($this->logDir);
        $this->fakeConfig->SetKey(ConfigKeys::LOGGING_FOLDER, $this->logDir);
        $this->fakeConfig->SetKey(ConfigKeys::LOGGING_LEVEL, $level);
        $this->fakeConfig->SetKey(ConfigKeys::LOGGING_SQL, false);
        LoggerFactory::setStderrTarget($this->logDir . '/stderr.log');
    }

    /** @return list<string> */
    private function appLogLines(): array
    {
        $file = $this->logDir . '/app.log';

        return is_file($file) ? array_values(array_filter(explode("\n", (string) file_get_contents($file)))) : [];
    }

    public function teardown(): void
    {
        ExceptionHandler::SetExceptionHandler(null);
        LoggerFactory::setStderrTarget('php://stderr');
        if ($this->logDir !== '') {
            array_map('unlink', glob($this->logDir . '/*') ?: []);
            rmdir($this->logDir);
        }
        parent::teardown();
    }
}
