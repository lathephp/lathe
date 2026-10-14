<?php

namespace Lathe\Menu;

use Illuminate\Http\Request;
use JsonSerializable;
use Lathe\Enums\Icon;

/**
 * A labelled group of sidebar links, inside the menu or a section.
 *
 * @phpstan-consistent-constructor
 */
class MenuGroup implements JsonSerializable
{
    use HasNavigationBadge;
    use MenuEntry;

    protected ?Icon $icon = null;

    protected bool $collapsible = false;

    protected bool $collapsedByDefault = false;

    /**
     * @param  list<MenuItem>  $items
     */
    public function __construct(protected string $label, protected array $items = [])
    {
        //
    }

    /**
     * @param  list<MenuItem>  $items
     */
    public static function group(string $name, array $items = []): static
    {
        return new static($name, $items);
    }

    public function item(MenuItem $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @return list<MenuItem>
     */
    public function entries(): array
    {
        return $this->items;
    }

    public function icon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * Start collapsed. A group that starts collapsed is collapsible.
     */
    public function collapsedByDefault(bool $collapsed = true): static
    {
        $this->collapsedByDefault = $collapsed;
        $this->collapsible = $this->collapsible || $collapsed;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray(request());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $json = [
            'kind' => 'group',
            'label' => $this->label,
            'icon' => $this->icon?->name,
            'items' => Menu::arrange($this->items, $request),
        ];

        if ($this->collapsible) {
            $json['collapsible'] = true;
        }

        if ($this->collapsedByDefault) {
            $json['collapsedByDefault'] = true;
        }

        return [...$json, ...$this->badgePayload()];
    }
}
