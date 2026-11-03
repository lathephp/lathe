<?php

namespace Workbench\App\Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Filters\DateFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\Timezone;

/**
 * The days an order was placed on, cut at the user's local midnight as the dashboard cuts them.
 */
class OrderPlaced extends DateFilter
{
    public function name(): string
    {
        return 'Placed';
    }

    public function key(): string
    {
        return 'placed';
    }

    public function column(): ?string
    {
        return 'placed_at';
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array{from: string|null, to: string|null}  $value
     * @return Builder<Model>
     */
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        $timezone = app(Timezone::class);
        $zone = $timezone->forRequest($request);
        $column = $query->qualifyColumn('placed_at');

        if ($value['from'] !== null) {
            $query->where($column, '>=', $timezone->dayBounds($value['from'], $zone)[0]);
        }

        if ($value['to'] !== null) {
            $query->where($column, '<', $timezone->dayBounds($value['to'], $zone)[1]);
        }

        return $query;
    }
}
