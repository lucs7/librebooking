<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Runs real entry points in separate PHP processes with an unreachable database and
 * checks the single uncaught-exception path end to end:
 * one reference id in the response, in every error_log line and in the app.log record,
 * and no stack trace in the response.
 *
 * Scripts are run as files, not with `php -r`: PHP never calls a user exception
 * handler for code run with -r.
 */
class ErrorHandlingEntryPointTest extends TestCase
{
    private const UNREACHABLE_DATABASE = '127.0.0.1:1';
    private const UNCAUGHT_EXIT_CODE = 255;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/lb-entry-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testWebPageShowsErrorPageWithReference(): void
    {
        [, $output] = $this->runScript(script: 'index.php', cwd: __DIR__ . '/../../Web');

        $this->assertMatchesRegularExpression('/<code>([0-9a-f]{8})<\/code>/', $output, $output);
        preg_match('/<code>([0-9a-f]{8})<\/code>/', $output, $match);
        $this->assertStringNotContainsString('Stack trace', $output);
        $this->assertLoggedEverywhere(reference: $match[1], class: 'DatabaseConnectionException');
    }

    public function testRestApiReturnsJsonWithReferenceAndNoTrace(): void
    {
        $prepend = $this->dir . '/prepend.php';
        file_put_contents($prepend, '<?php $_SERVER = array_merge($_SERVER, ' . var_export([
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/Web/Services/index.php',
            'REQUEST_URI' => '/Web/Services/index.php/Schedules/',
            'HTTP_X_BOOKED_SESSIONTOKEN' => 'fake-session-token',
            'HTTP_X_BOOKED_USERID' => '1',
        ], true) . ');');

        [, $output] = $this->runScript(
            script: 'index.php',
            cwd: __DIR__ . '/../../Web/Services',
            ini: ['auto_prepend_file' => $prepend],
            env: ['LB_API_ENABLED' => 'true'],
        );

        $this->assertStringNotContainsString('Slim Application Error', $output);
        $this->assertStringNotContainsString('Stack trace', $output);
        $body = json_decode($output, true);
        $this->assertIsArray($body, $output);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $body['reference'] ?? '', $output);
        $this->assertLoggedEverywhere(reference: $body['reference'], class: 'Exception');
    }

    public function testCliScriptLogsUncaughtExceptionAndFails(): void
    {
        // a script that bootstraps like Jobs/*.php and lets an exception escape
        $script = $this->dir . '/uncaught-exception-job.php';
        file_put_contents($script, implode("\n", [
            '<?php',
            'define(\'ROOT_DIR\', ' . var_export(dirname(__DIR__, 2) . '/', true) . ');',
            'require_once ROOT_DIR . \'lib/Common/namespace.php\';',
            'throw new RuntimeException(\'job failed\');',
        ]));

        [$exitCode] = $this->runScript(script: $script, cwd: $this->dir);

        $this->assertSame(self::UNCAUGHT_EXIT_CODE, $exitCode);
        $lines = $this->lines('php_error.log');
        $this->assertNotEmpty($lines);
        preg_match('/\[exception ([0-9a-f]{8})\] Uncaught exception: RuntimeException: job failed/', implode("\n", $lines), $match);
        $this->assertNotEmpty($match, implode("\n", $lines));
        $this->assertLoggedEverywhere(reference: $match[1], class: 'RuntimeException');
    }

    /** The reference is in every error_log line of the exception and in exactly one app.log record. */
    private function assertLoggedEverywhere(string $reference, string $class): void
    {
        $errorLog = preg_grep('/\[exception [0-9a-f]{8}\]/', $this->lines('php_error.log'));
        $this->assertNotEmpty($errorLog, 'error_log has the exception');
        foreach ($errorLog as $line) {
            $this->assertStringContainsString("[exception {$reference}]", $line);
        }
        $appLog = preg_grep("/\\[exception {$reference}\\]/", $this->lines('app.log'));
        $this->assertCount(1, $appLog, implode("\n", $this->lines('app.log')));
        $this->assertStringContainsString("app.ERROR: [exception {$reference}] Uncaught exception: ", reset($appLog));
        $this->assertStringContainsString($class, reset($appLog));
    }

    /** @return list<string> */
    private function lines(string $file): array
    {
        $path = $this->dir . '/' . $file;

        return is_file($path) ? array_values(array_filter(explode("\n", (string) file_get_contents($path)))) : [];
    }

    /**
     * @param array<string, string> $ini
     * @param array<string, string> $env
     * @return array{0: int, 1: string} exit code and stdout
     */
    private function runScript(string $script, string $cwd, array $ini = [], array $env = []): array
    {
        $command = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'error_log=' . $this->dir . '/php_error.log'];
        foreach ($ini as $name => $value) {
            array_push($command, '-d', "{$name}={$value}");
        }
        $command[] = $script;

        $process = proc_open(
            command: $command,
            descriptor_spec: [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            pipes: $pipes,
            cwd: $cwd,
            env_vars: array_merge(getenv(), [
                'LB_DATABASE_HOSTSPEC' => self::UNREACHABLE_DATABASE,
                'LB_LOGGING_FOLDER' => $this->dir,
                'LB_LOGGING_LEVEL' => 'error',
            ], $env),
        );
        $this->assertIsResource($process, 'Failed to start PHP subprocess');

        $stdout = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout];
    }
}
