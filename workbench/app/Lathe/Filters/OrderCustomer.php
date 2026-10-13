<?php

namespace Workbench\App\Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Models\Customer;

class OrderCustomer extends Filter
{
    // The store has a thousand customers or more, so the index never loads them: a remote picker.
    protected bool|Closure $searchable = true;

    public function name(): string
    {
        return 'Customer';
    }

    public function key(): string
    {
        return 'customer';
    }

    public function options(LatheRequest $request): array
    {
        return Customer::orderBy('name')->get(['id', 'name'])
            ->mapWithKeys(fn (Customer $customer) => [$customer->name => $customer->id])
            ->all();
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($query->qualifyColumn('customer_id'), $value);
    }
}
