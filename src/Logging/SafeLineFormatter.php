<?php

declare(strict_types=1);

namespace LibreBooking\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

/**
 * A line formatter whose output cannot forge log lines or carry terminal escape sequences.
 *
 * Control characters, newlines included, are escaped as \xNN, so every record stays on
 * one physical line whatever the message, context or stack trace contains. Text that is
 * not valid UTF-8 has its invalid bytes replaced with U+FFFD; valid non-ASCII text is kept.
 */
final class SafeLineFormatter extends LineFormatter
{
    /** C0 controls except tab, DEL, C1 controls (U+0080-U+009F) and U+2028/U+2029, in valid UTF-8. */
    private const CONTROL_CHARS = '/[\x00-\x08\x0A-\x1F\x7F]|\xC2[\x80-\x9F]|\xE2\x80[\xA8\xA9]/';
    private const REPLACEMENT_CHARACTER = 0xFFFD;
    private const UNPRINTABLE = '[unprintable]';

    public function __construct(?string $format = null)
    {
        parent::__construct(format: $format, includeStacktraces: true);
    }

    public function format(LogRecord $record): string
    {
        return self::escape(rtrim(string: parent::format($record), characters: "\n")) . "\n";
    }

    /** Makes $text safe for a single log line. */
    public static function escape(string $text): string
    {
        if (!mb_check_encoding(value: $text, encoding: 'UTF-8')) {
            $previous = mb_substitute_character();
            mb_substitute_character(substitute_character: self::REPLACEMENT_CHARACTER);
            try {
                $text = mb_scrub(string: $text, encoding: 'UTF-8');
            } finally {
                mb_substitute_character(substitute_character: $previous);
            }
        }

        return preg_replace_callback(
            pattern: self::CONTROL_CHARS,
            callback: static fn (array $m): string => implode(
                '',
                array_map(static fn (string $byte): string => sprintf('\\x%02X', ord($byte)), str_split($m[0]))
            ),
            subject: $text,
        ) ?? self::UNPRINTABLE;
    }
}
