<?php

namespace Lathe\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Support\ConfigStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The one way the panel reads a Resource's rows, so scoping and soft deletes
 * behave the same on every path.
 */
class ResourceQuery
{
    protected ?string $trashed = null;

    /** @var list<string> */
    protected array $withoutScopes = [];

    /** @var Builder<Model>|null */
    protected ?Builder $scoped = null;

    protected bool $hasMorePages = false;

    /** @var array{0: int, 1: int}|null */
    protected ?array $pagination = null;

    public function __construct(
        protected Resource $resource,
        protected LatheRequest $request,
        protected QueryContext $context = QueryContext::Index,
    ) {
        //
    }

    public function withTrashed(): static
    {
        return $this->change(fn () => $this->trashed = 'with');
    }

    public function onlyTrashed(): static
    {
        return $this->change(fn () => $this->trashed = 'only');
    }

    /**
     * Lift one named global scope for this query only.
     */
    public function withoutScope(string $globalScope): static
    {
        return $this->change(fn () => $this->withoutScopes[] = $globalScope);
    }

    /**
     * The model query with global scopes, the trashed scope and the context hook.
     *
     * @return Builder<Model>
     */
    public function scoped(): Builder
    {
        if ($this->scoped === null) {
            $query = $this->resource::query();

            foreach ($this->withoutScopes as $scope) {
                $query->withoutGlobalScope($scope);
            }

            $query = $this->applyHook($this->applyTrashed($query));
            $this->scoped = $this->applyFilters($this->applySearch($this->applySavedTab($this->applyTab($query))));
        }

        return clone $this->scoped;
    }

    /**
     * One page of rows, newest first, with the Resource's eager loads.
     *
     * @return Collection<int, Model>
     */
    public function rows(): Collection
    {
        [$perPage, $page] = $this->pagination();
        $query = $this->scoped();

        // One row past the page tells whether a next page exists without waiting for the count.
        $rows = $this->applySort($query->with($this->resource->with()))
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $this->hasMorePages = $rows->count() > $perPage;

        return $rows->take($perPage);
    }

    /**
     * Whether the last rows() call found a row past its page.
     */
    public function hasMorePages(): bool
    {
        return $this->hasMorePages;
    }

    /**
     * The page size and page number this query reads.
     *
     * @return array{0: int, 1: int}
     */
    public function page(): array
    {
        return $this->pagination();
    }

    /**
     * Check every request parameter without reading a row: 422 for a bad one.
     */
    public function validate(): void
    {
        $this->pagination();
        $this->scoped();
    }

    /**
     * @return non-empty-list<int>
     */
    public function perPageOptionsInUse(): array
    {
        $options = $this->resource->definition()->getPerPage();

        return $options !== null && $options !== [] ? $options : $this->perPageOptions();
    }

    /**
     * The row count in the Resource's count mode, or null when it counts nothing.
     */
    public function count(): ?Count
    {
        $definition = $this->resource->definition();

        return match ($definition->getCountStrategy()) {
            CountStrategy::None => null,
            CountStrategy::Exact => new Count($this->scoped()->toBase()->count(), capped: false, exact: true),
            CountStrategy::Estimated => $this->estimatedCount() ?? $this->cappedCount($definition->getCountCap()),
            CountStrategy::Capped => $this->cappedCount($definition->getCountCap()),
        };
    }

    /**
     * One query limited to the cap plus one, so a huge table is never counted in full.
     */
    protected function cappedCount(int $cap): Count
    {
        $query = $this->scoped()->toBase()->limit($cap + 1);

        $count = DB::connection($this->scoped()->getModel()->getConnectionName())
            ->query()
            ->fromSub($query, 'lathe_count')
            ->count();

        return new Count(min($count, $cap), $count > $cap);
    }

