<?php

namespace Lathe\Query;

use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Database\Connection;
use Lathe\Widgets\TrendUnit;

/**
 * Day and month buckets of a date column, written in each driver's own SQL, and buckets on a
 * user's calendar for a trend.
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

    /**
     * The label of the bucket a UTC column falls in on the zone's calendar, written as TrendUnit::key()
     * writes it, except that a minute or hour label is the local clock time without its offset.
     * The zone's offsets come from PHP, so each driver only adds seconds and cuts a string.
     */
    public static function zoned(Connection $connection, string $column, TrendUnit $unit, string $timezone, CarbonInterface $from, CarbonInterface $to): string
    {
        $driver = $connection->getDriverName();
        $local = self::local($driver, $connection->getQueryGrammar()->wrap($column), self::offset($connection, $column, $timezone, $from, $to));

        return match ($driver) {
            'pgsql' => match ($unit) {
                TrendUnit::Minute => "to_char(date_trunc('minute', {$local}), 'YYYY-MM-DD HH24:MI:SS')",
                TrendUnit::Hour => "to_char(date_trunc('hour', {$local}), 'YYYY-MM-DD HH24:MI')",
                TrendUnit::Day => "to_char({$local}, 'YYYY-MM-DD')",
                TrendUnit::Week => "to_char(date_trunc('week', {$local}), 'YYYY-MM-DD')",
                TrendUnit::Month => "to_char({$local}, 'YYYY-MM')",
                TrendUnit::Quarter => "to_char({$local}, 'YYYY\"-Q\"Q')",
                TrendUnit::Year => "to_char({$local}, 'YYYY')",
            },
            'sqlite' => match ($unit) {
                TrendUnit::Minute => "strftime('%Y-%m-%d %H:%M:00', {$local})",
                TrendUnit::Hour => "strftime('%Y-%m-%d %H:00', {$local})",
                TrendUnit::Day => "strftime('%Y-%m-%d', {$local})",
                TrendUnit::Week => "strftime('%Y-%m-%d', {$local}, '-' || ((CAST(strftime('%w', {$local}) AS INTEGER) + 6) % 7) || ' days')",
                TrendUnit::Month => "strftime('%Y-%m', {$local})",
                TrendUnit::Quarter => "strftime('%Y', {$local}) || '-Q' || ((CAST(strftime('%m', {$local}) AS INTEGER) + 2) / 3)",
                TrendUnit::Year => "strftime('%Y', {$local})",
            },
            default => match ($unit) {
                TrendUnit::Minute => "date_format({$local}, '%Y-%m-%d %H:%i:00')",
                TrendUnit::Hour => "date_format({$local}, '%Y-%m-%d %H:00')",
                TrendUnit::Day => "date_format({$local}, '%Y-%m-%d')",
                TrendUnit::Week => "date_format(date_sub({$local}, interval weekday({$local}) day), '%Y-%m-%d')",
                TrendUnit::Month => "date_format({$local}, '%Y-%m')",
                TrendUnit::Quarter => "concat(year({$local}), '-Q', quarter({$local}))",
                TrendUnit::Year => "date_format({$local}, '%Y')",
            },
        };
    }

    /**
     * The zone's UTC offset in seconds for each row of a UTC column, as a SQL integer expression.
     * It changes where the zone changes its clock between the two moments, a daylight saving change.
     * The moments are written into the SQL, not bound, so a GROUP BY on it matches the SELECT.
     */
    public static function offset(Connection $connection, string $column, string $timezone, CarbonInterface $from, CarbonInterface $to): string
    {
        $transitions = (new DateTimeZone($timezone))->getTransitions($from->getTimestamp(), $to->getTimestamp());
        $first = (int) ($transitions[0]['offset'] ?? 0);
        $transitions = array_slice($transitions, 1);

        if ($transitions === []) {
            return (string) $first;
        }

        $wrapped = $connection->getQueryGrammar()->wrap($column);
        $cases = '';

        foreach (array_reverse($transitions) as $transition) {
            $cases .= " WHEN {$wrapped} >= '".gmdate('Y-m-d H:i:s', $transition['ts'])."' THEN ".(int) $transition['offset'];
        }

        return "(CASE{$cases} ELSE {$first} END)";
    }

    private static function local(string $driver, string $column, string $offset): string
    {
        return match ($driver) {
            'pgsql' => "({$column} + {$offset} * interval '1 second')",
            'sqlite' => "datetime({$column}, ({$offset}) || ' seconds')",
            default => "date_add({$column}, interval {$offset} second)",
        };
    }
}
