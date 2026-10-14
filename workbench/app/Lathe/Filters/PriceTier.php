<?php

namespace Workbench\App\Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;

/**
 * Products by price band, drawn by the workbench-price-tier component.
 */
class PriceTier extends Filter
{
    public function options(LatheRequest $request): array
    {
        return ['Budget' => 'budget', 'Standard' => 'standard', 'Premium' => 'premium'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        $price = $query->qualifyColumn('price_cents');

        return match ($value) {
            'budget' => $query->where($price, '<', 5000),
            'standard' => $query->whereBetween($price, [5000, 19999]),
            default => $query->where($price, '>=', 20000),
        };
    }

    public function component(): string
    {
        return 'workbench-price-tier';
    }
}
