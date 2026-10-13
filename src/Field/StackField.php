<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Several lines in one index cell. Every line reads the same record in the same pass,
 * so a stack adds no query of its own.
 */
class StackField extends Field
{
    protected string $component = 'stack-field';

    /** @var list<Field> */
    protected array $lines = [];

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->exceptOnForms();
    }

    /**
     * @param  list<Field|Closure>  $fields  a Closure `(record)` becomes an anonymous LineField
     */
    public function lines(array $fields): static
    {
        $this->mutate();
        $this->lines = array_map(fn (Field|Closure $line): Field => $line instanceof Closure ? new LineField('', $line) : $line, $fields);

        return $this;
    }

    /**
     * @return array{value: list<array<string, mixed>>, display: string}
     */
    public function cell(Model $model): array
    {
        $lines = array_map(
            fn (Field $line): array => ['style' => 'base', 'extraClasses' => '', ...$line->cell($model)],
            $this->lines,
        );

        return ['value' => $lines, 'display' => implode(' ', array_filter(array_column($lines, 'display')))];
    }
}
