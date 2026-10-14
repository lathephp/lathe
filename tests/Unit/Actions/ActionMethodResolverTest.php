<?php

namespace Lathe\Tests\Unit\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionMethodResolver;
use Lathe\Actions\ActionResult;
use Throwable;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;

class ResolverAction extends Action
{
    public function handleForOrders(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::message('orders');
    }

    public function handleForOrder(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::message('order');
    }

    public function handleForCustomer(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::message('customer');
    }

    public function failedForOrders(Throwable $e, Order $order): void
    {
        //
    }

    public function failedForOrder(Throwable $e, Order $order): void
    {
        //
    }

    public function failedForCustomer(Throwable $e, Customer $customer): void
    {
        //
    }

    public function failed(Throwable $e, mixed $model): void
    {
        //
    }
}

class PlainResolverAction extends Action
{
    //
}

it('prefers the plural handler, then the singular, then handle', function () {
    $action = ResolverAction::make();

    expect(ActionMethodResolver::handlerFor($action, Order::class))->toBe('handleForOrders')
        ->and(ActionMethodResolver::handlerFor($action, Customer::class))->toBe('handleForCustomer')
        ->and(ActionMethodResolver::handlerFor($action, Product::class))->toBe('handle');
});

it('prefers the plural failed handler, then the singular, then failed', function () {
    $action = ResolverAction::make();

    expect(ActionMethodResolver::failedHandlerFor($action, Order::class))->toBe('failedForOrders')
        ->and(ActionMethodResolver::failedHandlerFor($action, Customer::class))->toBe('failedForCustomer')
        ->and(ActionMethodResolver::failedHandlerFor($action, Product::class))->toBe('failed')
        ->and(ActionMethodResolver::failedHandlerFor(PlainResolverAction::make(), Order::class))->toBeNull();
});
