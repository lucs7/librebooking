<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** Server whose user session is a fixed fake user (never a real person). */
class LogTestServer extends Server
{
    public function __construct(private UserSession $session)
    {
    }

    public function GetUserSession()
    {
        return $this->session;
    }
}

final class LogTestSupport
{
    public const USER_ID = 42;
    public const USER_EMAIL = 'user@example.com';
    public const USER_STRING = 'Test User (user@example.com)';

    /** Resets the cached loggers so the next call rebuilds them from configuration. */
    public static function reset(): void
    {
        Log::resetForTests();
    }

    public static function fakeUser(): UserSession
    {
        $user = new UserSession(self::USER_ID);
        $user->FirstName = 'Test';
        $user->LastName = 'User';
        $user->Email = self::USER_EMAIL;
        return $user;
    }

    /**
     * The PSR-3 'app' logger behind the Log facade.
     */
    public static function psrLogger(): LoggerInterface
    {
        return \LibreBooking\Logging\LoggerFactory::channel('app');
    }

    /** @return list<string> non-empty lines of $file, or [] if it does not exist */
    public static function lines(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        return array_values(array_filter(
            explode("\n", (string) file_get_contents($file)),
            static fn (string $l): bool => $l !== ''
        ));
    }

    /**
     * Recovers the caller file/line from a log line, in either form:
     * legacy text ' [File=<file>,Line=<n>]' or structured extra {"file":"...","line":n}.
     * @return array{file: string, line: int}|null
     */
    public static function callerOf(string $line): ?array
    {
        if (preg_match('/\[File=(.+?),Line=(\d+)\]/', $line, $m)) {
            return ['file' => $m[1], 'line' => (int) $m[2]];
        }
        if (preg_match('/"file":("(?:[^"\\\\]|\\\\.)*")/', $line, $f)
            && preg_match('/"line":(\d+)/', $line, $l)) {
            return ['file' => (string) json_decode($f[1]), 'line' => (int) $l[1]];
        }
        return null;
    }

    /** True when the line carries some user attribution: '[User=<non-empty>]' or "user_id":<value>. */
    public static function hasUserAttribution(string $line): bool
    {
        return preg_match('/\[User=[^\]]+\]/', $line) === 1
            || preg_match('/"user_id":("[^"]+"|\d+)/', $line) === 1;
    }

    /** The user id from structured extra, or null. */
    public static function userIdOf(string $line): ?string
    {
        return preg_match('/"user_id":"?(\w+)"?/', $line, $m) ? $m[1] : null;
    }
}

/** Per-test temp log folder, fake config, fake user, fresh singleton. */
abstract class LogTestCase extends TestCase
{
    protected string $dir;
    protected FakeConfig $config;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/lb-log-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->config = new FakeConfig();
        $this->config->SetKey(ConfigKeys::LOGGING_FOLDER, $this->dir);
        Configuration::SetInstance($this->config);
        ServiceLocator::SetServer(new LogTestServer(LogTestSupport::fakeUser()));
        \LibreBooking\Logging\LoggerFactory::setStderrTarget($this->dir . '/stderr.log');
        LogTestSupport::reset();
    }

    protected function tearDown(): void
    {
        \LibreBooking\Logging\LoggerFactory::setStderrTarget('php://stderr');
        LogTestSupport::reset();
        Configuration::SetInstance(null);
        $this->rmTree($this->dir);
    }

    protected function configure(string $level, bool $sql = false): void
    {
        $this->config->SetKey(ConfigKeys::LOGGING_LEVEL, $level);
        $this->config->SetKey(ConfigKeys::LOGGING_SQL, $sql);
    }

    /** @return list<string> */
    protected function appLines(): array
    {
        return LogTestSupport::lines($this->dir . '/app.log');
    }

    /** @return list<string> */
    protected function sqlLines(): array
    {
        return LogTestSupport::lines($this->dir . '/sql.log');
    }

    /** Runs $fn with PHP's error_log pointed at a temp file; returns the lines written there. */
    protected function captureErrorLog(callable $fn): array
    {
        $file = $this->dir . '/php_error.log';
        $old = ini_set('error_log', $file);
        try {
            $fn();
        } finally {
            ini_set('error_log', (string) $old);
        }
        return LogTestSupport::lines($file);
    }

    private function rmTree(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
                $this->rmTree("$path/$entry");
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}

