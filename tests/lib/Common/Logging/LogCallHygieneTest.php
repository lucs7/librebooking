<?php

declare(strict_types=1);

/**
 * Guards every Log:: call in the application code:
 *  - the format string is a literal, so a '%' in user input cannot change it
 *  - no argument passes a token, password, secret, email address or raw payment
 *    payload
 * Parsed with PHP's tokenizer, so multi-line calls and string contents are handled exactly.
 */
class LogCallHygieneTest extends TestBase
{
    private const SCANNED = ['Controls', 'Domain', 'Jobs', 'lib', 'Pages', 'plugins', 'Presenters', 'src', 'Web', 'WebServices'];
    private const SKIPPED = ['lib/external/', 'plugins/Authentication/CAS/CAS-'];
    private const LOG_METHODS = ['Debug', 'Info', 'Warning', 'Error', 'Sql'];

    /** Variables whose value must never be logged. */
    private const SECRET_VARIABLES = '/^\$(token|sessionToken|csrfToken|password|newPassword|currentPassword|secret|apiKey|providedKey|email|emailAddress)$/i';
    /** Properties or methods whose value must never be logged. */
    private const SECRET_MEMBERS = ['Email', 'EmailAddress', 'SessionToken', 'CSRFToken', 'Password'];
    /** Raw API payloads (payer data) must not be serialized into the log. */
    private const PAYLOAD_VARIABLES = ['$response', '$charge', '$refund', '$body'];

    public function testLogCallsUseLiteralFormatsAndNoSecrets(): void
    {
        $violations = [];
        foreach ($this->files() as $file) {
            foreach ($this->logCalls($file) as [$line, $args]) {
                foreach ($this->problems($args) as $problem) {
                    $violations[] = sprintf('%s:%d %s', substr($file, strlen(ROOT_DIR)), $line, $problem);
                }
            }
        }

        $this->assertSame([], $violations, "Log calls that need fixing:\n" . implode("\n", $violations));
    }

    /** @return iterable<string> */
    private function files(): iterable
    {
        foreach (self::SCANNED as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_DIR . $dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen(ROOT_DIR));
                if ($file->getExtension() !== 'php' || array_filter(self::SKIPPED, fn (string $skip) => str_starts_with($relative, $skip))) {
                    continue;
                }
                yield $file->getPathname();
            }
        }
    }

    /**
     * @return iterable<array{int, list<list<array|string>>}> line and top-level argument token lists of each Log:: call
     */
    private function logCalls(string $file): iterable
    {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            static fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        $count = count($tokens);
        for ($i = 0; $i + 3 < $count; $i++) {
            if (!$this->isToken($tokens[$i], T_STRING, 'Log') || !$this->isToken($tokens[$i + 1], T_DOUBLE_COLON)
                || !is_array($tokens[$i + 2]) || !in_array($tokens[$i + 2][1], self::LOG_METHODS, true) || $tokens[$i + 3] !== '(') {
                continue;
            }
            $args = [[]];
            $depth = 0;
            for ($j = $i + 4; $j < $count; $j++) {
                $t = $tokens[$j];
                if (in_array($t, ['(', '[', '{'], true) || $this->isToken($t, T_CURLY_OPEN)) {
                    $depth++;
                } elseif (in_array($t, [')', ']', '}'], true)) {
                    if ($depth-- === 0) {
                        break;
                    }
                } elseif ($t === ',' && $depth === 0) {
                    $args[] = [];
                    continue;
                }
                $args[count($args) - 1][] = $t;
            }
            yield [$tokens[$i][2], array_values(array_filter($args))];
        }
    }

    /**
     * @param list<list<array|string>> $args
     * @return list<string>
     */
    private function problems(array $args): array
    {
        if ($args === []) {
            return ['has no message'];
        }
        $format = $args[0];
        if (count($format) >= 2 && $this->isToken($format[0], T_STRING, 'message') && $format[1] === ':') {
            $format = array_slice($format, 2);
        }
        $problems = [];
        if (count($format) !== 1 || !$this->isToken($format[0], T_CONSTANT_ENCAPSED_STRING)) {
            $problems[] = 'format string is not a literal';
        }
        foreach (array_slice($args, 1) as $arg) {
            foreach ($arg as $k => $t) {
                if ($this->isToken($t, T_VARIABLE) && preg_match(self::SECRET_VARIABLES, $t[1])) {
                    $problems[] = "logs {$t[1]}";
                }
                if ($this->isToken($t, T_OBJECT_OPERATOR) && isset($arg[$k + 1]) && is_array($arg[$k + 1])
                    && in_array($arg[$k + 1][1], self::SECRET_MEMBERS, true)) {
                    $problems[] = "logs ->{$arg[$k + 1][1]}";
                }
                if ($this->isToken($t, T_STRING, 'json_encode') && isset($arg[$k + 2]) && is_array($arg[$k + 2])
                    && in_array($arg[$k + 2][1], self::PAYLOAD_VARIABLES, true)) {
                    $problems[] = "logs json_encode({$arg[$k + 2][1]})";
                }
            }
        }

        return $problems;
    }

    private function isToken(array|string $token, int $id, ?string $text = null): bool
    {
        return is_array($token) && $token[0] === $id && ($text === null || $token[1] === $text);
    }
}
