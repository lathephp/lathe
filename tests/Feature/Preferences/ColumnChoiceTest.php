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
    $choice = ['order' => ['number', 'placed_at', 'total_cents', 'customer', 'status'], 'hidden' => [], 'pinned' => []];

    expect(ColumnChoice::visible(ORDER_COLUMNS, $choice))->toBe(['number', 'placed_at', 'total_cents', 'customer', 'status']);
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
    $choice = ['order' => ['number', 'status'], 'hidden' => [], 'pinned' => []];

    expect(ColumnChoice::visible(['number', 'customer', 'status', 'placed_at'], $choice))->toBe(['number', 'status', 'customer', 'placed_at']);
});

it('drops a hidden column that was later removed from code', function () {
    $choice = ['order' => ['number', 'status'], 'hidden' => ['customer', 'status'], 'pinned' => []];

    expect(ColumnChoice::age(['number', 'status'], $choice))->toBe(['order' => ['number', 'status'], 'hidden' => ['status'], 'pinned' => []]);
});

it('drops unknown keys, duplicates and items that are not strings', function () {
    $choice = ['order' => ['number', 'status', 'status', 'nope', 7], 'hidden' => ['nope', 'customer', 'customer'], 'pinned' => ['total_cents', 'total_cents', 'nope']];

    expect(ColumnChoice::age(ORDER_COLUMNS, $choice))->toBe([
        'order' => ['number', 'status', 'customer', 'total_cents', 'placed_at'],
        'hidden' => ['customer'],
        'pinned' => ['total_cents'],
    ]);
});

it('keeps a hidden column in the order, so the picker can show it again', function () {
    $aged = ColumnChoice::age(ORDER_COLUMNS, ['order' => [], 'hidden' => ['customer'], 'pinned' => []]);

    expect($aged['order'])->toBe(ORDER_COLUMNS)
        ->and(ColumnChoice::visible(ORDER_COLUMNS, $aged))->toBe(['number', 'status', 'total_cents', 'placed_at']);
});

it('keeps the title column shown and first, whatever a request asks', function () {
    $aged = ColumnChoice::age(ORDER_COLUMNS, ['order' => ['status', 'number'], 'hidden' => ORDER_COLUMNS, 'pinned' => []]);

    expect($aged['order'][0])->toBe('number')
        ->and($aged['hidden'])->toBe(['customer', 'status', 'total_cents', 'placed_at'])
        ->and(ColumnChoice::visible(ORDER_COLUMNS, $aged))->toBe(['number']);
});
