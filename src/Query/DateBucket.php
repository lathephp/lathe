<?php

namespace Lathe\Query;

use Illuminate\Database\Connection;

/**
 * Day and month buckets of a date column, written in each driver's own SQL.
 */
class DateBucket
{
    public static function day(Connection $connection, string $column): string
    {
        $column = $connection->getQueryGrammar()->wrap($column);

        return match ($connection->getDriverName()) {
            'pgsql' => "date_trunc('day', {$column})",
            default => "date({$column})",
        };
    }

    public static function month(Connection $connection, string $column): string
    {
        $column = $connection->getQueryGrammar()->wrap($column);

        return match ($connection->getDriverName()) {
            'pgsql' => "date_trunc('month', {$column})",
            'sqlite' => "strftime('%Y-%m-01', {$column})",
            default => "date_format({$column}, '%Y-%m-01')",
        };
    }
}
