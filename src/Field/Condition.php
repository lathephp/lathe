<?php

namespace Lathe\Field;

use JsonSerializable;

/**
 * A declarative rule over other form values. The browser evaluates it as staff type; the
 * server evaluates it again on save and never trusts what the browser decided.
 */
class Condition implements JsonSerializable
{
    /**
     * @param  'eq'|'neq'|'in'|'notIn'|'gt'|'gte'|'lt'|'lte'|'filled'|'blank'|'all'|'any'  $operator
     * @param  list<Condition>  $conditions
     */
    final protected function __construct(
        protected string $operator,
        protected ?string $field = null,
        protected mixed $value = null,
        protected array $conditions = [],
    ) {
        //
    }

    /**
     * Start a rule on the value of another field, by its attribute.
     */
    public static function field(string $attribute): ConditionBuilder
    {
        return new ConditionBuilder($attribute);
    }

    /**
     * @internal
     */
    public static function rule(string $field, string $operator, mixed $value = null): static
    {
        /** @var 'eq'|'neq'|'in'|'notIn'|'gt'|'gte'|'lt'|'lte'|'filled'|'blank' $operator */
        return new static($operator, $field, $value);
    }

    public static function allOf(Condition ...$conditions): static
    {
        return new static('all', conditions: array_values($conditions));
    }

    public static function anyOf(Condition ...$conditions): static
    {
        return new static('any', conditions: array_values($conditions));
    }

    /**
     * Whether the rule holds for these form values, keyed by attribute.
     *
     * @param  array<array-key, mixed>  $values
     */
    public function passes(array $values): bool
    {
        if ($this->operator === 'all') {
            return array_reduce($this->conditions, fn (bool $carry, Condition $condition) => $carry && $condition->passes($values), true);
        }

        if ($this->operator === 'any') {
            return array_reduce($this->conditions, fn (bool $carry, Condition $condition) => $carry || $condition->passes($values), false);
        }

        $actual = data_get($values, (string) $this->field);

        return match ($this->operator) {
            'eq' => self::same($actual, $this->value),
            'neq' => ! self::same($actual, $this->value),
            'in' => self::within($actual),
            'notIn' => ! self::within($actual),
            'gt', 'gte', 'lt', 'lte' => self::compare($actual),
            'filled' => ! self::isBlank($actual),
            'blank' => self::isBlank($actual),
        };
    }

    /**
     * Every attribute the rule reads.
     *
     * @return list<string>
     */
    public function attributes(): array
    {
        return $this->field !== null
            ? [$this->field]
            : array_values(array_unique(array_merge(...array_map(fn (Condition $condition) => $condition->attributes(), $this->conditions))));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return in_array($this->operator, ['all', 'any'], true)
            ? ['op' => $this->operator, 'conditions' => $this->conditions]
            : ['op' => $this->operator, 'field' => $this->field, 'value' => $this->value];
    }

    /** Form values arrive as strings, so a rule compares their text: 1 equals "1", 0.4 never equals 0. */
    protected static function same(mixed $actual, mixed $expected): bool
    {
        return self::text($actual) === self::text($expected);
    }

    protected function within(mixed $actual): bool
    {
        $options = is_array($this->value) ? array_map(self::text(...), $this->value) : [];

        return in_array(self::text($actual), $options, true);
    }

    protected function compare(mixed $actual): bool
    {
        if (! is_numeric($actual) || ! is_numeric($this->value)) {
            return false;
        }

        return match ($this->operator) {
            'gt' => $actual > $this->value,
            'gte' => $actual >= $this->value,
            'lt' => $actual < $this->value,
            default => $actual <= $this->value,
        };
    }

    protected static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    protected static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof \BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
