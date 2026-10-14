<?php

namespace Lathe\Tests\Fixtures\Widgets;

use DateInterval;
use DateTimeInterface;

/**
 * Counts the orders, cached for as long as the test sets.
 */
class TtlOrders extends ValueOrders
{
    public static DateTimeInterface|DateInterval|int|null $ttl = null;

    public function cacheFor(): DateTimeInterface|DateInterval|int|null
    {
        return static::$ttl;
    }
}
