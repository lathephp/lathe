<?php

use Lathe\Widgets\PartitionColors;

it('gives a user colour to its key without using up a slot', function () {
    expect(PartitionColors::forKeys(['a', 'b', 'c'], ['b' => '#ff0000']))
        ->toBe(['a' => 'var(--chart-1)', 'b' => '#ff0000', 'c' => 'var(--chart-2)']);
});

it('runs slots 1 to 5 through the chart tokens and slots 6 to 15 through ten distinct hues', function () {
    $colors = array_values(PartitionColors::forKeys(range(1, 15)));

    expect(array_slice($colors, 0, 5))->toBe(['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)']);

    $hues = array_slice($colors, 5);

    expect($hues)->toHaveCount(10)
        ->and(array_unique($hues))->toHaveCount(10)
        ->and($hues)->each->toMatch('/^#[0-9a-f]{6}$/');
});

it('gives the 16th key slot 1 again', function () {
    $colors = array_values(PartitionColors::forKeys(range(1, 16)));

    expect($colors[15])->toBe('var(--chart-1)');
});
