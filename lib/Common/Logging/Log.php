<?php

if (file_exists(ROOT_DIR . 'vendor/autoload.php')) {
    require_once ROOT_DIR . 'vendor/autoload.php';
}

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\WebProcessor;

class Log
{
    /**
     * @var Log
     */
    private static $_instance;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Logger
     */
    private $sqlLogger;

    /**
     * @var Logger
     */
    private $authLogger;

    private function __construct()
    {
        $this->logger = new Logger('app');
        $this->sqlLogger = new Logger('sql');
        $this->authLogger = new Logger('auth');

        $log_level = self::getLogLevel();

        $log_folder = null;
        $log_sql = false;

        if ($log_level != 'none') {
            $log_folder = rtrim(Configuration::Instance()->GetKey(ConfigKeys::LOGGING_FOLDER), '/');
            $log_sql = Configuration::Instance()->GetKey(ConfigKeys::LOGGING_SQL, new BooleanConverter());
            switch ($log_level) {
                case 'debug':
                    $this->logger->pushHandler(new StreamHandler($log_folder.'/app.log', Level::Debug));
                    break;
                case 'error':
                    $this->logger->pushHandler(new StreamHandler($log_folder.'/app.log', Level::Error));
                    break;
            }
            $this->logger->pushProcessor(new WebProcessor());
        }
        if ($log_sql) {
            $this->sqlLogger->pushHandler(new StreamHandler($log_folder.'/sql.log', Level::Error));
        }

        // auth.log is independent of logging.level: security events should not
        // be silenced when the app log is set to 'none'. Resolve the folder
        // here in case the app log is disabled but auth logging is on.
        if (Configuration::Instance()->GetKey(ConfigKeys::AUTH_LOG_ENABLED, new BooleanConverter())) {
            $auth_folder = $log_folder ?? rtrim(Configuration::Instance()->GetKey(ConfigKeys::LOGGING_FOLDER), '/');
            $this->authLogger->pushHandler(new StreamHandler($auth_folder.'/auth.log', Level::Info));
        }
    }

    /**
     * Gets the configured log level in lowercase, with a fallback to 'error' if not set.
     * @return string The log level ('none', 'error', or 'debug')
     */
    private static function getLogLevel(): string
    {
        return strtolower(Configuration::Instance()->GetKey(ConfigKeys::LOGGING_LEVEL) ?? 'error');
    }

    /**
     * @return Log
     */
    private static function &GetInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new Log();
        }

        return self::$_instance;
    }

    /**
     * @param string $message
     * @param mixed $args
     */
    public static function Debug($message, $args = [])
    {
        if (self::getLogLevel() == 'none') {
            return;
        }

        try {
            $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if (is_array($debug)) {
                $debugInfo = $debug[0];
            } else {
                $debugInfo = ['file' => null, 'line' => null];
            }

            $args = func_get_args();
            $log = vsprintf(array_shift($args), array_values($args));
            $log .= sprintf(' [File=%s,Line=%s]', $debugInfo['file'], $debugInfo['line']);

            $log = '[User=' . ServiceLocator::GetServer()->GetUserSession() . '] ' . $log;

            self::GetInstance()->logger->debug($log);
        } catch (Exception $ex) {
            echo $ex;
        }
    }

    /**
     * @param string $message
     * @param mixed $args
     */
    public static function Error($message, $args = [])
    {
        if (self::getLogLevel() == 'none') {
            return;
        }

        try {
            $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if (is_array($debug)) {
                $debugInfo = $debug[0];
            } else {
                $debugInfo = ['file' => null, 'line' => null];
            }

            $args = func_get_args();
            $log = vsprintf(array_shift($args), array_values($args));
            $log .= sprintf(' [File=%s,Line=%s]', $debugInfo['file'], $debugInfo['line']);

            $log = '[User=' . ServiceLocator::GetServer()->GetUserSession() . '] ' . $log;

            self::GetInstance()->logger->error($log);
        } catch (Exception $ex) {
        }
    }

    /**
     * @static
     * @param string $message
     * @param mixed $args
     * @return void
     */
    public static function Sql($message, $args = [])
    {
        try {
            if (!Configuration::Instance()->GetKey(ConfigKeys::LOGGING_SQL, new BooleanConverter())) {
                return;
            }
            $args = func_get_args();
            $log = vsprintf(array_shift($args), array_values($args));
            $log = '[User=' . ServiceLocator::GetServer()->GetUserSession() . '] ' . $log;
            self::GetInstance()->sqlLogger->error($log);
        } catch (Exception $ex) {
        }
    }
    public static function DebugEnabled()
    {
        return self::getLogLevel() != 'none';
    }

    /**
     * Write a structured authentication event to auth.log.
     *
     * Independent of `logging.level` — when AUTH_LOG_ENABLED is true, the event
     * is always written, even if app.log is disabled. Format is fail2ban-friendly
     * key=value:
     *
     *   outcome=failure username="admin" source=feed ip=1.2.3.4 ua="curl/7.88" uri="/Web/..." reason="invalid_credentials"
     *
     * Successes are only emitted when AUTH_LOG_LEVEL='all' (the default); when
     * set to 'failure_only', successful auth events are dropped silently.
     *
     * @param string $outcome 'success' or 'failure'
     * @param string $username Username attempted (never the password)
     * @param string $source 'web' | 'feed' | 'cookie'
     * @param array $extra Optional context, e.g. ['reason' => 'invalid_credentials']
     */
    public static function Auth(string $outcome, string $username, string $source, array $extra = []): void
    {
        try {
            if (!Configuration::Instance()->GetKey(ConfigKeys::AUTH_LOG_ENABLED, new BooleanConverter())) {
                return;
            }

            $level = strtolower(
                (string) Configuration::Instance()->GetKey(ConfigKeys::AUTH_LOG_LEVEL)
            );
            if ($level === 'failure_only' && $outcome !== 'failure') {
                return;
            }
            if ($level === 'none') {
                return;
            }

            $fields = array_merge(
                [
                    'outcome' => $outcome,
                    'username' => $username,
                    'source' => $source,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                ],
                $extra
            );

            $parts = [];
            foreach ($fields as $key => $value) {
                $value = str_replace(["\r", "\n", '"'], ['', '', '\\"'], (string) $value);
                $parts[] = sprintf('%s="%s"', $key, $value);
            }
            $line = implode(' ', $parts);

            if ($outcome === 'failure') {
                self::GetInstance()->authLogger->warning($line);
            } else {
                self::GetInstance()->authLogger->info($line);
            }
        } catch (Exception $ex) {
        }
    }
}
