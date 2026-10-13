<?php

namespace Lathe\Field;

use Closure;

/**
 * A numeric value. min, max and step become input attributes, and min and max also become server rules.
 */
abstract class NumericField extends Field
{
    protected string $component = 'number-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options = [
            ...$this->options,
            'textAlign' => $this->isComputed() ? 'center' : 'right',
            'min' => null,
            'max' => null,
            'step' => null,
        ];
    }

    public function min(float $min): static
    {
        return $this->option('min', $min);
    }

    public function max(float $max): static
    {
        return $this->option('max', $max);
    }

    public function step(float $step): static
    {
        return $this->option('step', $step);
    }

    /**
     * @return list<mixed>
     */
    protected function impliedRules(): array
    {
        return array_values(array_filter([
            'numeric',
            $this->options['min'] === null ? null : "min:{$this->bound('min')}",
            $this->options['max'] === null ? null : "max:{$this->bound('max')}",
        ]));
    }

    private function bound(string $name): string
    {
        $bound = $this->options[$name];

        return is_int($bound) || is_float($bound) || is_string($bound) ? (string) $bound : '';
    }

    protected function supportsInline(): bool
    {
        return true;
    }
}
