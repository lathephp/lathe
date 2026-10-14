<?php

namespace Lathe\Menu;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;
use Lathe\Enums\Icon;
use Lathe\Support\ConfigStore;

/**
 * A titled group of sidebar links.
 *
 * @phpstan-consistent-constructor
 */
class MenuSection implements JsonSerializable
{
    use HasNavigationBadge;
    use MenuEntry;

    protected ?Icon $icon = null;

    protected ?string $path = null;

    protected bool $collapsible = false;

    protected bool $collapsedByDefault = false;

    /**
     * @param  list<MenuItem|MenuGroup>  $items
     */
    public function __construct(protected ?string $label, protected array $items = [])
    {
        //
    }

    /**
     * @param  list<MenuItem|MenuGroup>  $items
     */
    public static function section(?string $label, array $items = []): static
    {
        return new static($label, $items);
    }

    /**
     * A section titled after a Resource key, whose title links to the Resource.
     */
    public static function resource(string $resource): static
    {
        return (new static(Str::headline($resource)))->path("resources/{$resource}");
    }

    /**
     * A section titled after a page key, whose title links to the page.
     */
    public static function page(string $page): static
    {
        return (new static(Str::headline($page)))->path("pages/{$page}");
    }

    public function icon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Link the section title to a path under the panel.
     */
    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function item(MenuItem|MenuGroup $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * Start collapsed. A section that starts collapsed is collapsible.
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
            'label' => $this->label,
            'icon' => $this->icon?->name,
            'items' => Menu::arrange($this->items, $request),
        ];

        // The keys after items appear only when set, so a plain section keeps its old shape.
        if ($this->path !== null) {
            $base = '/'.trim(app(ConfigStore::class)->string('path'), '/');
            $json['href'] = $base.'/'.trim($this->path, '/');
        }

        if ($this->collapsible) {
            $json['collapsible'] = true;
        }

        if ($this->collapsedByDefault) {
            $json['collapsedByDefault'] = true;
        }

        return [...$json, ...$this->badgePayload()];
    }
}