/**
 * Tests for the Log facade and the PSR-3 loggers behind it.
 */
class LogTest extends LogTestCase
{
    // level 'none' writes nothing to app.log
    public function testLevelNoneWritesNothingToAppLog(): void
    {
        $this->configure('none');
        Log::Debug('debug at none');
        Log::Error('error at none');

        $this->assertSame([], $this->appLines());
    }

    // level 'error' keeps Error and drops Debug
    public function testLevelErrorWritesErrorAndDropsDebug(): void
    {
        $this->configure('error');
        Log::Error('Reservation %s failed', 42);
        Log::Debug('Loaded %s', 'x');

        $lines = $this->appLines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('app.ERROR', $lines[0]);
        $this->assertStringContainsString('Reservation 42 failed', $lines[0]);
    }

    // every entry goes to the file and to stderr
    public function testEntriesAlsoGoToStderr(): void
    {
        $this->configure('error');
        Log::Error('to both %s', 'targets');

        $this->assertStringContainsString('to both targets', $this->appLines()[0]);
        $stderr = LogTestSupport::lines($this->dir . '/stderr.log');
        $this->assertCount(1, $stderr);
        $this->assertStringContainsString('app.ERROR: to both targets', $stderr[0]);
    }

    // the four facade levels map to their PSR-3 levels; level 'warning' keeps Error and Warning
    public function testFacadeLevelsAtWarningThreshold(): void
    {
        $this->configure('warning');
        Log::Error('error %s', 1);
        Log::Warning('warning %s', 2);
        Log::Info('info %s', 3);
        Log::Debug('debug %s', 4);

        $lines = $this->appLines();
        $this->assertCount(2, $lines, implode("\n", $lines));
        $this->assertStringContainsString('app.ERROR: error 1', $lines[0]);
        $this->assertStringContainsString('app.WARNING: warning 2', $lines[1]);
    }

    // level 'info' adds Info, still drops Debug
    public function testInfoLevelWritesInfoAndDropsDebug(): void
    {
        $this->configure('info');
        Log::Info('info %s', 3);
        Log::Debug('debug %s', 4);

        $lines = $this->appLines();
        $this->assertCount(1, $lines, implode("\n", $lines));
        $this->assertStringContainsString('app.INFO: info 3', $lines[0]);
    }

    // level 'debug' writes Debug and Error, in call order
    public function testLevelDebugWritesDebugAndError(): void
    {
        $this->configure('debug');
        Log::Debug('Loaded %s', 'x');
        Log::Error('Reservation %s failed', 42);

        $lines = $this->appLines();
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('app.DEBUG', $lines[0]);
        $this->assertStringContainsString('Loaded x', $lines[0]);
        $this->assertStringContainsString('app.ERROR', $lines[1]);
        $this->assertStringContainsString('Reservation 42 failed', $lines[1]);
    }

    // the level is matched case-insensitively inside Log ('DeBuG' = 'debug').
    // FakeConfig bypasses Configuration::ValidateConfig, so this pins Log's own handling.
    public function testMixedCaseDebugLevelIsTreatedAsDebug(): void
    {
        $this->configure('DeBuG');
        Log::Debug('mixed case debug');

        $lines = $this->appLines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('mixed case debug', $lines[0]);
    }

    // 'NONE' is treated as 'none'
    public function testUpperCaseNoneTurnsLoggingOff(): void
    {
        $this->configure('NONE');
        Log::Error('error at NONE');

        $this->assertSame([], $this->appLines());
        $this->assertFalse(Log::DebugEnabled());
    }

    // an unset level defaults to 'error' (through the real ConfigurationFile,
    // which supplies ConfigKeys::LOGGING_LEVEL['default'])
    public function testUnsetLevelDefaultsToError(): void
    {
        $this->assertNull(env('LB_LOGGING_LEVEL'), 'precondition: LB_LOGGING_LEVEL must not be set in the environment');
        $this->config->SetFile(Configuration::DEFAULT_CONFIG_ID, new ConfigurationFile([
            Configuration::SETTINGS => ['logging' => ['folder' => $this->dir]],
        ]));

        Log::Error('error with default level');
        Log::Debug('debug with default level');

        $lines = $this->appLines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('error with default level', $lines[0]);
    }

