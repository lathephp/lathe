<?php

namespace Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;

/**
 * The one reader of filter[key] values: every path that narrows rows by filters goes through it.
 */
class FilterApplicator
{
    /**
     * @param  list<Filter>|null  $filters  the filters to read in place of the Resource's, as a lens's
     */
    public function __construct(protected Resource $resource, protected ?array $filters = null)
    {
        //
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<array-key, mixed>  $filters
     * @return Builder<Model>
     */
    public function apply(LatheRequest $request, Builder $query, array $filters): Builder
    {
        foreach ($this->resolve($request, $filters) as [$filter, $value]) {
            $query = $filter->apply($request, $query, $value);
        }

        return $query;
    }

    /**
     * The checked values keyed by filter key; 422 for an unknown, hidden or malformed filter.
     *
     * @param  array<array-key, mixed>  $filters
     * @return array<string, mixed>
     */
    public function values(LatheRequest $request, array $filters): array
    {
        $values = [];

        foreach ($this->resolve($request, $filters) as [$filter, $value]) {
            $values[$filter->key()] = $value;
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $filters
     * @return list<array{0: Filter, 1: mixed}>
     */
    protected function resolve(LatheRequest $request, array $filters): array
    {
        $available = [];

        foreach ($this->filters ?? $this->resource->availableFilters($request) as $filter) {
            $available[$filter->key()] = $filter;
        }

        $threshold = $this->resource->definition()->getFilterRemoteThreshold();
        $resolved = [];

        foreach ($filters as $key => $value) {
            $filter = $available[(string) $key] ?? throw ValidationException::withMessages([
                'filter' => "The {$key} filter is not available.",
            ]);

            $resolved[] = [$filter, $filter->normalize($request, $value, $filter->isRemote($request, $threshold))];
        }

        return $resolved;
    }
}
