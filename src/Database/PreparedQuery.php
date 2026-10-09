<?php

declare(strict_types=1);

namespace LibreBooking\Database;

/**
 * SQL with positional placeholders and the values to bind to them, in order.
 */
final class PreparedQuery
{
    /**
     * @param list<string|null> $values
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $values,
    ) {
    }
}
