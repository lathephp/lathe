<?php

use Lathe\Widgets\Results\BarResult;
use Lathe\Widgets\Results\PartitionResult;
use Lathe\Widgets\Results\PieResult;
use Lathe\Widgets\Results\RadialResult;

/**
 * The payload a chart card receives for each result type.
 *
 * @return array<string, Closure(): array<string, mixed>>
 */
function chartPayloads(): array
{
    return [
        'bar' => fn () => (new BarResult(['a' => 1]))->prefix('$')->suffix('order')->withoutSuffixInflection()->format('a')->payload(),
        'partition' => fn () => (new PartitionResult(['a' => 1]))->prefix('$')->suffix('order')->withoutSuffixInflection()->format('a')->payload(),
        'pie' => fn () => (new PieResult(['a' => 1]))->prefix('$')->suffix('order')->withoutSuffixInflection()->format('a')->jsonSerialize(),
        'radial' => fn () => (new RadialResult(5, 10))->prefix('$')->suffix('order')->withoutSuffixInflection()->format('a')->jsonSerialize(),
    ];
}

it('sends the number display options of a chart result', function (string $type) {
    expect(chartPayloads()[$type]())->toMatchArray([
        'prefix' => '$',
        'suffix' => 'order',
        'suffixInflection' => false,
        'format' => 'a',
    ]);
})->with(['bar', 'partition', 'pie', 'radial']);

it('sends nothing extra for a chart result that sets no display option', function (string $type) {
    $payload = match ($type) {
        'bar' => (new BarResult(['a' => 1]))->payload(),
        'partition' => (new PartitionResult(['a' => 1]))->payload(),
        'pie' => (new PieResult(['a' => 1]))->jsonSerialize(),
        'radial' => (new RadialResult(5, 10))->jsonSerialize(),
    };

    expect($payload)->not->toHaveKeys(['prefix', 'suffix', 'suffixInflection', 'format']);
})->with(['bar', 'partition', 'pie', 'radial']);

it('sets a currency symbol as the prefix', function (string $type) {
    $make = fn () => match ($type) {
        'bar' => new BarResult(['a' => 1]),
        'partition' => new PartitionResult(['a' => 1]),
        'pie' => new PieResult(['a' => 1]),
        'radial' => new RadialResult(5, 10),
    };
    $prefix = fn (object $result) => ($result instanceof BarResult || $result instanceof PartitionResult ? $result->payload() : $result->jsonSerialize())['prefix'];

    expect($prefix($make()->dollars()))->toBe('$')
        ->and($prefix($make()->euros()))->toBe('€')
        ->and($prefix($make()->currency('£')))->toBe('£')
        ->and($prefix($make()->dollars('US$')))->toBe('US$');
})->with(['bar', 'partition', 'pie', 'radial']);

it('sends an Intl options array as the format', function () {
    $format = ['style' => 'currency', 'currency' => 'EUR'];

    expect((new BarResult(['a' => 1]))->format($format)->payload()['format'])->toBe($format);
});
