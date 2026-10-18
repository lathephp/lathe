<?php

use Lathe\Preferences\ColumnChoice;

const ORDER_COLUMNS = ['number', 'customer', 'status', 'total_cents', 'placed_at'];

it('shows the code order when there is no choice', function () {
    expect(ColumnChoice::visible(ORDER_COLUMNS, null))->toBe(['number', 'customer', 'status', 'total_cents', 'placed_at']);
});

it('leaves out a hidden column', function () {
    $choice = ['order' => ORDER_COLUMNS, 'hidden' => ['customer'], 'pinned' => []];

    expect(ColumnChoice::visible(ORDER_COLUMNS, $choice))->toBe(['number', 'status', 'total_cents', 'placed_at']);
});

it('shows the columns in the chosen order', function () {
    $choice = ['order' => ['placed_at', 'total_cents', 'number', 'customer', 'status'], 'hidden' => [], 'pinned' => []];

    expect(ColumnChoice::visible(ORDER_COLUMNS, $choice))->toBe(['placed_at', 'total_cents', 'number', 'customer', 'status']);
});

it('keeps a pinned column pinned', function () {
    $choice = ['order' => ORDER_COLUMNS, 'hidden' => [], 'pinned' => ['total_cents']];

    expect(ColumnChoice::age(ORDER_COLUMNS, $choice)['pinned'])->toBe(['total_cents']);
});

it('drops a column removed from code', function () {
    $choice = ['order' => ['number', 'customer', 'status'], 'hidden' => [], 'pinned' => ['customer']];

    expect(ColumnChoice::age(['number', 'status'], $choice))->toBe(['order' => ['number', 'status'], 'hidden' => [], 'pinned' => []])
        ->and(ColumnChoice::visible(['number', 'status'], $choice))->toBe(['number', 'status']);
});

it('shows a column added in code at the end', function () {
    $choice = ['order' => ['status', 'number'], 'hidden' => [], 'pinned' => []];

    expect(ColumnChoice::visible(['number', 'status', 'placed_at'], $choice))->toBe(['status', 'number', 'placed_at']);
});

it('drops a hidden column that was later removed from code', function () {
    $choice = ['order' => ['number', 'status'], 'hidden' => ['customer', 'status'], 'pinned' => []];

    expect(ColumnChoice::age(['number', 'status'], $choice))->toBe(['order' => ['number', 'status'], 'hidden' => ['status'], 'pinned' => []]);
});

it('drops unknown keys, duplicates and items that are not strings', function () {
    $choice = ['order' => ['status', 'status', 'nope', 7, 'number'], 'hidden' => ['nope', 'customer', 'customer'], 'pinned' => ['total_cents', 'total_cents', 'nope']];

    expect(ColumnChoice::age(ORDER_COLUMNS, $choice))->toBe([
        'order' => ['status', 'number', 'total_cents', 'placed_at'],
        'hidden' => ['customer'],
        'pinned' => ['total_cents'],
    ]);
});
