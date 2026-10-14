<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;
use Lathe\Widgets\Concerns\FormatsNumbers;

/**
 * A number per bucket, keyed by the bucket's label and in time order.
 */
class TrendResult implements JsonSerializable
{
    use FormatsNumbers;

    public int|float|null $headline = null;

    /** 'latest' or 'sum' when no result() set the headline */
    protected string $headlineFrom = 'latest';

    /** @var array<string, array<string, int|float>>|null */
    public ?array $series = null;

    /** @var array<string, string>|null */
    public ?array $labels = null;

    protected ?Closure $transformCallback = null;

    /** @var (Closure(int|float): (int|float))|null */
    protected ?Closure $roundCallback = null;

    protected ?int $roundedTo = null;

    protected ?int $roundedBy = null;

    /**
     * @param  array<string, int|float>  $data
     */
    public function __construct(public array $data)
    {
        //
    }

    /**
     * The headline number, where the latest value is shown without it.
     */
    public function result(int|float|null $value): static
    {
        $this->headline = $value;

        return $this;
    }

    /**
     * @param  array<string, int|float>  $trend
     */
    public function trend(array $trend): static
    {
        $this->data = $trend;

        return $this;
    }

    public function showLatestValue(): static
    {
        $this->headline = null;
        $this->headlineFrom = 'latest';

        return $this;
    }

    public function showSumValue(): static
    {
        $this->headline = null;
        $this->headlineFrom = 'sum';

        return $this;
    }

    /**
     * One trend per series name, drawn over each other.
     *
     * @param  array<string, array<string, int|float>>  $series
     */
    public function withSeries(array $series): static
    {
        $this->series = $series;

        return $this;
    }

    /**
     * A label per point, keyed like the trend.
     *
     * @param  array<string, string>  $labels
     */
    public function labels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Runs on the headline on the server, before it is rounded.
     *
     * @param  callable(int|float): mixed  $callback
     */
    public function transform(callable $callback): static
    {
        $this->transformCallback = $callback(...);

        return $this;
    }

    /**
     * Rounds the points, series and headline when the result is sent, and says how in the payload.
     *
     * @param  Closure(int|float): (int|float)  $round
     */
    public function rounded(Closure $round, int $precision, int $mode): static
    {
        $this->roundCallback = $round;
        $this->roundedTo = $precision;
        $this->roundedBy = $mode;

        return $this;
    }

    /**
     * The value of the last bucket, null for a trend without any.
     */
    public function latest(): int|float|null
    {
        return $this->data === [] ? null : $this->data[array_key_last($this->data)];
    }

    /**
     * What a widget sends for the card: the points and everything the client needs to draw them.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $headline = $this->headline ?? ($this->headlineFrom === 'sum' ? array_sum($this->data) : $this->latest());

        if ($headline !== null && $this->transformCallback !== null) {
            $headline = ($this->transformCallback)($headline);
        }

        return [
            'trend' => $this->roundPoints($this->data),
            'value' => is_int($headline) || is_float($headline) ? $this->round($headline) : $headline,
            'series' => $this->series === null ? null : array_map($this->roundPoints(...), $this->series),
            'labels' => $this->labels,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'suffixInflection' => $this->suffixInflection,
            'format' => $this->format,
            'precision' => $this->roundedTo,
            'roundingMode' => $this->roundedBy,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @param  array<string, int|float>  $points
     * @return array<string, int|float>
     */
    private function roundPoints(array $points): array
    {
        return array_map($this->round(...), $points);
    }

    private function round(int|float $number): int|float
    {
        return $this->roundCallback === null ? $number : ($this->roundCallback)($number);
    }
}
