<?php

namespace Lathe\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Field\Contracts\Cover;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Filters\FilterApplicator;
use Lathe\Groups\Group;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;
use Lathe\Resources\Resource;
use Lathe\SavedTabs\SavedTab;
use Lathe\SavedTabs\SavedTabQueryApplicator;
use Lathe\Search\LikeSearch;
use Lathe\Search\SearchableColumn;
use Lathe\Search\SearchablePrimaryKey;
use Lathe\Search\SearchApplicator;
use Lathe\Support\ConfigStore;
use Lathe\Support\Timezone;
use Lathe\Tabs\Tab;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The one way the panel reads a Resource's rows, so scoping and soft deletes
 * behave the same on every path.
 */
class ResourceQuery
{
    /** The ?group= value that turns the Resource's default group off. */
    public const string UNGROUPED = '_none';

    protected ?string $trashed = null;

    /** @var list<string> */
    protected array $withoutScopes = [];

    /** @var Builder<Model>|null */
    protected ?Builder $scoped = null;

    protected bool $hasMorePages = false;

    /** @var array{0: int, 1: int}|null */
    protected ?array $pagination = null;

    protected ?Lens $lens = null;

    /** Whether the lens's query() applied the filters or the sort itself, so the pipeline skips them. */
    protected bool $lensFiltered = false;

