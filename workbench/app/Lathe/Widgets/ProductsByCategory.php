<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PieWidget;
use Workbench\App\Models\Category;
use Workbench\App\Models\Product;

/**
 * How many products each category holds.
 */
class ProductsByCategory extends PieWidget
{
    public function name(): string
    {
        return 'Products by category';
    }

    public function calculate(LatheRequest $request): mixed
    {
        /** @var array<int|string, string> $names */
        $names = Category::pluck('name', 'id')->all();

        return $this->count($request, Product::class, 'category_id')
            ->label(fn (string $id) => $names[$id] ?? $id);
    }
}
