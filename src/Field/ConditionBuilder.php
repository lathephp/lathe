<?php

namespace Lathe\Field;

/**
 * The second half of Condition::field('type')->eq('company').
 */
class ConditionBuilder
{
    public function __construct(protected string $attribute)
    {
        //
    }

    public function eq(mixed $value): Condition
    {
        return Condition::rule($this->attribute, 'eq', $value);
    }

    public function neq(mixed $value): Condition
    {
        return Condition::rule($this->attribute, 'neq', $value);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function in(array $values): Condition
    {
        return Condition::rule($this->attribute, 'in', $values);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function notIn(array $values): Condition
    {
        return Condition::rule($this->attribute, 'notIn', $values);
    }

    public function gt(int|float $value): Condition
    {
        return Condition::rule($this->attribute, 'gt', $value);
    }

    public function gte(int|float $value): Condition
    {
        return Condition::rule($this->attribute, 'gte', $value);
    }

    public function lt(int|float $value): Condition
    {
        return Condition::rule($this->attribute, 'lt', $value);
    }

    public function lte(int|float $value): Condition
    {
        return Condition::rule($this->attribute, 'lte', $value);
    }

    public function filled(): Condition
    {
        return Condition::rule($this->attribute, 'filled');
    }

    public function blank(): Condition
    {
        return Condition::rule($this->attribute, 'blank');
    }
}