    protected bool $lensOrdered = false;

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
     * Read the rows through a lens: the Resource's scoped index query refined by the lens,
     * with the lens's own filters, search and sortable fields in place of the Resource's.
     */
    public function forLens(Lens $lens): static
    {
        return $this->change(fn () => $this->lens = $lens);
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

            // Tabs, filters and search are the index's view; a record lookup ignores them.
            $this->scoped = $this->lens !== null ? $this->applyLens($query) : match ($this->context) {
                QueryContext::Index => $this->applyFilters($this->applySearch($this->applySavedTab($this->applyTab($query)))),
                QueryContext::Search => $this->applySearch($query),
                default => $query,
            };
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
     * At most $limit rows for global search. When the title and subtitle are plain
     * attributes, only the key, title and subtitle columns are read.
     *
     * @return Collection<int, Model>
     */
    public function searchResults(int $limit): Collection
    {
        $query = $this->scoped();
        $definition = $this->resource->definition();
        $title = $definition->getTitle();
        $subtitle = $definition->getSubtitle();

        if (is_string($title) && is_string($subtitle)) {
            $columns = array_unique(array_filter([$query->getModel()->getKeyName(), $title, $subtitle, $this->coverColumn()]));
            $query->select(array_map(fn (string $column) => $query->qualifyColumn($column), array_values($columns)));
        }

        // A stable order, so the same term lists the same rows on every database.
        return $query->orderBy($query->getModel()->getQualifiedKeyName())->limit($limit)->get();
    }

    /**
     * The column the Resource's avatar reads, so a search row can carry its image.
     */
    protected function coverColumn(): ?string
    {
        $cover = $this->resource->fieldsFor(FieldContext::Detail, $this->request)->first(fn (Field $field) => $field instanceof Cover);

        return $cover !== null && ! $cover->isComputed() && ! str_contains($cover->attribute(), '.') ? $cover->attribute() : null;
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
        $this->activeGroup();
        $this->applySort($this->scoped());
    }

    /**
     * @return non-empty-list<int>
     */
    public function perPageOptionsInUse(): array
    {
        $options = $this->lens?->perPageOptions() ?? $this->resource->definition()->getPerPage();

        return $options !== null && $options !== [] ? $options : $this->perPageOptions();
    }

    /**
     * The row count in the Resource's count mode, or null when it counts nothing.
     */
    public function count(): ?Count
    {
        $definition = $this->resource->definition();
        $counted = $this->lens !== null ? $this->lens::countQuery($this->request, $this->scoped()) : null;

        if (is_int($counted)) {
            return $definition->getCountStrategy() === CountStrategy::None ? null : new Count($counted, capped: false, exact: true);
        }

        $counted ??= $this->scoped();

        return match ($definition->getCountStrategy()) {
            CountStrategy::None => null,
            CountStrategy::Exact => new Count($counted->toBase()->count(), capped: false, exact: true),
            CountStrategy::Estimated => $this->estimatedCount() ?? $this->cappedCount($definition->getCountCap(), $counted),
            CountStrategy::Capped => $this->cappedCount($definition->getCountCap(), $counted),
        };
    }

    /**
     * One query limited to the cap plus one, so a huge table is never counted in full.
     *
     * @param  Builder<Model>|null  $counted
     */
    protected function cappedCount(int $cap, ?Builder $counted = null): Count
    {
        $counted ??= $this->scoped();
        $query = $counted->toBase()->limit($cap + 1);

        $count = DB::connection($counted->getModel()->getConnectionName())
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
        if (! static::holdsKey($this->resource::newModel(), $id)) {
            throw new NotFoundHttpException;
        }

        try {
            return $this->scoped()->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }

    /**
     * Whether the id can be a key of the model. PostgreSQL answers 500 for "abc" against a
     * bigint or uuid column, so a caller checks this and answers 404 before any query.
     */
    public static function holdsKey(Model $model, string|int $id): bool
    {
        $id = (string) $id;

        if (in_array($model->getKeyType(), ['int', 'integer'], true)) {
            // filter_var() bounds the digits at PHP_INT_MAX but refuses a leading zero.
            return ctype_digit($id) && filter_var(ltrim($id, '0') ?: '0', FILTER_VALIDATE_INT) !== false;
        }

        if ($model->getIncrementing() || ! in_array($model->getKeyName(), $model->uniqueIds(), true)) {
            return true;
        }

        $traits = class_uses_recursive($model);

        return match (true) {
            in_array(HasUuids::class, $traits, true) => Str::isUuid($id),
            in_array(HasUlids::class, $traits, true) => Str::isUlid($id),
            default => true,
        };
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
        $tabs = $this->tabs();

        if ($tabs === []) {
            return $query;
        }

        return $this->activeTab($tabs)->modify($query);
    }

    /**
     * The tabs this request may see. With none declared and saved tabs on, a counted All tab.
     *
     * @return list<Tab>
     */
    public function tabs(): array
    {
        $declared = $this->resource->tabs($this->request);

        if ($declared === [] && $this->resource->definition()->usesSavedTabs() && $this->config()->boolean('saved_tabs.enabled')) {
            return [Tab::make('All')->withCount()];
        }

        return array_values(array_filter($declared, fn (Tab $tab) => $tab->authorizedToSee($this->request)));
    }

    /**
     * The key of the tab the request shows: the first one unless ?tab= names another.
     */
    public function activeTabKey(): ?string
    {
        $tabs = $this->tabs();

        return $tabs === [] ? null : $this->activeTab($tabs)->key();
    }

    /**
     * One capped count of the tab over the Resource's own scope, never the user's filters or search.
     */
    public function tabCount(Tab $tab): Count
    {
        $cap = $this->resource->definition()->getCountCap();
        $base = $this->tabBase($tab);

        $count = DB::connection($base->getModel()->getConnectionName())
            ->query()
            ->fromSub($base->toBase()->limit($cap + 1), 'lathe_count')
            ->count();

        return new Count(min($count, $cap), $count > $cap);
    }

    /**
     * What a badge closure answers for the tab, or the plain badge.
     */
    public function tabBadge(Tab $tab): string|int|null
    {
        return $tab->badgeFor($this->tabBase($tab));
    }

    /**
     * @param  list<Tab>  $tabs
     */
    private function activeTab(array $tabs): Tab
    {
        $key = $this->request->query('tab');

        if ($key === null || $key === '') {
            return $tabs[0];
        }

        foreach ($tabs as $tab) {
            if ($key === $tab->key()) {
                return $tab;
            }
        }

        throw ValidationException::withMessages(['tab' => 'The tab is not available.']);
    }

    /**
     * @return Builder<Model>
     */
    private function tabBase(Tab $tab): Builder
    {
        $query = $this->resource::query();

        foreach ($this->withoutScopes as $scope) {
            $query->withoutGlobalScope($scope);
        }

        return $tab->modify($this->resource::indexQuery($this->request, $this->applyTrashed($query)));
    }

    /**
     * The active saved tab's stored filters and search, applied before the user's own.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySavedTab(Builder $query): Builder
    {
        $tab = SavedTabQueryApplicator::active($this->resource, $this->request);

        return $tab === null ? $query : (new SavedTabQueryApplicator($this->resource, $this->request, $tab))->applyBase($query);
    }

    /**
     * A capped count of a saved tab for its badge, over the Resource's own scope.
     */
    public function savedTabCount(SavedTab $tab): Count
    {
        $query = $this->resource::query();

        foreach ($this->withoutScopes as $scope) {
            $query->withoutGlobalScope($scope);
        }

        $base = $this->resource::indexQuery($this->request, $this->applyTrashed($query));

        return (new SavedTabQueryApplicator($this->resource, $this->request, $tab))->countForSavedTab($base);
    }

    /**
     * The active saved tab's stored sort when the request names none, cut to the sortable keys.
     */
    private function savedSort(): ?string
    {
        $sort = $this->request->query('sort');

        if ($this->context !== QueryContext::Index || ($sort !== null && $sort !== '')) {
            return null;
        }

        $tab = SavedTabQueryApplicator::active($this->resource, $this->request);

        return $tab === null ? null : (new SavedTabQueryApplicator($this->resource, $this->request, $tab))->applySort($this->sortKeys());
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query): Builder
    {
        $definition = $this->resource->definition();
        $term = SearchApplicator::term($this->request->query('q'), $definition->getSearchTermChars(), $definition->getSearchTermWords());

        if ($term === null) {
            return $query;
        }

        if ($definition->usesScout()) {
            return $query->whereIn($query->getModel()->getQualifiedKeyName(), $this->scoutKeys($term));
        }

        return SearchApplicator::for($this->resource)->apply($query, $term);
    }

    /**
     * The keys Scout finds for the term, capped at scoutSearchResults; filters, sort and
     * paging then run on those keys.
     *
     * @return list<mixed>
     */
    protected function scoutKeys(string $term): array
    {
        // Scout is the host app's optional dependency, so it is called, not imported.
        /** @var callable(string): mixed $search */
        $search = [$this->resource::model(), 'search'];
        /** @var \Laravel\Scout\Builder<Model> $builder */
        $builder = $this->resource::scoutQuery($this->request, $search($term));

        return array_values($builder->take($this->resource->definition()->getScoutSearchResults())->keys()->all());
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyFilters(Builder $query): Builder
    {
        $filters = $this->request->query('filter');

        if ($filters === null) {
            return $query;
        }

        if (! is_array($filters)) {
            throw ValidationException::withMessages(['filter' => 'The filter must be keyed by filter, as filter[status]=paid.']);
        }

        return (new FilterApplicator($this->resource, $this->lens?->availableFilters($this->request)))->apply($this->request, $query, $filters);
    }

    /**
     * The lens refines the scoped index query, then its filters, search and sort apply,
     * except a step its query() already ran through Lens::withFilters() or withOrdering().
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyLens(Builder $query): Builder
    {
        $lens = $this->lens ?? throw new \LogicException('No lens is set.');
        $this->lensFiltered = $this->lensOrdered = false;
        // Lens::withFilters() and withOrdering() are static, so they find this query on the request.
        $this->request->attributes->set(self::class, $this);

        $query = $lens::query($this->request, $query);

        if (! $this->lensFiltered) {
            $query = $this->applyFilters($query);
        }

        return $this->applyLensSearch($query);
    }

    /**
     * The lens's searchable columns through the shared search, with the key match.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyLensSearch(Builder $query): Builder
    {
        $definition = $this->resource->definition();
        $term = SearchApplicator::term($this->request->query('q'), $definition->getSearchTermChars(), $definition->getSearchTermWords());
        $declared = $this->lens?->searchableColumns() ?? [];

        if ($term === null || $declared === []) {
            return $query;
        }

        $columns = array_map(fn (string|SearchableColumn $column) => is_string($column) ? LikeSearch::prefix($column) : $column, $declared);
        $columns[] = new SearchablePrimaryKey($definition->getMaxPrimaryKeySize());

        return (new SearchApplicator($columns))->apply($query, $term);
    }

    /**
     * The request's filters, applied by a lens's query() through Lens::withFilters().
     *
     * @internal
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyLensFilters(Builder $query): Builder
    {
        $query = $this->applyFilters($query);
        $this->lensFiltered = true;

        return $query;
    }

    /**
     * The request's sort, applied by a lens's query() through Lens::withOrdering().
     *
     * @internal
     *
     * @param  Builder<Model>  $query
     * @param  (\Closure(Builder<Model>): Builder<Model>)|null  $default
     * @return Builder<Model>
     */
    public function applyLensOrdering(Builder $query, ?\Closure $default = null): Builder
    {
        $query = $default !== null && $this->sortTokens() === [] ? $default($query) : $this->applySort($query);
        $this->lensOrdered = true;

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
        $savedSort = $this->savedSort();

        // sortPlan() and sortString() read the request, so the stored sort becomes the request's.
        if ($savedSort !== null) {
            $this->request->query->set('sort', $savedSort);
        }

        if ($this->lensOrdered) {
            return $query;
        }

        $model = $query->getModel();
        $plan = $this->sortPlan($model);
        [$joined, $columns] = $this->applyGroupOrder($query);

        foreach ($plan as $step) {
            if ($step['relation'] !== null && ! in_array($step['relation']->getRelationName(), $joined, true)) {
                $relation = $step['relation'];
                $joined[] = $relation->getRelationName();
                $query->leftJoin(
                    $relation->getRelated()->getTable(),
                    $relation->getQualifiedForeignKeyName(),
                    '=',
                    $relation->getQualifiedOwnerKeyName(),
                );
            }

            if (! isset($columns[$step['column']])) {
                $columns[$step['column']] = $step['direction'];
            }
        }

        // The joined rows would bring the related columns along, so keep the model's own.
        if ($joined !== [] && $query->getQuery()->columns === null) {
            $query->select($model->getTable().'.*');
        }

        $last = $plan === [] ? 'desc' : $plan[array_key_last($plan)]['direction'];
        $columns += [$model->getQualifiedKeyName() => $last];

        foreach ($columns as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /**
     * The group ?group= picks, else the Resource's default group, with its direction.
     * Lenses do not group. An unknown id or direction is 422.
     *
     * @return array{group: Group, direction: 'asc'|'desc'}|null
     */
    public function activeGroup(): ?array
    {
        $value = $this->request->query('group');
        $value = $value === null || $value === '' ? $this->resource->definition()->getDefaultGroup() : $value;

        if ($this->lens !== null || $this->context !== QueryContext::Index || $value === null || $value === self::UNGROUPED) {
            return null;
        }

        // An array ?group[]= names no group id, so it fails like an unknown one.
        $parts = is_string($value) ? explode(':', $value) : [''];
        $direction = $parts[1] ?? null;
        $valid = count($parts) <= 2 && in_array($direction, [null, 'asc', 'desc'], true);

        foreach ($valid ? $this->resource->groups($this->request) : [] as $group) {
            if ($group->id() === $parts[0]) {
                $group->inTimezone(app(Timezone::class)->forRequest($this->request));

                return ['group' => $group, 'direction' => match ($direction) {
                    'asc', 'desc' => $direction,
                    default => $group->getOrderDirection(),
                }];
            }
        }

        throw ValidationException::withMessages(['group' => 'group is invalid']);
    }

    /**
     * The group's column ordered first; a to-one relation group joins like a relation sort.
     *
     * @param  Builder<Model>  $query
     * @return array{0: list<string>, 1: array<string, 'asc'|'desc'>}
     */
    private function applyGroupOrder(Builder $query): array
    {
        $active = $this->activeGroup();

        if ($active === null) {
            return [[], []];
        }

        $group = $active['group'];
        $model = $query->getModel();
        $name = $group->relation();

        if ($name === null) {
            return [[], [$model->qualifyColumn($group->column()) => $active['direction']]];
        }

        /** @var BelongsTo<Model, Model> $relation */
        $relation = $model->{$name}();
        // The header reads the related value from each row, so the relation loads with the page.
        $query->leftJoin($relation->getRelated()->getTable(), $relation->getQualifiedForeignKeyName(), '=', $relation->getQualifiedOwnerKeyName())
            ->with($name);

        return [[$name], [$relation->getRelated()->qualifyColumn($group->attribute()) => $active['direction']]];
    }

    /**
     * The keys of the sortable fields that can sort on this model.
     *
     * @return list<string>
     */
    public function sortKeys(): array
    {
        $model = $this->scoped()->getModel();

        return array_values(array_filter(
            array_keys($this->sortableFields()),
            fn (string $key) => $this->sortTarget($model, $key) !== null,
        ));
    }

    /**
     * The requested sort as the ?sort= string, `-total_cents,placed_at`.
     */
    public function sortString(): string
    {
        return implode(',', array_map(
            fn (array $token) => ($token['direction'] === 'desc' ? '-' : '').$token['key'],
            $this->sortTokens(),
        ));
    }

    /**
     * @return array<string, Field>
     */
    private function sortableFields(): array
    {
        $fields = [];

        foreach ($this->lens?->fields($this->request) ?? $this->resource->fieldsFor(FieldContext::Index, $this->request) as $field) {
            if ($field instanceof Field && $field->isSortable()) {
                $fields[$field->getSortableUriKey()] = $field;
            }
        }

        return $fields;
    }

    /**
     * The tokens of ?sort=, each a field key and a direction. Anything else is 422.
     *
     * @return list<array{key: string, direction: 'asc'|'desc'}>
     */
    private function sortTokens(): array
    {
        $sort = $this->request->query('sort');

        if ($sort === null || $sort === '') {
            return [];
        }

        $invalid = ValidationException::withMessages(['sort' => 'sort is invalid']);
        $tokens = is_string($sort) ? explode(',', $sort) : [];

        if ($tokens === [] || count($tokens) > 3) {
            throw $invalid;
        }

        $parsed = [];

        foreach ($tokens as $token) {
            $parts = explode(':', $token);
            $key = $parts[0];
            $direction = $parts[1] ?? null;

            if (count($parts) > 2 || ($direction !== null && ! in_array($direction, ['asc', 'desc'], true))) {
                throw $invalid;
            }

            if (str_starts_with($key, '-')) {
                // A leading dash and a suffix both name a direction, so a token may not carry both.
                if ($direction !== null) {
                    throw $invalid;
                }

                $key = substr($key, 1);
                $direction = 'desc';
            }

            $parsed[] = ['key' => $key, 'direction' => $direction === 'desc' ? 'desc' : 'asc'];
        }

        return $parsed;
    }

    /**
     * Each token's qualified column and, for a belongsTo field, the relation to join.
     *
     * @return list<array{column: string, direction: 'asc'|'desc', relation: BelongsTo<Model, Model>|null}>
     */
    private function sortPlan(Model $model): array
    {
        $plan = [];

        foreach ($this->sortTokens() as $token) {
            $target = $this->sortTarget($model, $token['key']);

            if ($target === null) {
                throw ValidationException::withMessages(['sort' => 'sort is invalid']);
            }

            $plan[] = [...$target, 'direction' => $token['direction']];
        }

        return $plan;
    }

    /**
     * @return array{column: string, relation: BelongsTo<Model, Model>|null}|null
     */
    private function sortTarget(Model $model, string $key): ?array
    {
        if (! isset($this->sortableFields()[$key])) {
            return null;
        }

        if (! str_contains($key, '.')) {
            return ['column' => $model->qualifyColumn($key), 'relation' => null];
        }

        [$name, $column] = explode('.', $key, 2);

        // Only a belongsTo has one row per record, so only it can join without repeating rows.
        if (str_contains($column, '.') || ! method_exists($model, $name)) {
            return null;
        }

        $relation = $model->{$name}();

        return $relation instanceof BelongsTo
            ? ['column' => $relation->getRelated()->qualifyColumn($column), 'relation' => $relation]
            : null;
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
