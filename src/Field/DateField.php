<?php

namespace Lathe\Field;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use LogicException;

/**
 * A calendar day. It travels as Y-m-d with no timezone, so no zone moves it. The model must cast
 * the column to date; a field on a model without that cast throws a LogicException when it
 * resolves or fills. min and max become the after_or_equal and before_or_equal rules.
 */
class DateField extends Field
{
    protected string $component = 'date-field';

    protected const string FORMAT = 'Y-m-d';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options = [...$this->options, 'min' => null, 'max' => null];
    }

    public function min(string|DateTimeInterface $min): static
    {
        return $this->option('min', $this->bound($min));
    }

    public function max(string|DateTimeInterface $max): static
    {
        return $this->option('max', $this->bound($max));
    }

    /**
     * The input's step: days for a date, seconds for a date-time.
     */
    public function step(int $step): static
    {
        return $this->option('step', max(1, $step));
    }

    public function resolve(object $record, ?string $attribute = null): mixed
    {
        if ($this->resolveCallback === null) {
            $this->assertCast($record);
        }

        $raw = $this->resolveCallback === null ? $this->lifecycle->extract($record, $attribute ?? $this->attribute) : null;

        return $raw instanceof DateTimeInterface ? $raw->format(static::FORMAT) : parent::resolve($record, $attribute);
    }

    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $this->assertCast($model);

        return parent::fill($request, $model, $requestAttribute);
    }

    /**
     * @return list<mixed>
     */
    protected function impliedRules(): array
    {
        return array_values(array_filter([
            'date_format:'.static::FORMAT,
            $this->options['min'] === null ? null : "after_or_equal:{$this->optionText('min')}",
            $this->options['max'] === null ? null : "before_or_equal:{$this->optionText('max')}",
        ]));
    }

    protected function bound(string|DateTimeInterface $bound): string
    {
        return $bound instanceof DateTimeInterface ? $bound->format(static::FORMAT) : $bound;
    }

    protected function optionText(string $name): string
    {
        $value = $this->options[$name];

        return is_string($value) ? $value : '';
    }

    /**
     * A Date column the model does not cast to date would hand back a string the field cannot trust.
     */
    protected function assertCast(object $record): void
    {
        if (! $record instanceof Model) {
            return;
        }

        $cast = $record->getCasts()[$this->attribute] ?? '';

        if (preg_match('/^(immutable_)?date(:.*)?\z/', $cast) !== 1) {
            throw new LogicException("The date field [{$this->attribute}] needs a date cast on ".$record::class.'.');
        }
    }
}
