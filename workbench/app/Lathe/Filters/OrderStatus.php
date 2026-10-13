<?php

namespace Workbench\App\Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;

class OrderStatus extends Filter
{
    public function name(): string
    {
        return 'Status';
    }

    public function key(): string
    {
        return 'status';
    }

    public function options(LatheRequest $request): array
    {
        return ['Pending' => 'pending', 'Paid' => 'paid', 'Shipped' => 'shipped', 'Cancelled' => 'cancelled'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($query->qualifyColumn('status'), $value);
    }
}
