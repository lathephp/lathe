<?php

use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Resources\OrderItem;
use Workbench\App\Models\Order;

function orderTwelve(): Order
{
    return new Order()->forceFill(['id' => 12, 'number' => 'ORD-0012']);
}

it('names an OrderItem Resource order-items and Order Items', function () {
    expect(OrderItem::uriKey())->toBe('order-items')
        ->and(OrderItem::label())->toBe('Order Items');
});

it('titles a row by its id and gives it no subtitle by default', function () {
    $item = OrderItem::make()->forModel(orderTwelve());

    expect($item->title())->toBe('12')
        ->and($item->subtitle())->toBe('');
});

it('titles a row with the Closure passed to title()', function () {
    $titled = new class extends OrderItem
    {
        protected function configure(ResourceDefinition $definition): ResourceDefinition
        {
            return parent::configure($definition)->title(fn (Order $order) => 'Order '.$order->number);
        }
    };

    expect($titled->forModel(orderTwelve())->title())->toBe('Order ORD-0012');
});