    // entries go to <folder>/app.log; a trailing slash on the folder is trimmed
    public function testAppLogFileNameWithTrailingSlashFolder(): void
    {
        $this->configure('error');
        $this->config->SetKey(ConfigKeys::LOGGING_FOLDER, $this->dir . '/');
        Log::Error('into app.log');

        $this->assertSame(['app.log'], array_values(array_diff(scandir($this->dir), ['.', '..', 'stderr.log'])));
        $this->assertStringContainsString('into app.log', $this->appLines()[0]);
    }

    // several positional vsprintf args
    public function testPositionalArgsAreFormattedWithVsprintf(): void
    {
        $this->configure('debug');
        Log::Debug('User %s booked %d resources for %.2f credits', 'abc', 3, 1.5);

        $this->assertStringContainsString('User abc booked 3 resources for 1.50 credits', $this->appLines()[0]);
    }

    // named 'message:' argument with no args (ParamsValidator.php:23)
    public function testNamedMessageArgumentWithoutArgs(): void
    {
        $this->configure('debug');
        Log::Debug(message: 'No parameters found in URI.');

        $this->assertStringContainsString('No parameters found in URI.', $this->appLines()[0]);
    }

    // named 'message:'/'args:' with an exception (TwigRenderer.php:118)
    public function testNamedArgsWithExceptionAreStringified(): void
    {
        $this->configure('error');
        Log::Error(message: 'Error during page validation: %s', args: new RuntimeException('template boom'));

        $line = $this->appLines()[0];
        $this->assertStringContainsString('Error during page validation: ', $line);
        $this->assertStringContainsString('template boom', $line);
    }

    // an exception passed positionally for %s is stringified
    public function testExceptionAsPositionalPercentSArgIsStringified(): void
    {
        $this->configure('error');
        Log::Error('Failed: %s', new RuntimeException('disk full'));

        $line = $this->appLines()[0];
        $this->assertStringContainsString('Failed: ', $line);
        $this->assertStringContainsString('RuntimeException', $line);
        $this->assertStringContainsString('disk full', $line);
    }

    // an Error entry is attributed to a user and to the calling file/line
    public function testErrorEntryHasUserAndCallerLocation(): void
    {
        $this->configure('error');
        $line = __LINE__ + 1;
        Log::Error('Failed %s', 'save');

        $entry = $this->appLines()[0];
        $this->assertTrue(LogTestSupport::hasUserAttribution($entry), $entry);
        $this->assertSame(['file' => __FILE__, 'line' => $line], LogTestSupport::callerOf($entry), $entry);
    }

    // a Debug entry is attributed to a user and to the calling file/line
    public function testDebugEntryHasUserAndCallerLocation(): void
    {
        $this->configure('debug');
        $line = __LINE__ + 1;
        Log::Debug('Loaded %s', 'x');

        $entry = $this->appLines()[0];
        $this->assertTrue(LogTestSupport::hasUserAttribution($entry), $entry);
        $this->assertSame(['file' => __FILE__, 'line' => $line], LogTestSupport::callerOf($entry), $entry);
    }

    // with logging.sql on, Log::Sql writes the formatted statement to sql.log only
    public function testSqlOnWritesStatementToSqlLog(): void
    {
        $this->configure('error', sql: true);
        Log::Sql('SELECT * FROM users WHERE id=%s', 5);

        $lines = $this->sqlLines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('SELECT * FROM users WHERE id=5', $lines[0]);
        $this->assertTrue(LogTestSupport::hasUserAttribution($lines[0]), $lines[0]);
        $this->assertSame([], $this->appLines());
    }

    // callers pre-escape '%' as '%%' (MySqlConnection.php:72); without args it is collapsed to '%'
    // (regression found by the side-by-side run, scenario S04)
    public function testPreEscapedPercentWithoutArgsIsCollapsed(): void
    {
        $this->configure('error', sql: true);
        Log::Sql('MySql Query: ' . str_replace('%', '%%', "SELECT 1 WHERE name LIKE '%room%'"));

        $this->assertStringContainsString("LIKE '%room%'", $this->sqlLines()[0]);
    }

    // with logging.sql off, Log::Sql writes nothing
    public function testSqlOffWritesNothing(): void
    {
        $this->configure('debug', sql: false);
        Log::Sql('SELECT * FROM users WHERE id=%s', 5);

        $this->assertSame([], $this->sqlLines());
        $this->assertSame([], $this->appLines());
    }

