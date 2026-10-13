<?php

namespace Lathe\Field;

use Closure;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Http\Requests\LatheRequest;

/**
 * A yes or no, stored as trueValue and falseValue. A nullable boolean also keeps null.
 */
class BooleanField extends Field implements FilterableField
{
    protected string $component = 'boolean-field';

    protected mixed $trueValue = true;

    protected mixed $falseValue = false;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->default = false;
        $this->options['asCheckbox'] = false;
        $this->lifecycle = $this->valueLifecycle();
    }

    public function trueValue(mixed $value): static
    {
        $this->mutate();
        $this->trueValue = $value;

        return $this;
    }

    public function falseValue(mixed $value): static
    {
        $this->mutate();
        $this->falseValue = $value;

        return $this;
    }

    /**
     * Both stored values at once, as trueValue() and falseValue().
     */
    public function values(mixed $true, mixed $false): static
    {
        return $this->trueValue($true)->falseValue($false);
    }

    /**
     * Draw a checkbox instead of a switch.
     */
    public function asCheckbox(bool $checkbox = true): static
    {
        return $this->option('asCheckbox', $checkbox);
    }

    /**
     * The stored value is on when it equals trueValue; a nullable field keeps null.
     */
    private function valueLifecycle(): FieldLifecycle
    {
        $state = fn (): array => [$this->trueValue, $this->falseValue, $this->isNullable()];

        return new class($state) extends DefaultLifecycle
        {
            /**
             * @param  Closure(): array{mixed, mixed, bool}  $state  the true value, the false value and whether the field is nullable
             */
            public function __construct(private readonly Closure $state) {}

            public function present(mixed $raw, object $record, string $attribute): mixed
            {
                [$true, , $nullable] = ($this->state)();

                return $raw === null && $nullable ? null : $raw == $true;
            }

            public function parse(LatheRequest $request, string $attribute): mixed
            {
                $input = $request->input($attribute);

                return $input === null || $input === '' ? null : $request->boolean($attribute);
            }

            public function persist(object $model, string $attribute, mixed $value): ?Closure
            {
                [$true, $false, $nullable] = ($this->state)();

                return parent::persist($model, $attribute, $value === null ? ($nullable ? null : $false) : ($value ? $true : $false));
            }
        };
    }

    protected function supportsInline(): bool
    {
        return true;
    }
}
