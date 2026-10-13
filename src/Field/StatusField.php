<?php

namespace Lathe\Field;

use Lathe\Field\Contracts\FilterableField;

/**
 * A badge whose type follows the value: loading, failed, or success for anything else.
 * Enum values unwrap before the strict comparison, so 0.4 does not match 0 and true
 * does not match 1.
 */
class StatusField extends BadgeField implements FilterableField
{
    protected string $component = 'status-field';

    /** @var list<mixed> */
    protected array $loading = [];

    /** @var list<mixed> */
    protected array $failed = [];

    /**
     * @param  mixed  $values  one value or a list of values
     */
    public function loadingWhen(mixed $values): static
    {
        $this->mutate();
        $this->loading = self::unwrapped($values);

        return $this;
    }

    /**
     * @param  mixed  $values  one value or a list of values
     */
    public function failedWhen(mixed $values): static
    {
        $this->mutate();
        $this->failed = self::unwrapped($values);

        return $this;
    }

    protected function typeFor(mixed $value): string
    {
        return match (true) {
            in_array($value, $this->loading, true) => 'loading',
            in_array($value, $this->failed, true) => 'failed',
            default => 'success',
        };
    }

    /**
     * @return list<mixed>
     */
    private static function unwrapped(mixed $values): array
    {
        return array_map(DefaultLifecycle::wire(...), is_array($values) ? array_values($values) : [$values]);
    }
}
