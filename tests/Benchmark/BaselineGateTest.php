<?php

use Lathe\Tests\Benchmark\BaselineGate;

/**
 * A baseline file in this test's own temp folder, holding one metric.
 */
function baselineWith(string $name, float $value): string
{
    $path = sys_get_temp_dir().'/lathe-baseline-'.bin2hex(random_bytes(6)).'/baseline.json';
    mkdir(dirname($path));
    file_put_contents($path, json_encode([
        'release' => 'v1.0.0',
        'metrics' => [['name' => $name, 'value' => $value, 'unit' => 'ms', 'budget' => 50]],
    ]));

    return $path;
}

/**
 * @return list<array{name: string, value: float, unit: string, budget: float}>
 */
function listMetric(float $value): array
{
    return [['name' => 'B1 list p95', 'value' => $value, 'unit' => 'ms', 'budget' => 50.0]];
}

it('fails a metric 21% worse than its baseline', function () {
    expect(BaselineGate::compare(listMetric(36.3), baselineWith('B1 list p95', 30.0)))
        ->toBe(['B1 list p95: 36.3 ms is more than 20% over the v1.0.0 baseline of 30 ms']);
});

it('passes a metric exactly 20% worse than its baseline', function () {
    expect(BaselineGate::compare(listMetric(36.0), baselineWith('B1 list p95', 30.0)))->toBe([]);
});

it('passes a metric 19% worse than its baseline', function () {
    expect(BaselineGate::compare(listMetric(35.7), baselineWith('B1 list p95', 30.0)))->toBe([]);
});

it('applies only the absolute budgets when no baseline file exists', function () {
    $missing = sys_get_temp_dir().'/lathe-baseline-'.bin2hex(random_bytes(6)).'/baseline.json';

    expect(BaselineGate::compare(listMetric(500.0), $missing))->toBe([]);
});