    /**
     * The database's row estimate for the table, only while nothing narrows the query.
     * SQLite keeps no estimate.
     */
    protected function estimatedCount(): ?Count
    {
        $query = $this->scoped()->toBase();

        if ($query->wheres !== [] || $query->joins !== null || $query->groups !== null || $query->havings !== null || $query->unions !== null) {
            return null;
        }

        $model = $this->scoped()->getModel();
        $connection = $model->getConnection();
        $table = $connection->getTablePrefix().$model->getTable();

        $estimate = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $connection->scalar(
                'select table_rows from information_schema.tables where table_schema = database() and table_name = ?',
                [$table],
            ),
            'pgsql' => $connection->scalar('select reltuples::bigint from pg_class where oid = to_regclass(?)', [$table]),
            default => null,
        };

        // PostgreSQL answers -1 for a table it has not analysed yet.
        return is_numeric($estimate) ? new Count(max(0, (int) $estimate), capped: false, estimated: true) : null;
    }

    /**
     * One record, or a 404 when it is outside the scope.
     */
    public function find(string|int $id): Model
    {
        try {
            return $this->scoped()->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }

    /**
     * The page size and page number, checked against the Resource's options and the count cap.
     *
     * @return array{0: int, 1: int}
     */
    protected function pagination(): array
    {
        return $this->pagination ??= $this->readPagination();
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function readPagination(): array
    {
        $perPage = $this->request->query('per_page');
        $page = $this->request->query('page');
        $options = $this->perPageOptionsInUse();

        // A whole number outside the options falls back below; anything else is 422.
        if ($perPage !== null && (! is_string($perPage) || preg_match('/^-?\d+$/', $perPage) !== 1)) {
            throw ValidationException::withMessages(['per_page' => 'The page size must be a whole number.']);
        }

        if ($page !== null && (! is_string($page) || ! ctype_digit($page) || (int) $page < 1)) {
            throw ValidationException::withMessages(['page' => 'The page must be a whole number of 1 or more.']);
        }

        $perPage = in_array((int) $perPage, $options, true) ? (int) $perPage : $options[0];
        $page = $page === null ? 1 : (int) $page;

        if (($page - 1) * $perPage >= $this->resource->definition()->getCountCap()) {
            throw ValidationException::withMessages(['page' => 'Narrow the filters']);
        }

        return [$perPage, $page];
    }

    /**
     * @return non-empty-list<int>
     */
    protected function perPageOptions(): array
    {
        $options = array_values(array_filter(
            (array) $this->config()->get('pagination.per_page_options', []),
            'is_int',
        ));

        return $options === [] ? [$this->config()->integer('pagination.per_page')] : $options;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyTrashed(Builder $query): Builder
    {
        if (! $this->resource->definition()->usesSoftDeletes()) {
            return $query;
        }

        $model = $query->getModel();
        $column = method_exists($model, 'getQualifiedDeletedAtColumn') ? $model->getQualifiedDeletedAtColumn() : null;
        $column = is_string($column) ? $column : $model->qualifyColumn('deleted_at');

        return match ($this->trashed ?? $this->request->trashed()) {
            'with' => $query->withoutGlobalScope(SoftDeletingScope::class),
            'only' => $query->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull($column),
            default => $query,
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyHook(Builder $query): Builder
    {
        $resource = $this->resource;

        return match ($this->context) {
            QueryContext::Index => $resource::indexQuery($this->request, $query),
            QueryContext::Detail => $resource::detailQuery($this->request, $query),
            QueryContext::Edit => $resource::editQuery($this->request, $query),
            QueryContext::Search => $resource::searchQuery($this->request, $query),
            QueryContext::Widget => $resource::widgetQuery($this->request, $query),
            QueryContext::Relatable => $resource::relatableQuery($this->request, $query),
            QueryContext::Replicate => $resource::replicateQuery($this->request, $query),
        };
    }

    /**
     * The active developer tab's query change.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyTab(Builder $query): Builder
    {
        return $query;
    }

    /**
     * The active saved tab's stored filters and search, applied before the user's own.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySavedTab(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyFilters(Builder $query): Builder
    {
        return $query;
    }

    /**
     * The group order, the requested sort, then the primary key as the tie-break.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySort(Builder $query): Builder
    {
        return $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }

    /**
     * @param  \Closure(): mixed  $change
     */
    protected function change(\Closure $change): static
    {
        $change();
        $this->scoped = null;

        return $this;
    }

    protected function config(): ConfigStore
    {
        return app(ConfigStore::class);
    }
}
