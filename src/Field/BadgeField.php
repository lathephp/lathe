<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * A value drawn as a coloured pill. map() sends a value to a badge type: info, success,
 * warning, danger, or a type named in types(), which the host styles through the
 * data-lathe-badge-type attribute. A value no map entry names has the type info.
 */
class BadgeField extends Field
{
    /** The icon withIcons() gives each built-in type. */
    public const array ICONS = ['success' => 'circle-check', 'info' => 'info', 'danger' => 'circle-alert', 'warning' => 'triangle-alert'];

    protected string $component = 'badge-field';

    /** @var array<array-key, mixed> */
    protected array $map = [];

    /** @var list<string> */
    protected array $types = [];

    /** @var array<array-key, mixed> */
    protected array $labels = [];

    protected ?Closure $labelCallback = null;

    protected ?string $nullLabel = null;

    /** @var array<string, string>|null */
    protected ?array $icons = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->exceptOnForms();
    }

    /**
     * The first entry whose key is the value. The comparison is strict, so 0.4 does not pick
     * the key 0 and true does not pick the key 1; a numeric string still picks an integer key,
     * because PHP turns the key "1" into 1.
     *
     * @param  array<array-key, mixed>  $map
     */
    public static function pick(array $map, mixed $value): mixed
    {
        foreach ($map as $key => $result) {
            if ($key === $value || (is_int($key) && is_string($value) && (string) $key === $value)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $map  value to badge type; a BackedEnum case works as the type
     */
    public function map(array $map): static
    {
        $this->mutate();
        $this->map = $map;

        return $this;
    }

    /**
     * Declare custom type names that map() can target.
     *
     * @param  list<string>  $types
     */
    public function types(array $types): static
    {
        $this->mutate();
        $this->types = $types;

        return $this;
    }

    /**
     * Add types over the ones already set, where types() replaces them.
     *
     * @param  list<string>  $types
     */
    public function addTypes(array $types): static
    {
        $this->mutate();
        $this->types = array_values(array_unique([...$this->types, ...$types]));

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $labels  value to label
     */
    public function labels(array $labels): static
    {
        $this->mutate();
        $this->labels = $labels;

        return $this;
    }

    /**
     * The label from a Closure `(value, record)`. It wins over every other label source.
     * (Field::label() is the getter of the field's own name, so this one has another name.)
     */
    public function labelUsing(Closure $callback): static
    {
        $this->mutate();
        $this->labelCallback = $callback;

        return $this;
    }

    public function nullLabel(string $label): static
    {
        $this->mutate();
        $this->nullLabel = $label;

        return $this;
    }

    /**
     * The icon of each badge type, as withIcons() with a map.
     *
     * @param  array<string, string>  $icons
     */
    public function icons(array $icons): static
    {
        return $this->withIcons($icons);
    }

    /**
     * Draw an icon in front of the label. With no argument: success circle-check, info info,
     * danger circle-alert, warning triangle-alert.
     *
     * @param  array<string, string>|null  $icons  badge type to Lucide icon name
     */
    public function withIcons(?array $icons = null): static
    {
        $this->mutate();
        $this->icons = $icons ?? self::ICONS;

        return $this;
    }

    /**
     * @return array{value: mixed, display: string, type: string, icon?: string}
     */
    public function cell(Model $model): array
    {
        $cell = parent::cell($model);
        $value = $cell['value'];
        $type = $this->typeFor($value);
        $icon = $this->icons[$type] ?? null;

        return [
            ...$cell,
            'display' => $this->labelFor($value, $model, $cell['display']),
            'type' => $type,
            ...($icon === null ? [] : ['icon' => $icon]),
        ];
    }

    protected function typeFor(mixed $value): string
    {
        $type = DefaultLifecycle::wire(self::pick($this->map, $value));

        return is_string($type) ? $type : 'info';
    }

    private function labelFor(mixed $value, Model $model, string $display): string
    {
        if ($this->labelCallback !== null) {
            $label = $this->evaluate($this->labelCallback, ['record' => $model, 'value' => $value], [$value, $model]);

            return is_scalar($label) ? (string) $label : '';
        }

        if ($value === null) {
            return $this->nullLabel ?? '';
        }

        $mapped = self::pick($this->labels, $value);

        if (is_string($mapped)) {
            return $mapped;
        }

        $raw = $this->computed === null && $this->resolveCallback === null ? $this->lifecycle->extract($model, $this->attribute) : null;
        $enumLabel = $raw instanceof UnitEnum && method_exists($raw, 'label') ? $raw->label() : null;

        if (is_string($enumLabel)) {
            return $enumLabel;
        }

        return is_string($value) ? Str::headline($value) : $display;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...parent::jsonSerialize(), 'types' => $this->types];
    }
}
