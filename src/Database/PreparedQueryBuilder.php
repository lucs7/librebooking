<?php

declare(strict_types=1);

namespace LibreBooking\Database;

/**
 * Turns a query template with named placeholders (@name) into SQL with
 * positional placeholders (?) plus the values to bind to them. Values never
 * become part of the SQL text, so they cannot change the statement.
 */
final class PreparedQueryBuilder
{
    /**
     * @param array<string, mixed> $parameters placeholder name => value
     * @param list<string> $rawNames placeholders whose value is inserted into the SQL as-is (identifiers
     *                               such as a sort column); the caller must have validated these
     */
    public static function build(string $template, array $parameters, array $rawNames = []): PreparedQuery
    {
        if ($parameters === []) {
            return new PreparedQuery($template, []);
        }

        // One pass over the original template, longest names first so '@userid' is not consumed by '@user'.
        $names = array_map('strval', array_keys($parameters));
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '/' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $names)) . '/';

        $values = [];
        $sql = preg_replace_callback(
            $pattern,
            static function (array $match) use ($parameters, $rawNames, &$values): string {
                $name = $match[0];
                $value = $parameters[$name];

                if (in_array($name, $rawNames, true)) {
                    return (string) $value;
                }

                if (is_array($value)) {
                    // An empty list binds a single empty string, as the escaped-string implementation did.
                    $items = $value === [] ? [''] : array_values($value);
                    foreach ($items as $item) {
                        $values[] = (string) ($item ?? '');
                    }

                    return implode(',', array_fill(0, count($items), '?'));
                }

                $values[] = $value === null ? null : (string) $value;

                return '?';
            },
            $template
        );

        return new PreparedQuery($sql ?? $template, $values);
    }

    /**
     * Splits a template that holds several statements into one template per statement.
     *
     * @return list<string>
     */
    public static function splitStatements(string $template): array
    {
        $statements = array_map('trim', explode(';', $template));

        return array_values(array_filter($statements, static fn (string $statement): bool => $statement !== ''));
    }
}
