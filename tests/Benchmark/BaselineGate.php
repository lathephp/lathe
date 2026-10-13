<?php

namespace Lathe\Tests\Benchmark;

/**
 * Compares a run's metrics with the last release's baseline file.
 */
class BaselineGate
{
    /**
     * The failures of the metrics that are more than 20% worse than their baseline.
     * Every metric is a time, a byte count or a query count, so higher is worse.
     * With no baseline file (before the first release) only the absolute budgets apply.
     *
     * @param  list<array{name: string, value: float|int, unit: string, budget: float|int|null}>  $metrics
     * @return list<string>
     */
    public static function compare(array $metrics, string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        /** @var array{release: string, metrics: list<array{name: string, value: float|int}>} $baseline */
        $baseline = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $before = array_column($baseline['metrics'], 'value', 'name');
        $failures = [];

        foreach ($metrics as $metric) {
            $old = $before[$metric['name']] ?? null;

            // Times five instead of times 1.2 keeps exactly 20% on the passing side without float noise.
            if ($old !== null && ($metric['value'] - $old) * 5 > $old) {
                $failures[] = "{$metric['name']}: {$metric['value']} {$metric['unit']} is more than 20% over the {$baseline['release']} baseline of {$old} {$metric['unit']}";
            }
        }

        return $failures;
    }
}
