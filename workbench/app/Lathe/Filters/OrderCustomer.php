<?php

namespace Workbench\App\Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Search\LikeSearch;
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

    public function searchOptions(LatheRequest $request, string $term, int $page, int $perPage): array
    {
        // The panel's own prefix match: escaped, and case-insensitive on PostgreSQL too.
        $customers = Customer::where(function (Builder $query) use ($term) {
            /** @var Builder<Model> $query */
            LikeSearch::prefix('name')->apply($query, $term, 'and');
        })
            ->orderBy('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['id', 'name']);

        return array_values($customers
            ->map(fn (Customer $customer) => ['label' => $customer->name, 'value' => $customer->id])
            ->all());
    }

    public function labels(LatheRequest $request, array $values): array
    {
        $labels = [];

        foreach (Customer::whereKey($values)->get(['id', 'name']) as $customer) {
            $labels[(string) $customer->id] = $customer->name;
        }

        return $labels;
    }

    // customer_id is a bigint: PostgreSQL answers 500 for "abc" or a number past its range.
    protected function acceptsRemoteValue(string $value): bool
    {
        return ctype_digit($value) && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($query->qualifyColumn('customer_id'), $value);
    }
}
