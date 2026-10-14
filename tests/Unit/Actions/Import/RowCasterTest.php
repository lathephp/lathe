<?php

use Lathe\Actions\Import\ImportColumn;
use Lathe\Actions\Import\RowCaster;
use Lathe\Actions\Import\RowCastException;

function caster(): RowCaster
{
    return new RowCaster('UTC');
}

it('strips a BOM, trims and normalizes line endings', function () {
    $column = ImportColumn::make('note');

    expect(caster()->cast($column, "\u{FEFF}  a\r\nb\rc  "))->toBe("a\nb\nc");
});

it('turns an empty string into null on a nullable column only', function () {
    expect(caster()->cast(ImportColumn::make('a')->nullable(), '  '))->toBeNull()
        ->and(caster()->cast(ImportColumn::make('a'), '  '))->toBe('');
});

it('casts booleans', function (mixed $in, bool $out) {
    expect(caster()->cast(ImportColumn::make('a')->boolean(), $in))->toBe($out);
})->with([
    ['1', true], ['true', true], ['YES', true], ['On', true], ['y', true],
    ['0', false], ['no', false], ['off', false], ['maybe', false],
]);

it('casts numbers', function () {
    $column = ImportColumn::make('a')->numeric();

    expect(caster()->cast($column, '$1,234.50'))->toBe(1234.5)
        ->and(caster()->cast($column, 'abc'))->toBeNull()
        ->and(caster()->cast($column, '12.0'))->toBe(12)
        ->and(caster()->cast($column, 7))->toBe(7)
        ->and(caster()->cast(ImportColumn::make('a')->numeric(2), '1.23456'))->toBe(1.23);
});

it('reads a date in the given timezone and returns UTC', function () {
    $column = ImportColumn::make('a')->date();

    $date = (new RowCaster('Pacific/Kiritimati'))->cast($column, '2026-03-09 13:30:00');

    expect($date->format('Y-m-d H:i:s'))->toBe('2026-03-08 23:30:00')
        ->and($date->timezoneName)->toBe('UTC');
});

it('reads a date with an explicit format', function () {
    $column = ImportColumn::make('a')->date('d/m/Y H:i');

    expect(caster()->cast($column, '09/03/2026 13:30')->format('Y-m-d H:i'))->toBe('2026-03-09 13:30');
});

it('throws on a date that does not parse', function () {
    caster()->cast(ImportColumn::make('a')->date(), 'not a date');
})->throws(RowCastException::class, 'Invalid date');

it('runs castUsing last', function () {
    $column = ImportColumn::make('a')->numeric()->castUsing(fn ($v) => $v * 2);

    expect(caster()->cast($column, '5'))->toBe(10);
});

it('skips null values', function () {
    expect(caster()->cast(ImportColumn::make('a')->boolean(), null))->toBeNull();
});

it('casts only the keys present in the row', function () {
    $columns = ['a' => ImportColumn::make('a')->boolean(), 'b' => ImportColumn::make('b')->boolean()];

    expect(caster()->castRow($columns, ['a' => 'yes']))->toBe(['a' => true]);
});

it('reports the column declaration', function () {
    $column = ImportColumn::make('first_name')->rules('required', 'max:5');

    expect($column->jsonSerialize())->toBe(['name' => 'first_name', 'label' => 'First Name', 'required' => true])
        ->and(ImportColumn::make('x')->relationship('customer', 'email')->getRelationship())
        ->toBe(['name' => 'customer', 'resolveBy' => 'email']);
});
