<?php

namespace Lathe\Widgets;

use Carbon\CarbonImmutable;

/**
 * The size of one bucket in a trend.
 */
enum TrendUnit: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * The first moment of the bucket the moment falls in, on the zone's own calendar.
     */
    public function startOf(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Minute => $moment->startOfMinute(),
            self::Hour => $moment->startOfHour(),
            self::Day => $moment->startOfDay(),
            self::Week => $moment->startOfWeek(CarbonImmutable::MONDAY),
            self::Month => $moment->startOfMonth(),
            self::Quarter => $moment->startOfQuarter(),
            self::Year => $moment->startOfYear(),
        };
    }

    /**
     * The start of the bucket that many buckets after this one, before it for a negative count.
     * Minutes and hours move by elapsed time, so a daylight saving change does not skip or repeat one.
     */
    public function shift(CarbonImmutable $start, int $count): CarbonImmutable
    {
        return match ($this) {
            self::Minute => CarbonImmutable::createFromTimestamp($start->getTimestamp() + 60 * $count, $start->getTimezone()),
            self::Hour => CarbonImmutable::createFromTimestamp($start->getTimestamp() + 3600 * $count, $start->getTimezone()),
            self::Day => $start->addDays($count)->startOfDay(),
            self::Week => $start->addWeeks($count)->startOfDay(),
            self::Month => $start->addMonthsNoOverflow($count),
            self::Quarter => $start->addMonthsNoOverflow(3 * $count),
            self::Year => $start->addYearsNoOverflow($count),
        };
    }

    /**
     * The key of the bucket that starts at the moment. Minute and hour keys carry the UTC
     * offset, because a clock hour repeats when daylight saving ends.
     */
    public function key(CarbonImmutable $start): string
    {
        return match ($this) {
            self::Minute, self::Hour => $start->format('c'),
            self::Day, self::Week => $start->format('Y-m-d'),
            self::Month => $start->format('Y-m'),
            self::Quarter => $start->format('Y').'-Q'.$start->quarter,
            self::Year => $start->format('Y'),
        };
    }
}