    // DebugEnabled() is true at 'debug'
    public function testDebugEnabledTrueAtDebug(): void
    {
        $this->configure('debug');
        $this->assertTrue(Log::DebugEnabled());
    }

    // DebugEnabled() is false at 'none'
    public function testDebugEnabledFalseAtNone(): void
    {
        $this->configure('none');
        $this->assertFalse(Log::DebugEnabled());
    }

    // a write failure on the Error path neither throws nor reaches output
    public function testErrorWithUnwritableFolderIsSilent(): void
    {
        $this->configure('error');
        touch($this->dir . '/blocker');
        $this->config->SetKey(ConfigKeys::LOGGING_FOLDER, $this->dir . '/blocker/logs');

        ob_start();
        $errorLog = $this->captureErrorLog(fn () => Log::Error('cannot be written'));
        $this->assertSame('', ob_get_clean());
        $this->assertCount(1, $errorLog, 'the entry falls back to error_log');
    }

    // through the real ConfigurationFile, 'DEBUG' is accepted case-insensitively as 'debug'
    public function testRealConfigUpperCaseDebugIsDebug(): void
    {
        $errorLog = $this->captureErrorLog(function (): void {
            $this->config->SetFile(Configuration::DEFAULT_CONFIG_ID, new ConfigurationFile([
                Configuration::SETTINGS => ['logging' => ['folder' => $this->dir, 'level' => 'DEBUG']],
            ]));
            Log::Debug('debug with DEBUG');
            Log::Error('error with DEBUG');
        });

        $this->assertCount(0, preg_grep('/logging\.level/', $errorLog));
        $this->assertCount(2, $this->appLines());
    }

    // logging.sql works on its own, even at level 'none'
    public function testSqlWrittenEvenAtLevelNone(): void
    {
        $this->configure('none', sql: true);
        Log::Sql('SELECT * FROM users WHERE id=%s', 5);

        $this->assertCount(1, $this->sqlLines());
        $this->assertStringContainsString('SELECT * FROM users WHERE id=5', $this->sqlLines()[0]);
    }

    // SQL entries are written at DEBUG severity on the 'sql' channel
    public function testSqlSeverityIsDebug(): void
    {
        $this->configure('error', sql: true);
        Log::Sql('SELECT 1');

        $this->assertStringContainsString('sql.DEBUG: ', $this->sqlLines()[0]);
    }

    // DebugEnabled() is false at 'error' (debug lines are not written)
    public function testDebugEnabledFalseAtError(): void
    {
        $this->configure('error');
        $this->assertFalse(Log::DebugEnabled());
    }

    // '%' with no args logs the raw message and does not throw (Debug)
    public function testPercentWithoutArgsLogsRawMessageFromDebug(): void
    {
        $this->configure('debug');
        Log::Debug('50% off');

        $this->assertStringContainsString('50% off', $this->appLines()[0]);
    }

    // '%' with no args logs the raw message and does not throw (Error)
    public function testPercentWithoutArgsLogsRawMessageFromError(): void
    {
        $this->configure('error');
        Log::Error('50% off');

        $this->assertStringContainsString('50% off', $this->appLines()[0]);
    }

    // an unwritable folder gives no output, no exception, one error_log line
    public function testUnwritableFolderFallsBackToErrorLog(): void
    {
        $this->configure('debug');
        touch($this->dir . '/blocker');
        $this->config->SetKey(ConfigKeys::LOGGING_FOLDER, $this->dir . '/blocker/logs');

        ob_start();
        $errorLog = $this->captureErrorLog(fn () => Log::Debug('cannot be written'));
        $this->assertSame('', ob_get_clean());
        $this->assertCount(1, $errorLog, implode("\n", $errorLog));
    }

    /** @return array<string, array{string, string, string}> level, method that must be written, method that must be dropped */
    public static function psrThresholds(): array
    {
        return [
            'info' => ['info', 'info', 'debug'],
            'WARNING (case-insensitive)' => ['WARNING', 'warning', 'info'],
        ];
    }

    // warning and info are real thresholds
    #[DataProvider('psrThresholds')]
    public function testPsrLevelIsAThreshold(string $level, string $written, string $dropped): void
    {
        $this->configure($level);
        $logger = LogTestSupport::psrLogger();
        $logger->{$written}("kept at $written");
        $logger->{$dropped}("dropped at $dropped");

        $lines = $this->appLines();
        $this->assertCount(1, $lines, implode("\n", $lines));
        $this->assertStringContainsString("kept at $written", $lines[0]);
    }

