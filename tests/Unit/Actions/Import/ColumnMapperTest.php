<?php

use Lathe\Actions\Import\ColumnMapper;
use Lathe\Actions\Import\ImportColumn;

it('maps exact headers and a near miss by edit distance', function () {
    $columns = [ImportColumn::make('name'), ImportColumn::make('sku'), ImportColumn::make('price')];

    expect(ColumnMapper::autoMap(['Name', 'SKU', 'Prise'], $columns))
        ->toBe([0 => 'name', 1 => 'sku', 2 => 'price']);
});

it('matches a header on the label when the name differs', function () {
    $columns = [ImportColumn::make('sku')->label('Product Code')];

    expect(ColumnMapper::autoMap(['product code'], $columns))->toBe([0 => 'sku']);
});

it('never fuzzy-matches a header longer than 20 characters', function () {
    $columns = [ImportColumn::make('a_very_long_column_name_x')];

    expect(ColumnMapper::autoMap(['a_very_long_column_name_y'], $columns))->toBe([0 => null]);
});

it('uses each column once', function () {
    $columns = [ImportColumn::make('name')];

    expect(ColumnMapper::autoMap(['Name', 'Name'], $columns))->toBe([0 => 'name', 1 => null]);
});

it('never maps a column that ignores auto mapping', function () {
    $columns = [ImportColumn::make('name')->ignoreAutoMapping()];

    expect(ColumnMapper::autoMap(['Name'], $columns))->toBe([0 => null]);
});

it('leaves a header more than two edits away unmapped', function () {
    $columns = [ImportColumn::make('price')];

    expect(ColumnMapper::autoMap(['Cost'], $columns))->toBe([0 => null]);
});
