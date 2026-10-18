<?php

use Lathe\Preferences\DashboardLayout;

const CODE_WIDGETS = ['orders' => '1/3', 'revenue' => '1/3', 'paid-orders' => '1/3'];

it('keeps the saved order, widths and hidden flags', function () {
    $saved = [
        ['uriKey' => 'revenue', 'width' => 'full', 'hidden' => false],
        ['uriKey' => 'orders', 'width' => '1/2', 'hidden' => true],
        ['uriKey' => 'paid-orders', 'width' => '3/4', 'hidden' => false],
    ];

    expect(DashboardLayout::age($saved, CODE_WIDGETS))->toBe($saved);
});

it('drops a widget removed from code and a repeated one', function () {
    $saved = [
        ['uriKey' => 'gone', 'width' => '1/2', 'hidden' => false],
        ['uriKey' => 'revenue', 'width' => '1/2', 'hidden' => false],
        ['uriKey' => 'revenue', 'width' => 'full', 'hidden' => true],
        ['uriKey' => 'orders', 'width' => '1/3', 'hidden' => false],
        ['uriKey' => 'paid-orders', 'width' => '1/3', 'hidden' => false],
    ];

    expect(array_column(DashboardLayout::age($saved, CODE_WIDGETS), 'uriKey'))->toBe(['revenue', 'orders', 'paid-orders'])
        ->and(DashboardLayout::age($saved, CODE_WIDGETS)[0])->toBe(['uriKey' => 'revenue', 'width' => '1/2', 'hidden' => false]);
});

it('adds a widget new in code at the end, shown, at its code width', function () {
    $saved = [['uriKey' => 'revenue', 'width' => '1/2', 'hidden' => true]];

    expect(DashboardLayout::age($saved, ['revenue' => '1/3', 'orders' => '2/3', 'paid-orders' => 'full']))->toBe([
        ['uriKey' => 'revenue', 'width' => '1/2', 'hidden' => true],
        ['uriKey' => 'orders', 'width' => '2/3', 'hidden' => false],
        ['uriKey' => 'paid-orders', 'width' => 'full', 'hidden' => false],
    ]);
});

it('keys a dashboard layout by the dashboard', function () {
    expect(DashboardLayout::key('main'))->toBe('dashboard:main');
});
