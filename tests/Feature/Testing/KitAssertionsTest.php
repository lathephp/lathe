<?php

use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Testing\ActionAssertions;
use Lathe\Testing\ResourceAssertions;
use PHPUnit\Framework\AssertionFailedError;
use Workbench\App\Lathe\Actions\MarkShipped;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Lathe\Resources\Product;

uses(ResourceAssertions::class, ActionAssertions::class);

it('passes the resource assertions on a matching Resource', function () {
    $this->assertResourceExists(Order::class);
    $this->assertResourceFieldCount(Order::class, 8);
    $this->assertResourceHasField(Order::class, 'total_cents');
    $this->assertResourceSearchable(Product::class, ['name', 'sku']);
});

it('fails the resource assertions on a mismatch', function (string $assertion, array $arguments) {
    expect(fn () => $this->{$assertion}(...$arguments))->toThrow(AssertionFailedError::class);
})->with([
    'not a Resource' => ['assertResourceExists', [Field::class]],
    'field count' => ['assertResourceFieldCount', [Order::class, 7]],
    'missing field' => ['assertResourceHasField', [Order::class, 'shipped_at']],
    'searchable columns' => ['assertResourceSearchable', [Product::class, ['name']]],
]);

it('passes the action assertions on a matching action and result', function () {
    $this->assertActionExists(MarkShipped::class);
    $this->assertActionResultType(ActionResult::redirect('/lathe/resources/orders'), 'redirect');
    $this->assertActionResultType(ActionResult::danger('Out of stock'), 'danger');
    $this->assertActionResultType(ActionResult::deleted(), 'deleted');
    $this->assertActionSuccess(ActionResult::message('Shipped'));
});

it('fails the action assertions on a mismatch', function (string $assertion, array $arguments) {
    expect(fn () => $this->{$assertion}(...$arguments))->toThrow(AssertionFailedError::class);
})->with([
    'not an action' => ['assertActionExists', [Order::class]],
    'other effect' => ['assertActionResultType', [ActionResult::message('Shipped'), 'redirect']],
    'danger is no message' => ['assertActionResultType', [ActionResult::danger('Out of stock'), 'message']],
    'danger is no success' => ['assertActionSuccess', [ActionResult::danger('Out of stock')]],
]);
