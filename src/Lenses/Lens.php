<?php

namespace Lathe\Lenses;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lathe\Attributes\Lens\PerPage;
use Lathe\Attributes\Lens\Polling;
use Lathe\Attributes\Lens\Search;
use Lathe\Attributes\Lens\ShowPollingToggle;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Search\SearchableColumn;
use ReflectionClass;

/**
 * A named alternate index of a Resource: its own query, columns, filters and sort,
 * read through the Resource's scoped query so it never shows a row the index hides.
 */
abstract class Lens
{
    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    /**
     * @return list<mixed>
     */
    abstract public function fields(LatheRequest $request): array;

    /**
     * Refine the Resource's scoped index query.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    abstract public static function query(LatheRequest $request, Builder $query): Builder;

    /**
     * The count in place of the one derived from query(): a query to count, a number, or null.
     *
     * @param  Builder<Model>  $query  the lens query with its filters and search applied
     * @return Builder<Model>|int|null
     */
    public static function countQuery(LatheRequest $request, Builder $query): Builder|int|null
    {
        return null;
    }

    /**
     * @return list<Filter>
     */
    public function filters(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The lens filters this request may see and apply.
     *
     * @return list<Filter>
     */
    public function availableFilters(LatheRequest $request): array
    {
        return array_values(array_filter($this->filters($request), fn (Filter $filter) => $filter->authorizedToSee($request)));
    }

    public function name(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public static function label(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public static function singularLabel(): string
    {
        return Str::singular(static::label());
    }

    public function uriKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public static function searchable(): bool
    {
        return static::attribute(Search::class) !== null;
    }

    /**
     * @return list<string|SearchableColumn>
     */
    public function searchableColumns(): array
    {
        return static::attribute(Search::class)->columns ?? [];
    }

    /**
     * @return list<int>|null null uses the Resource's options
     */
    public static function perPageOptions(): ?array
    {
        return static::attribute(PerPage::class)?->options;
    }

    public function polling(): bool
    {
        return static::attribute(Polling::class) !== null;
    }

    public function pollingInterval(): int
    {
        return static::attribute(Polling::class)->seconds ?? 15;
    }

    public function showPollingToggle(): bool
    {
        return static::attribute(ShowPollingToggle::class)->show ?? false;
    }

    /**
     * Apply the request's filters here instead of after query(), for a query() that wraps a subquery.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function withFilters(LatheRequest $request, Builder $query): Builder
    {
        $resourceQuery = $request->attributes->get(ResourceQuery::class);

        return $resourceQuery instanceof ResourceQuery ? $resourceQuery->applyLensFilters($query) : $query;
    }

    /**
     * Apply the request's sort here instead of after query(); $default orders an unsorted request.
     *
     * @param  Builder<Model>  $query
     * @param  (Closure(Builder<Model>): Builder<Model>)|null  $default
     * @return Builder<Model>
     */
    public static function withOrdering(LatheRequest $request, Builder $query, ?Closure $default = null): Builder
    {
        $resourceQuery = $request->attributes->get(ResourceQuery::class);

        return $resourceQuery instanceof ResourceQuery ? $resourceQuery->applyLensOrdering($query, $default) : $query;
    }

    /**
     * Hide the lens from requests the callback refuses; every lens endpoint then answers 403.
     *
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        $this->seeCallback = $callback;

        return $this;
    }

    public function authorizedToSee(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    /**
     * The lens attribute of the class or its nearest parent. Reflection reads it on each
     * call, since a static cache would outlive the request in a long-lived worker.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    protected static function attribute(string $attribute): ?object
    {
        for ($reflection = new ReflectionClass(static::class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $read = $reflection->getAttributes($attribute)[0] ?? null;

            if ($read !== null) {
                return $read->newInstance();
            }
        }

        return null;
    }
}
