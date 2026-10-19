<?php

namespace Lathe\Widgets\Results;

use Closure;
use InvalidArgumentException;
use JsonSerializable;
use Lathe\Lathe;

/**
 * How far a number has come towards its target.
 */
class ProgressResult implements JsonSerializable
{
    public ?string $prefix = null;

    public ?string $suffix = null;

    public bool $suffixInflection = true;

    /** @var string|array<string, mixed>|null */
    public string|array|null $format = null;

    protected bool $avoid = false;

    protected ?string $tone = null;

    protected ?string $label = null;

    protected ?Closure $transformCallback = null;

    public function __construct(
        public int|float $value,
        public int|float $target = 100,
    ) {
        //
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function withoutSuffixInflection(): static
    {
        $this->suffixInflection = false;

        return $this;
    }

    /**
     * @param  string|array<string, mixed>  $format
     */
    public function format(string|array $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function dollars(string $symbol = '$'): static
    {
        return $this->prefix($symbol);
    }

    public function euros(string $symbol = '€'): static
    {
        return $this->prefix($symbol);
    }

    /**
     * For a number that should stay low, such as an error rate: the colours run the other way.
     */
    public function avoid(): static
    {
        $this->avoid = true;

        return $this;
    }

    /**
     * A fixed colour in place of the target colours, for a share that measures a state rather than
     * a goal: primary, info, success, warning or destructive.
     */
    public function tone(string $tone): static
    {
        if (! in_array($tone, ['primary', 'info', 'success', 'warning', 'destructive'], true)) {
            throw new InvalidArgumentException("A progress tone is primary, info, success, warning or destructive, not [{$tone}].");
        }

        $this->tone = $tone;

        return $this;
    }

    /**
     * The words after the share, in place of "of target".
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Runs on the value on the server.
     *
     * @param  callable(int|float): mixed  $callback
     */
    public function transform(callable $callback): static
    {
        $this->transformCallback = $callback(...);

        return $this;
    }

    /**
     * The share of the target reached, from 0 to 100.
     */
    public function percentage(): float
    {
        if ($this->target == 0) {
            return 0.0;
        }

        return min(100.0, round($this->value / $this->target * 100, 2));
    }

    /**
     * The tone when one is set; else destructive below 33%, warning from 33% to below 66%, success
     * from 66%, the other way round when avoiding.
     */
    public function color(): string
    {
        if ($this->tone !== null) {
            return $this->tone;
        }

        $percentage = $this->percentage();

        if ($percentage < 33) {
            return $this->avoid ? 'success' : 'destructive';
        }

        if ($percentage < 66) {
            return 'warning';
        }

        return $this->avoid ? 'destructive' : 'success';
    }

    public function caption(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return $this->avoid ? Lathe::__('Lower is better') : Lathe::__('of target');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->transformCallback === null ? $this->value : ($this->transformCallback)($this->value),
            'target' => max($this->value, $this->target),
            'percentage' => $this->percentage(),
            'avoid' => $this->avoid,
            'color' => $this->color(),
            'caption' => $this->caption(),
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'suffixInflection' => $this->suffixInflection,
            'format' => $this->format,
        ];
    }
}
