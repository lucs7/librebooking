<?php

declare(strict_types=1);

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__) . '/');
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

// Lang files do bare `require_once('Language.php')` / `require_once('en_gb.php')`.
// Add lang/ to include_path so those resolve regardless of cwd.
set_include_path(get_include_path() . PATH_SEPARATOR . ROOT_DIR . 'lang');

require_once(ROOT_DIR . 'lang/Language.php');
require_once(ROOT_DIR . 'lang/AvailableLanguage.php');
require_once(ROOT_DIR . 'lang/AvailableLanguages.php');

final class TranslationLinter
{
    private string $langDir;
    private bool $useColor;
    private bool $strict = false;
    private bool $json = false;
    private ?string $filterLanguage = null;

    /** @var array{errors:int,warnings:int,languages:array<string,array<string,array<int,array{severity:string,message:string}>>>,coverage:array<string,array{changed:int,total:int,pct:float}>} */
    private array $report = ['errors' => 0, 'warnings' => 0, 'languages' => [], 'coverage' => []];

    public function __construct()
    {
        $this->langDir = ROOT_DIR . 'lang/';
        $this->useColor = function_exists('stream_isatty')
            && @stream_isatty(STDOUT)
            && getenv('NO_COLOR') === false;
    }

    public static function main(array $argv): int
    {
        $linter = new self();
        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--strict') {
                $linter->strict = true;
            } elseif ($arg === '--json') {
                $linter->json = true;
            } elseif (str_starts_with($arg, '--language=')) {
                $linter->filterLanguage = strtolower(substr($arg, 11));
            } elseif ($arg === '--help' || $arg === '-h') {
                self::usage();
                return 0;
            } else {
                fwrite(STDERR, "Unknown argument: $arg\n");
                self::usage();
                return 2;
            }
        }
        return $linter->run();
    }

    private static function usage(): void
    {
        fwrite(STDOUT, <<<'HELP'
Usage: lint-translations.php [options]

Lints language files in lang/ for consistency against the en_us baseline.

Options:
  --strict             Exit non-zero if any errors are reported
  --language=<code>    Limit to a single language code (e.g. --language=de_de)
  --json               Output machine-readable JSON instead of a human report
  --help, -h           Show this message

Checks (per language, against en_us baseline):
  1. Format-specifier analysis:
       stray '%' (vsprintf will throw)                  [error]
       more placeholders than en_us                     [error]
       same count, different types                      [error]
       fewer placeholders than en_us (likely editorial) [warn]
  2. Orphan keys not present in en_us                   [warn]
  3. Structural arrays Dates/Days/Months/Letters drift  [error/warn]
  4. Empty translation values (would render as '?')     [warn]
  5. lang/*.php <-> AvailableLanguages.php parity       [error]
  6. lang/{code}/*.tpl email template parity            [warn]

HELP);
    }

    private function run(): int
    {
        $registered = AvailableLanguages::GetAvailableLanguages();

        $baseline = $this->loadLanguage('en_us');
        if ($baseline === null) {
            fwrite(STDERR, "ERROR: cannot load en_us baseline\n");
            return 2;
        }

        if ($this->filterLanguage === null) {
            $this->checkRegistrationParity($registered);
        }

        foreach ($registered as $code => $available) {
            if ($this->filterLanguage !== null && $code !== $this->filterLanguage) {
                continue;
            }
            // en_us is the baseline; en_gb is a regional spelling variant of it
            // and the merged-catalogue comparison can't distinguish its overrides
            // from inherited strings, so it always shows 0% coverage — noise.
            if (in_array($code, ['en_us', 'en_gb'], true)) {
                continue;
            }
            $lang = $this->loadLanguage($code);
            if ($lang === null) {
                $this->langError($code, 'load-failed', "could not instantiate language class '$code'");
                continue;
            }
            $this->checkLanguage($code, $lang, $baseline);
            $this->checkEmailTemplates($code);
        }

        return $this->emitReport();
    }

    private function loadLanguage(string $code): ?Language
    {
        $file = $this->langDir . $code . '.php';
        if (!file_exists($file)) {
            return null;
        }
        require_once($file);
        if (!class_exists($code, false)) {
            return null;
        }
        try {
            $instance = new $code();
        } catch (Throwable $e) {
            $this->langError($code, 'construct-failed', $e->getMessage());
            return null;
        }
        return $instance instanceof Language ? $instance : null;
    }

    /**
     * @param array<string,AvailableLanguage> $registered
     */
    private function checkRegistrationParity(array $registered): void
    {
        $registeredFiles = [];
        foreach ($registered as $code => $available) {
            $registeredFiles[$available->LanguageFile] = $code;
        }

        $support = ['Language.php', 'AvailableLanguage.php', 'AvailableLanguages.php'];
        $actualFiles = glob($this->langDir . '*.php') ?: [];
        foreach ($actualFiles as $path) {
            $base = basename($path);
            if (in_array($base, $support, true)) {
                continue;
            }
            if (!isset($registeredFiles[$base])) {
                $this->langError(
                    '(registration)',
                    'unregistered-file',
                    "lang/$base is on disk but not listed in AvailableLanguages.php"
                );
            }
        }
        foreach ($registeredFiles as $file => $code) {
            if (!file_exists($this->langDir . $file)) {
                $this->langError(
                    $code,
                    'missing-file',
                    "AvailableLanguages.php references lang/$file but the file is missing"
                );
            }
        }
    }

    private function checkLanguage(string $code, Language $lang, Language $baseline): void
    {
        // Coverage: % of en_us keys whose value differs in this language.
        // Conflates "translated" with "happens to share spelling" (e.g. 'Email'),
        // so treat as an indicator, not a precise figure.
        $baseKeys = array_keys($baseline->Strings);
        $total = count($baseKeys);
        $changed = 0;
        foreach ($baseKeys as $k) {
            if (isset($lang->Strings[$k]) && $lang->Strings[$k] !== $baseline->Strings[$k]) {
                $changed++;
            }
        }
        $this->report['coverage'][$code] = [
            'changed' => $changed,
            'total' => $total,
            'pct' => $total > 0 ? round(100 * $changed / $total, 1) : 0.0,
        ];

        // 1. format-specifier analysis. Three distinct failure modes:
        //   - stray '%'             → ValueError at runtime, user sees raw format    [error]
        //   - more specs than en_us → ValueError at runtime (callsite passes too few) [error]
        //   - type mismatch         → wrong substitution or ValueError                [error]
        //   - fewer specs than en_us → vsprintf silently drops the extra arg          [warn]
        //                              (often an intentional editorial choice)
        foreach ($baseline->Strings as $key => $baseStr) {
            if (!isset($lang->Strings[$key]) || !is_string($baseStr) || !is_string($lang->Strings[$key])) {
                continue;
            }
            $tr = $lang->Strings[$key];
            if ($tr === $baseStr) {
                continue;
            }

            if ($this->hasStrayPercent($tr)) {
                $this->langError(
                    $code,
                    'format-stray-percent',
                    "key=$key  stray '%' is not a valid specifier — vsprintf will throw at runtime\n"
                    . "  $code: \"" . $this->trunc($tr) . '"'
                );
                continue;
            }

            $baseSpecs = $this->extractSpecifiers($baseStr);
            $trSpecs = $this->extractSpecifiers($tr);
            if ($baseSpecs === $trSpecs) {
                continue;
            }

            $baseLine = '  en_us [' . implode(' ', $baseSpecs) . ']: "' . $this->trunc($baseStr) . '"';
            $trLine = "  $code [" . implode(' ', $trSpecs) . ']: "' . $this->trunc($tr) . '"';

            if (count($trSpecs) > count($baseSpecs)) {
                $this->langError(
                    $code,
                    'format-too-many',
                    "key=$key  translation has MORE placeholders than en_us — callsite will under-supply args and vsprintf will fail\n"
                    . $baseLine . "\n" . $trLine
                );
            } elseif (count($trSpecs) < count($baseSpecs)) {
                $this->langWarn(
                    $code,
                    'format-too-few',
                    "key=$key  translation has FEWER placeholders than en_us — extra callsite args silently dropped (may be intentional)\n"
                    . $baseLine . "\n" . $trLine
                );
            } else {
                // same count, different types or positional order
                $this->langError(
                    $code,
                    'format-type-mismatch',
                    "key=$key  placeholder count matches but types/positions differ\n"
                    . $baseLine . "\n" . $trLine
                );
            }
        }

        // 2. orphan keys defined here but absent from en_us
        $orphans = array_values(array_diff(array_keys($lang->Strings), array_keys($baseline->Strings)));
        if ($orphans) {
            $shown = array_slice($orphans, 0, 10);
            $this->langWarn(
                $code,
                'orphan-keys',
                count($orphans) . ' key(s) in ' . $code . ' but not in en_us: ' . implode(', ', $shown)
                . (count($orphans) > 10 ? ', ...' : '')
            );
        }

        // 3. structural arrays
        $structural = ['Dates' => 'error', 'Days' => 'error', 'Months' => 'error', 'Letters' => 'warn'];
        foreach ($structural as $prop => $sev) {
            $baseKeys = array_keys($baseline->$prop);
            $trKeys = array_keys($lang->$prop);
            $missing = array_values(array_diff($baseKeys, $trKeys));
            $extra = array_values(array_diff($trKeys, $baseKeys));
            if ($missing) {
                $this->langIssue($code, "$prop-missing", $sev, "$prop missing keys: " . implode(', ', $missing));
            }
            if ($extra) {
                $this->langWarn($code, "$prop-extra", "$prop extra keys (not in en_us): " . implode(', ', $extra));
            }
        }

        // 4. empty translation values - these render as '?'
        $empty = [];
        foreach ($lang->Strings as $k => $v) {
            if (is_string($v) && trim($v) === '') {
                $empty[] = $k;
            }
        }
        if ($empty) {
            $shown = array_slice($empty, 0, 10);
            $this->langWarn(
                $code,
                'empty-values',
                count($empty) . " key(s) have empty values (render as '?'): " . implode(', ', $shown)
                . (count($empty) > 10 ? ', ...' : '')
            );
        }
    }

    private function checkEmailTemplates(string $code): void
    {
        $baseDir = $this->langDir . 'en_us';
        $trDir = $this->langDir . $code;
        if (!is_dir($trDir) || !is_dir($baseDir)) {
            return;
        }
        $baseTpls = array_map('basename', glob($baseDir . '/*.tpl') ?: []);
        $trTpls = array_map('basename', glob($trDir . '/*.tpl') ?: []);
        $missing = array_values(array_diff($baseTpls, $trTpls));
        $orphans = array_values(array_diff($trTpls, $baseTpls));
        if ($missing) {
            $this->langWarn($code, 'email-tpl-missing', 'missing email templates: ' . implode(', ', $missing));
        }
        if ($orphans) {
            $this->langWarn(
                $code,
                'email-tpl-orphan',
                'orphan email templates (not in en_us/): ' . implode(', ', $orphans)
            );
        }
    }

    /**
     * Detect a '%' that isn't part of a valid sprintf specifier or '%%'. PHP 8's
     * vsprintf throws ValueError on such strings, which means a translator typo
     * like '% s' (with a space) silently breaks the page at runtime.
     */
    private function hasStrayPercent(string $s): bool
    {
        $clean = preg_replace(
            '/%(?:\d+\$)?[-+0 ]*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]/',
            '',
            $s
        );
        return $clean !== null && str_contains($clean, '%');
    }

    /**
     * Extract sprintf specifiers preserving order and type, ignoring widths,
     * flags, and precision. Positional specifiers (e.g. %1$s) are kept as
     * '1$s' so reordering counts as a mismatch.
     *
     * @return array<int,string>
     */
    private function extractSpecifiers(string $s): array
    {
        $out = [];
        $pattern = '/%(?:(\d+)\$)?[-+0 ]*\d*(?:\.\d+)?([bcdeEfFgGosuxX%])/';
        if (!preg_match_all($pattern, $s, $matches, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($matches as $m) {
            if ($m[2] === '%') {
                continue;
            }
            $out[] = $m[1] !== '' ? $m[1] . '$' . $m[2] : $m[2];
        }
        return $out;
    }

    private function trunc(string $s, int $max = 60): string
    {
        $s = str_replace(["\n", "\r"], ' ', $s);
        if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max - 1) . '…';
        }
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
    }

    private function langError(string $code, string $type, string $message): void
    {
        $this->report['languages'][$code][$type][] = ['severity' => 'error', 'message' => $message];
        $this->report['errors']++;
    }

    private function langWarn(string $code, string $type, string $message): void
    {
        $this->report['languages'][$code][$type][] = ['severity' => 'warn', 'message' => $message];
        $this->report['warnings']++;
    }

    private function langIssue(string $code, string $type, string $severity, string $message): void
    {
        if ($severity === 'error') {
            $this->langError($code, $type, $message);
        } else {
            $this->langWarn($code, $type, $message);
        }
    }

    private function emitReport(): int
    {
        if ($this->json) {
            fwrite(STDOUT, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        } else {
            $this->emitHumanReport();
        }
        return ($this->strict && $this->report['errors'] > 0) ? 1 : 0;
    }

    private function emitHumanReport(): void
    {
        // Per-language sections: include every language with coverage data,
        // plus any (registration)-style synthetic entries that only appear in 'languages'.
        $allCodes = array_unique(array_merge(
            array_keys($this->report['coverage']),
            array_keys($this->report['languages']),
        ));

        if (!$allCodes) {
            $this->println($this->c('No languages checked.', 'yellow'));
            return;
        }

        foreach ($allCodes as $code) {
            $coverage = $this->report['coverage'][$code] ?? null;
            $issues = $this->report['languages'][$code] ?? [];

            $header = $this->c($code, 'bold');
            if ($coverage !== null) {
                $pct = $this->colorByCoverage(sprintf('%5.1f%%', $coverage['pct']), $coverage['pct']);
                $header .= sprintf(
                    '  coverage: %s (%d/%d strings differ from en_us)',
                    $pct,
                    $coverage['changed'],
                    $coverage['total']
                );
            }
            $bar = str_repeat('─', max(2, 60 - strlen($code)));
            $this->println($header);
            $this->println($bar);

            foreach ($issues as $type => $entries) {
                foreach ($entries as $entry) {
                    $tag = $entry['severity'] === 'error'
                        ? $this->c('ERROR', 'red')
                        : $this->c('WARN ', 'yellow');
                    $this->println("  $tag $type");
                    foreach (explode("\n", $entry['message']) as $line) {
                        $this->println('        ' . $line);
                    }
                }
            }
            $this->println('');
        }

        // Overall coverage summary
        if ($this->report['coverage']) {
            $pcts = array_column($this->report['coverage'], 'pct');
            $avg = array_sum($pcts) / count($pcts);
            $this->println(sprintf(
                'Coverage: avg %.1f%% across %d languages (min %.1f%%, max %.1f%%)',
                $avg,
                count($pcts),
                min($pcts),
                max($pcts)
            ));
        }

        $errStr = $this->report['errors'] > 0
            ? $this->c($this->report['errors'] . ' error(s)', 'red')
            : '0 errors';
        $warnStr = $this->report['warnings'] > 0
            ? $this->c($this->report['warnings'] . ' warning(s)', 'yellow')
            : '0 warnings';
        $this->println("Summary: $errStr, $warnStr");
    }

    private function colorByCoverage(string $s, float $pct): string
    {
        if (!$this->useColor) {
            return $s;
        }
        $color = $pct >= 85 ? 'green' : ($pct >= 50 ? 'yellow' : 'red');
        return $this->c($s, $color);
    }

    private function c(string $s, string $color): string
    {
        if (!$this->useColor) {
            return $s;
        }
        $codes = ['red' => '31', 'green' => '32', 'yellow' => '33', 'bold' => '1'];
        $code = $codes[$color] ?? '0';
        return "\033[{$code}m$s\033[0m";
    }

    private function println(string $s): void
    {
        fwrite(STDOUT, $s . "\n");
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    exit(TranslationLinter::main($argv));
}
