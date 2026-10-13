<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Enums\Icon;

/**
 * A value drawn as a Lucide icon: map() picks the icon, colors() a color token (info, success,
 * warning, danger), and fallback() draws for a value the map does not name.
 */
class IconField extends Field
{
    protected string $component = 'icon-field';

    /** @var array<array-key, mixed> */
    protected array $map = [];

    /** @var array<array-key, mixed> */
    protected array $colors = [];

    protected Icon|string|null $fallback = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->exceptOnForms();
    }

    /**
     * @param  array<array-key, mixed>  $map  value to a Lucide icon name or an Icon case
     */
    public function map(array $map): static
    {
        $this->mutate();
        $this->map = $map;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $colors  value to a color token
     */
    public function colors(array $colors): static
    {
        $this->mutate();
        $this->colors = $colors;

        return $this;
    }

    public function fallback(Icon|string $icon): static
    {
        $this->mutate();
        $this->fallback = $icon;

        return $this;
    }

    /**
     * @return array{value: mixed, display: string, icon: string|null, color: string|null}
     */
    public function cell(Model $model): array
    {
        $cell = parent::cell($model);
        $icon = DefaultLifecycle::wire(BadgeField::pick($this->map, $cell['value']) ?? $this->fallback);
        $color = DefaultLifecycle::wire(BadgeField::pick($this->colors, $cell['value']));

        return [...$cell, 'icon' => is_string($icon) ? $icon : null, 'color' => is_string($color) ? $color : null];
    }
}
