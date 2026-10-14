<?php

namespace Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Stringable;

/**
 * A card with rows of tabular data. The card sends at most 100 rows.
 */
abstract class TableWidget extends Widget
{
    public const MAX_ROWS = 100;

    protected ?string $component = 'table-widget';

    protected string $height = 'dynamic';

    protected ?string $emptyText = null;

    /**
     * @return list<array{key: string, label: string, align?: 'left'|'center'|'right'}>
     */
    abstract public function columns(): array;

    /**
     * @return list<array<string, mixed>|MetricTableRow>
     */
    abstract public function rows(LatheRequest $request): array;

    public function emptyText(string|Stringable $text): static
    {
        $this->emptyText = (string) $text;

        return $this;
    }

    public function calculate(LatheRequest $request): mixed
    {
        return [
            'columns' => array_map(function (array $column) {
                unset($column['sortable']);

                return $column;
            }, $this->columns()),
            'rows' => array_map(
                fn (array|MetricTableRow $row) => $row instanceof MetricTableRow ? $row->jsonSerialize() : $row,
                array_slice(array_values($this->rows($request)), 0, self::MAX_ROWS),
            ),
            'emptyText' => $this->emptyText ?? Lathe::__('No Results Found...'),
        ];
    }
}
