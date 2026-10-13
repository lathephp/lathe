<?php

namespace Lathe\Tests\Benchmark;

/**
 * Wall-clock timing for the budget tests.
 */
class Timing
{
    /**
     * Milliseconds since an hrtime(true) reading.
     */
    public static function since(int|float $started): float
    {
        return (hrtime(true) - $started) / 1e6;
    }

    /**
     * The 95th percentile: of 500 samples, the 475th smallest.
     *
     * @param  list<float>  $samples
     */
    public static function p95(array $samples): float
    {
        sort($samples);

        return $samples[(int) ceil(count($samples) * 0.95) - 1];
    }
}