    // an unknown level falls back to 'error' with exactly one warning naming it
    public function testUnknownLevelFallsBackToErrorWithOneWarning(): void
    {
        $this->configure('verbose');
        $errorLog = $this->captureErrorLog(function (): void {
            Log::Error('error at verbose');
            Log::Debug('debug at verbose');
        });

        $app = $this->appLines();
        $this->assertCount(1, preg_grep('/error at verbose/', $app));
        $this->assertCount(0, preg_grep('/debug at verbose/', $app));
        $warnings = preg_grep("/'?verbose'?/", array_merge(
            preg_grep('/error at verbose/', $app, PREG_GREP_INVERT),
            $errorLog
        ));
        $this->assertCount(1, $warnings, implode("\n", array_merge($app, $errorLog)));
    }

    // no email address in the line; the user id is present
    public function testLineCarriesUserIdAndNoEmail(): void
    {
        $this->configure('error');
        Log::Error('Failed %s', 'save');

        $line = $this->appLines()[0];
        $this->assertDoesNotMatchRegularExpression('/[\w.+-]+@[\w-]+\.[\w.-]+/', $line);
        $this->assertSame((string) LogTestSupport::USER_ID, LogTestSupport::userIdOf($line), $line);
    }

    // a file that reached rotateSize is renamed to <file>.<timestamp>; earlier rotated files are kept
    public function testRotationKeepsEveryRotatedFile(): void
    {
        file_put_contents($this->dir . '/app.log.20260101-000000', 'older rotated file');
        $logger = \LibreBooking\Logging\LoggerFactory::create(
            name: 'app',
            settings: \LibreBooking\Logging\LoggingSettings::fromValues(
                level: 'error',
                folder: $this->dir,
                sqlEnabled: false,
                rotateSize: 100,
            ),
            userId: static fn (): int => LogTestSupport::USER_ID,
        );

        foreach (['first', 'second'] as $round) {
            file_put_contents($this->dir . '/app.log', str_repeat($round[0], 100));
            $logger->error("entry after $round rotation");
        }

        $rotated = glob($this->dir . '/app.log.*');
        sort($rotated);
        $this->assertCount(3, $rotated, implode("\n", $rotated));
        $this->assertSame('older rotated file', file_get_contents($rotated[0]));
        $this->assertMatchesRegularExpression('/app\.log\.\d{8}-\d{6}(-\d+)?$/', $rotated[2]);
        $contents = array_map('file_get_contents', array_slice($rotated, 1));
        sort($contents);
        $this->assertSame([str_repeat('f', 100), str_repeat('s', 100)], $contents, 'a second rotation in the same second must not overwrite the first');
        $this->assertCount(1, $this->appLines());
        $this->assertStringContainsString('entry after second rotation', $this->appLines()[0]);
    }

    // below rotateSize nothing is rotated; rotateSize 0 never rotates
    public function testNoRotationBelowLimitOrWhenDisabled(): void
    {
        foreach ([1000, 0] as $rotateSize) {
            file_put_contents($this->dir . '/app.log', str_repeat('x', 100) . "\n");
            $logger = \LibreBooking\Logging\LoggerFactory::create(
                name: 'app',
                settings: \LibreBooking\Logging\LoggingSettings::fromValues(
                    level: 'error',
                    folder: $this->dir,
                    sqlEnabled: false,
                    rotateSize: $rotateSize,
                ),
                userId: static fn (): int => LogTestSupport::USER_ID,
            );
            $logger->error('kept in the same file');

            $this->assertSame([], glob($this->dir . '/app.log.*'), "rotateSize $rotateSize");
            $this->assertCount(2, $this->appLines(), "rotateSize $rotateSize");
        }
    }

    // PSR-3 '{placeholder}' messages are interpolated from the context
    public function testPsr3PlaceholdersAreInterpolated(): void
    {
        $this->configure('info');
        LogTestSupport::psrLogger()->info('Reservation {ref} created by user {id}', ['ref' => 'abc123', 'id' => 7]);

        $this->assertStringContainsString('Reservation abc123 created by user 7', $this->appLines()[0]);
    }
}
