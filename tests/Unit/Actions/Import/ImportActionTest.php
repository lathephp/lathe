<?php

use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Workbench\App\Lathe\Resources\Product as ProductResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Product;

it('is a standalone, confirmable import that records no action events', function () {
    $action = ImportAction::make();

    expect($action->uriKey())->toBe('import')
        ->and($action->name())->toBe('Import')
        ->and($action->isStandalone())->toBeTrue()
        ->and($action->isConfirmable())->toBeTrue()
        ->and($action->recordsActionEvents())->toBeFalse()
        ->and($action->ability())->toBe('import');
});

it('takes columns as arguments or as an array', function () {
    $name = ImportColumn::make('name');
    $sku = ImportColumn::make('sku');

    expect(ImportAction::make()->withColumns($name, $sku)->getColumns())->toBe([$name, $sku])
        ->and(ImportAction::make()->withColumns([$name, $sku])->getColumns())->toBe([$name, $sku]);
});

it('imports into the Resource model unless a model is set', function () {
    expect(ImportAction::make()->forResource(ProductResource::class)->getModel())->toBe(Product::class)
        ->and(ImportAction::make()->forResource(ProductResource::class)->withModel(Customer::class)->getModel())->toBe(Customer::class);
});

it('keeps the key columns and the queue settings', function () {
    $action = ImportAction::make()->upsertBy('sku')->skipDuplicates('email')
        ->queueable('redis', 'imports', 3, 600, 250);

    expect($action->getUpsertBy())->toBe('sku')
        ->and($action->getSkipDuplicates())->toBe('email')
        ->and([$action->connection, $action->queue, $action->tries, $action->timeout, $action->getChunkSize()])
        ->toBe(['redis', 'imports', 3, 600, 250]);
});

it('runs a hook, and a hook that answers nothing keeps the row', function () {
    $action = ImportAction::make()
        ->beforeRow(fn (array $row, int $rowNumber) => [...$row, 'row' => $rowNumber])
        ->afterRow(fn (array $row) => null);

    expect($action->callHook('beforeRow', ['sku' => 'A'], 7))->toBe(['sku' => 'A', 'row' => 7])
        ->and($action->callHook('afterRow', ['sku' => 'A'], 7))->toBeNull()
        ->and($action->callHook('beforeSave', ['sku' => 'A'], 7))->toBeNull();
});

it('survives a serialize round trip with its closures', function () {
    $suffix = '-copy';
    $action = ImportAction::make()
        ->withColumns(ImportColumn::make('sku'))
        ->upsertBy('sku')
        ->beforeSave(fn (array $row) => ['sku' => $row['sku'].$suffix])
        ->canSee(fn () => true);

    $copy = unserialize(serialize($action));

    expect($copy)->toBeInstanceOf(ImportAction::class)
        ->and($copy->callHook('beforeSave', ['sku' => 'A'], 2))->toBe(['sku' => 'A-copy'])
        ->and($copy->getUpsertBy())->toBe('sku')
        ->and($copy->getColumns()[0]->getName())->toBe('sku');
});
