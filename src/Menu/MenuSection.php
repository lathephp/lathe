<?php

namespace Lathe\Menu;

use JsonSerializable;
use Lathe\Enums\Icon;

/**
 * A titled group of sidebar links.
 *
 * @phpstan-consistent-constructor
 */
class MenuSection implements JsonSerializable
{
    protected ?Icon $icon = null;

    /**
     * @param  list<MenuItem>  $items
     */
    public function __construct(protected ?string $label, protected array $items = [])
    {
        //
    }

    /**
     * @param  list<MenuItem>  $items
     */
    public static function section(?string $label, array $items = []): static
    {
        return new static($label, $items);
    }

    public function icon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function item(MenuItem $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @return array{label: string|null, icon: string|null, items: list<array{label: string, href: string, icon: string|null}>}
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon?->name,
            'items' => array_map(fn (MenuItem $item) => $item->jsonSerialize(), $this->items),
        ];
    }
}
