<?php

namespace Lathe\Layout;

/**
 * Lays Sections, TabGroups and other Grids side by side.
 *
 * @phpstan-consistent-constructor
 */
class Grid
{
    protected int $columns;

    /** @var list<Section|TabGroup|Grid> */
    protected array $items = [];

    public function __construct(int $columns = 2)
    {
        $this->columns = (int) SectionPresenter::columns($columns, 'The grid columns');
    }

    public static function make(int $columns = 2): static
    {
        return new static($columns);
    }

    /**
     * @param  list<mixed>  $items  anything but a Section, TabGroup or Grid is ignored
     */
    public function schema(array $items): static
    {
        $this->items = array_values(array_filter(
            $items,
            fn (mixed $item) => $item instanceof Section || $item instanceof TabGroup || $item instanceof Grid,
        ));

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    /**
     * @return list<Section|TabGroup|Grid>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
