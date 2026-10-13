<?php

namespace Lathe\Layout;

use Illuminate\Support\Str;
use Lathe\Field\Field;

/**
 * Tabs that share one active-tab query key.
 *
 * @phpstan-consistent-constructor
 */
class TabGroup
{
    protected ?string $attribute;

    /** @var list<Tab> */
    protected array $tabs = [];

    protected int $activeTab = 0;

    protected ?string $queryKey = null;

    protected bool $vertical = false;

    protected bool $contained = false;

    protected ?int $columnSpan = null;

    public function __construct(protected ?string $label = null, Tab ...$tabs)
    {
        $this->attribute = $label === null ? null : Str::slug($label);
        $this->tabs = array_values($tabs);
    }

    public static function make(?string $label = null, Tab ...$tabs): static
    {
        return new static($label, ...$tabs);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Replaces the tabs. A Tab stays, a string key is a tab label over its items, a Section
     * becomes a tab of its fields and a Field becomes a tab of itself.
     *
     * @param  array<array-key, mixed>  $tabs
     */
    public function tabs(array $tabs): static
    {
        $this->tabs = [];

        foreach ($tabs as $key => $item) {
            $tab = match (true) {
                $item instanceof Tab => $item,
                is_string($key) && is_array($item) => Tab::make($key, array_values($item)),
                $item instanceof Section => Tab::make($item->getName(), $item->getFields())->columns($item->getColumns()),
                $item instanceof Field => Tab::make($item->label(), [$item]),
                default => null,
            };

            if ($tab !== null) {
                $this->tabs[] = $tab;
            }
        }

        return $this;
    }

    public function tab(Tab $tab): static
    {
        $this->tabs[] = $tab;

        return $this;
    }

    public function withAttribute(string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function activeTab(int $index): static
    {
        $this->activeTab = $index;

        return $this;
    }

    /**
     * Name the query key that holds the active tab.
     */
    public function persistTab(?string $queryKey = 'tab'): static
    {
        $this->queryKey = $queryKey;

        return $this;
    }

    public function vertical(bool $vertical = true): static
    {
        $this->vertical = $vertical;

        return $this;
    }

    public function contained(bool $contained = true): static
    {
        $this->contained = $contained;

        return $this;
    }

    public function columnSpan(?int $span): static
    {
        $this->columnSpan = SectionPresenter::columns($span, 'The tab group column span');

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getAttribute(): ?string
    {
        return $this->attribute;
    }

    /**
     * @return list<Tab>
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    public function getActiveTab(): int
    {
        return $this->activeTab;
    }

    public function getQueryKey(): ?string
    {
        return $this->queryKey;
    }

    public function isVertical(): bool
    {
        return $this->vertical;
    }

    public function isContained(): bool
    {
        return $this->contained;
    }

    public function getColumnSpan(): ?int
    {
        return $this->columnSpan;
    }
}
