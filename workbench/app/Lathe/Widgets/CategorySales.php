<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PieWidget;
use Workbench\App\Models\Category;
use Workbench\App\Models\OrderItem;

/**
 * The item sales in dollars of each product category, over the orders placed in the range.
 */
class CategorySales extends PieWidget
{
    // Five bars and a row for the rest need no more height than they take.
    protected string $height = 'dynamic';

    public function name(): string
    {
        return 'Item sales by category';
    }

    public function calculate(LatheRequest $request): mixed
    {
        /** @var array<int|string, string> $names */
        $names = Category::pluck('name', 'id')->all();
        /** @var Builder<Model> $items */
        $items = OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.placed_at', '>=', now()->subDays((int) $request->query('range', '30')));
        $this->cacheTags(['orders', 'products', 'categories']);

        return $this->sum($request, $items, DB::raw('order_items.price_cents * order_items.quantity / 100.0'), 'products.category_id')
            ->label(fn (string $id) => $names[$id] ?? $id)
            ->dollars()
            ->format('.2');
    }
}
