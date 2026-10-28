<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lathe\Actions\ActionMenu;
use Lathe\Auth\Impersonation;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Exceptions\DuplicateRowKeyException;
use Lathe\Field\Contracts\PrimesForPage;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\FieldResolver;
use Lathe\Filters\Filter;
use Lathe\Filters\FilterApplicator;
use Lathe\Groups\Group;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;
use Lathe\Preferences\ColumnChoice;
use Lathe\Preferences\LayoutChoice;
use Lathe\Preferences\PreviewState;
use Lathe\Preferences\UserPreference;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\PhoneCard;
use Lathe\Resources\Resource;
use Lathe\Resources\TrafficCop;
use Lathe\SavedTabs\SavedTab;
use Lathe\SavedTabs\SavedTabQueryApplicator;
use Lathe\SavedTabs\SchemaHash;
use Lathe\Summaries\ResourceSummaries;
use Lathe\Tabs\Tab;

/**
 * The index data the page and the JSON API both send. Built from fields, never from a model.
 */
class IndexPayload
{
    /** @var list<string> */
    public const array ROW_ABILITIES = ['view', 'update', 'delete', 'replicate'];

    public function __construct(
        protected LatheRequest $request,
        protected ResourceAuthorization $authorization,
    ) {
        //
    }

    /**
     * The index, or with $lens that lens of the Resource: its rows, columns and filters, no tabs.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{resource: array<string, mixed>, table: array<string, mixed>, filters: array{available: list<array<string, mixed>>, applied: array<string, mixed>}, state: array<string, mixed>, tabs: array{items: list<array<string, mixed>>, active: string|null, saved: list<array<string, mixed>>, activeSaved: int|null}, groups: array{available: list<array{id: string, label: string}>, active: array{id: string, direction: 'asc'|'desc', collapsible: bool}|null}, lens: array{uriKey: string, name: string}|null, lenses: list<array{uriKey: string, name: string}>, actions: array{actions: list<array<string, mixed>>, pivotActions: list<array{name: string, actions: list<array<string, mixed>>}>, counts: array{sole: int, standalone: int, resource: int}}}
     */
    public function build(string $class, ?Lens $lens = null): array
    {
        $resource = $class::make();
        $query = $this->query($resource, $lens);
        $rows = $query->rows();
        $rowKey = $class::rowKey();
        $this->checkRowKeys($class, $rowKey, $rows);
        [$perPage, $page] = $query->page();
        $fields = $this->columns($resource, $lens);

        $menu = new ActionMenu($this->request, $this->authorization);
        $active = $query->activeGroup();
        $mapped = $this->rows($class, $resource, $rows, $fields, $menu, $active['group'] ?? null);
        $available = $lens?->availableFilters($this->request) ?? $resource->availableFilters($this->request);
        $threshold = $resource->definition()->getFilterRemoteThreshold();
        $filters = $this->request->query('filter');
        $definitions = $this->withTokens($available, array_map(fn (Filter $filter) => $filter->definition($this->request, $threshold), $available));
        $applied = (new FilterApplicator($resource, $lens !== null ? $available : null))->values($this->request, is_array($filters) ? $filters : []);
        // A lens keeps its code columns: the saved choice is the Resource index's only.
        ['choice' => $choice, 'previewOpen' => $previewOpen, 'layout' => $layout] = $this->savedPreferences($class, $fields, $lens === null);
        $board = $lens !== null ? null : $this->board($resource, $fields);

        return [
            'resource' => $this->resource($class),
            'table' => [
                'columns' => $choice === null ? $fields : $this->chosenColumns($fields, $choice),
                'sortKeys' => $query->sortKeys(),
                'rowKey' => $rowKey,
                'rows' => $mapped,
                'columnChoice' => $choice,
                'previewOpen' => $previewOpen,
                'allColumns' => $lens !== null ? [] : array_map(fn (Field $field) => ['key' => $field->key(), 'label' => $field->label()], $fields),
                'phoneCard' => PhoneCard::pick($fields, $resource->definition()->getPhoneCard()),
                'board' => $board,
                // The URL's layout, so a Board link opens the Board, else the one this user chose last.
                'layout' => $board === null ? 'table' : $this->layout($layout),
            ],
            'groups' => $this->groups($resource, $active, $lens),
            'filters' => [
                'available' => $definitions,
                'applied' => $applied,
                // An open saved view's filters draw as chips too, so their names come along.
                'labels' => array_replace_recursive($this->remoteLabels($available, $definitions, $this->openSavedFilters($resource, $lens)), $this->remoteLabels($available, $definitions, $applied)),
            ],
            'tabs' => [
                'items' => $lens !== null ? [] : array_map(fn (Tab $tab) => $tab->toArray(), $query->tabs()),
                'active' => $lens !== null ? null : $query->activeTabKey(),
                'saved' => $lens !== null ? [] : $this->savedTabs($resource, $query),
                'activeSaved' => $lens !== null ? null : $this->activeSavedTab($resource),
            ],
            'lens' => $lens !== null ? $this->lensEntry($lens) : null,
            'lenses' => array_map(fn (Lens $lens) => $this->lensEntry($lens), $this->visibleLenses($class)),
            'actions' => $menu->build($class, 'index'),
            'state' => [
                'page' => $page,
                'perPage' => $perPage,
                'hasMorePages' => $query->hasMorePages(),
                'sort' => $query->sortString(),
            ],
        ];
    }

    /**
     * The rows as the table and the Board send them: key, title, cells, abilities and actions, plus the
     * stale-edit token, trashed flag and group the Resource and the index call for.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  Collection<int, Model>  $rows
     * @param  list<Field>  $fields
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $class, Resource $resource, Collection $rows, array $fields, ActionMenu $menu, ?Group $group = null): array
    {
        foreach ($fields as $field) {
            if ($field instanceof PrimesForPage) {
                $field->primeForPage($rows);
            }
        }
        $rowKey = $class::rowKey();
        $softDeletes = $resource->definition()->usesSoftDeletes();
        $inline = $this->hasInlineColumn($fields);
        $abilities = $this->authorization->abilitiesFor(
            $this->request->user(),
            $class,
            $rows,
            $this->rowAbilities($softDeletes, $inline),
        );

        // Not a policy ability: the user model's Impersonatable methods decide, from memory. The live
        // request holds the session, which the LatheRequest copy lacks.
        if (Impersonation::uses($class::newModel())) {
            foreach ($rows as $model) {
                $abilities[$this->key($model)]['impersonate'] = Impersonation::availableTo(request(), $model);
            }
        }
        // Rows of an index with inline cells carry the stale-edit token each cell edit sends back.
        $tokens = $resource->definition()->usesTrafficCop() && $inline;
        $rowActions = $menu->forRows($class);

        return $rows->map(fn (Model $model) => [
            'id' => $model->getAttribute($rowKey),
            'title' => $resource->forModel($model)->title(),
            'cells' => FieldResolver::cells($model, $fields),
            'abilities' => $abilities[$this->key($model)] ?? [],
            'actions' => $menu->runnableOn($class, $rowActions, $model),
            ...($tokens ? ['updatedAt' => TrafficCop::token($model)] : []),
            ...($softDeletes ? ['trashed' => method_exists($model, 'trashed') && $model->trashed() === true] : []),
            ...($group !== null ? ['group' => ['key' => $group->keyFor($model), 'title' => $group->titleFor($model), 'description' => $group->descriptionFor($model)]] : []),
        ])->values()->all();
    }

    /**
     * The Board's field, its options the columns in order, and whether a No value column ends it;
     * null when the Resource has no board or this user cannot see its field.
     *
     * @param  list<Field>  $fields
     * @return array{field: Field, noValue: bool}|null
     */
    public function board(Resource $resource, array $fields): ?array
    {
        $key = $resource->definition()->getBoard();

        foreach ($fields as $field) {
            if ($key !== null && $field->key() === $key) {
                return ['field' => $field, 'noValue' => $field->isNullable()];
            }
        }

        return null;
    }

    /**
     * The index columns: the fields shown on Index that this request may see.
     *
     * @return list<Field>
     */
    public function columns(Resource $resource, ?Lens $lens = null): array
    {
        $fields = $lens !== null
            ? new FieldCollection(array_values(array_filter($lens->fields($this->request), fn (mixed $field) => $field instanceof Field)))
            : $resource->fieldsFor(FieldContext::Index, $this->request);

        return array_values($fields->forContext(FieldContext::Index, $this->request)->authorized($this->request)->all());
    }

    /**
     * Each filter's key in the filter bar and the kind of value it takes. A field filter goes by its label in snake
     * case (Total gives total) unless that is another filter's key or another field's label; then by its own key.
     *
     * @param  list<Filter>  $available
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array<string, mixed>>
     */
    protected function withTokens(array $available, array $definitions): array
    {
        $keys = array_map(fn (Filter $filter) => $filter->key(), $available);
        $labels = array_map(fn (Filter $filter) => str_starts_with($filter->key(), 'field_') ? Str::snake($filter->name()) : null, $available);
        $taken = array_count_values([...$keys, ...array_filter($labels)]);

        foreach ($definitions as $index => $definition) {
            $label = $labels[$index];
            $definitions[$index]['token'] = $label !== null && $taken[$label] === 1 ? $label : $keys[$index];
            $definitions[$index]['kind'] = match (true) {
                $definition['remote'] === true => 'relation',
                $definition['component'] === 'range-filter' => 'range',
                $definition['component'] === 'date-filter' => 'date',
                $definition['component'] === 'boolean-filter' => 'boolean',
                $definition['component'] === 'multi-select-filter' => 'multiple',
                $definition['component'] === 'select-filter' && $definition['options'] !== [] => 'select',
                default => 'text',
            };
        }

        return $definitions;
    }

    /**
     * The labels of the values each applied remote filter holds, for its chip: the URL carries
     * only the keys. A filter with options sends them already, so it needs none.
     *
     * @param  list<Filter>  $available
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $applied
     * @return array<string, array<array-key, string>>
     */
    protected function remoteLabels(array $available, array $definitions, array $applied): array
    {
        $labels = [];

        foreach ($available as $index => $filter) {
            $value = $applied[$filter->key()] ?? null;

            if (($definitions[$index]['remote'] ?? false) !== true || $value === null || $value === '' || $value === []) {
                continue;
            }

            $values = array_values(array_map(fn (mixed $item) => is_scalar($item) ? (string) $item : '', is_array($value) ? $value : [$value]));
            $labels[$filter->key()] = $filter->labels($this->request, $values);
        }

        return $labels;
    }

    /**
     * The signed-in user's saved columns of this index, aged against the code's columns, or null when they saved none,
     * whether their preview panel is open, and the layout they chose last. One query reads the rows; a lens keeps its
     * code columns and has no Board, so it reads the panel row alone.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  list<Field>  $fields
     * @return array{choice: array{order: list<string>, hidden: list<string>, pinned: list<string>}|null, previewOpen: bool, layout: string|null}
     */
    protected function savedPreferences(string $class, array $fields, bool $withColumns): array
    {
        $uriKey = $class::uriKey();
        $keys = $withColumns ? [ColumnChoice::key($uriKey), PreviewState::key($uriKey), LayoutChoice::key($uriKey)] : [PreviewState::key($uriKey)];
        $rows = UserPreference::where('user_id', UserPreference::userId($this->request))->whereIn('key', $keys)->get()->keyBy('key');
        $columns = $rows->get(ColumnChoice::key($uriKey));
        $preview = $rows->get(PreviewState::key($uriKey));
        $layout = $rows->get(LayoutChoice::key($uriKey))?->value['layout'] ?? null;

        return [
            'choice' => $withColumns && $columns !== null ? ColumnChoice::age(array_map(fn (Field $field) => $field->key(), $fields), $columns->value) : null,
            'previewOpen' => $preview !== null && ($preview->value['open'] ?? false) === true,
            'layout' => is_string($layout) ? $layout : null,
        ];
    }

    /**
     * The layout the list opens in: the URL's ?layout, else the saved one, else the table.
     */
    protected function layout(?string $saved): string
    {
        $asked = $this->request->query('layout');

        foreach ([$asked, $saved] as $layout) {
            if (is_string($layout) && in_array($layout, LayoutChoice::LAYOUTS, true)) {
                return $layout;
            }
        }

        return 'table';
    }

    /**
     * The shown columns in the chosen order, each marked pinned or not.
     *
     * @param  list<Field>  $fields
     * @param  array{order: list<string>, hidden: list<string>, pinned: list<string>}  $choice
     * @return list<array<string, mixed>>
     */
    protected function chosenColumns(array $fields, array $choice): array
    {
        $byKey = [];

        foreach ($fields as $field) {
            $byKey[$field->key()] = $field;
        }

        return array_map(
            fn (string $key) => [...$byKey[$key]->jsonSerialize(), 'pinned' => in_array($key, $choice['pinned'], true)],
            ColumnChoice::visible(array_keys($byKey), $choice),
        );
    }

    /**
     * The Resource's own part of the payload, which reads no rows, so a refused URL can still draw the page.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{uriKey: string, label: string, singularLabel: string, perPageOptions: non-empty-list<int>, searchable: bool, softDeletes: bool, abilities: array{create: bool}, hasSummaries: bool, definition: array<string, mixed>, meta: array<string, mixed>, extra: array<string, mixed>}
     */
    public function resource(string $class): array
    {
        $resource = $class::make();
        $definition = $resource->definition();

        return [
            'uriKey' => $class::uriKey(),
            'label' => $class::label(),
            'singularLabel' => $class::singularLabel(),
            'perPageOptions' => (new ResourceQuery($resource, $this->request))->perPageOptionsInUse(),
            'searchable' => $resource->searchableColumns() !== [] || $definition->getUseScout() === true,
            // The filter panel offers Without, With and Only trashed when true.
            'softDeletes' => $definition->usesSoftDeletes(),
            'abilities' => ['create' => $this->authorization->allows($this->request->user(), $class, 'create')],
            // Whether the deferred summaries prop will be non-null, so the page reserves the footer.
            'hasSummaries' => ResourceSummaries::summarized($this->columns($resource)) !== [],
            'definition' => $definition->options(),
            'meta' => $resource->meta(),
            'extra' => $class::additionalInformation($this->request),
        ];
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{value: int, exact: bool, capped: bool, estimated: bool}|null
     */
    public function count(string $class, ?Lens $lens = null): ?array
    {
        return $this->query($class::make(), $lens)->count()?->toArray();
    }

    /**
     * The Resource's lenses this request may open: canSee and the viewLens ability.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return list<Lens>
     */
    public function visibleLenses(string $class): array
    {
        return array_values(array_filter(
            $class::make()->lenses($this->request),
            fn (Lens $lens) => $lens->authorizedToSee($this->request) && $this->authorization->allowsLens($this->request->user(), $class, $lens),
        ));
    }

    /**
     * The groups the menu offers and the one the rows are grouped by. A lens does not group.
     *
     * @param  array{group: Group, direction: 'asc'|'desc'}|null  $active
     * @return array{available: list<array{id: string, label: string}>, active: array{id: string, direction: 'asc'|'desc', collapsible: bool}|null}
     */
    protected function groups(Resource $resource, ?array $active, ?Lens $lens): array
    {
        return [
            'available' => $lens !== null ? [] : array_map(fn (Group $group) => $group->toArray(), $resource->groups($this->request)),
            'active' => $active === null ? null : ['id' => $active['group']->id(), 'direction' => $active['direction'], 'collapsible' => $active['group']->isCollapsible()],
        ];
    }

    protected function query(Resource $resource, ?Lens $lens): ResourceQuery
    {
        $query = new ResourceQuery($resource, $this->request);

        return $lens !== null ? $query->forLens($lens) : $query;
    }

    /**
     * @return array{uriKey: string, name: string}
     */
    protected function lensEntry(Lens $lens): array
    {
        return ['uriKey' => $lens->uriKey(), 'name' => $lens->name()];
    }

    /**
     * The footer totals, and under a group the subtotal of each group on the page keyed by
     * group key. The page's totals need the page's rows, so this reads them once more.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{allRecords: array<string, list<array{label: string|null, value: string|null}>>|null, page: array<string, list<array{label: string|null, value: string|null}>>|null, message: string|null, groups?: array<string, array<string, list<array{label: string|null, value: string|null}>>>}|null
     */
    public function summaries(string $class): ?array
    {
        $resource = $class::make();
        $query = new ResourceQuery($resource, $this->request);
        $rows = $query->rows();
        [, $page] = $query->page();
        $summaries = new ResourceSummaries($query, $this->columns($resource), $resource->definition()->getCountCap());
        $totals = $summaries->compute($rows, $page > 1 || $query->hasMorePages());
        $active = $query->activeGroup();

        return $totals === null || $active === null ? $totals : [...$totals, 'groups' => $summaries->computeGrouped($rows, $active['group'])];
    }

    /**
     * The badges of the tabs that need a query: one capped count or one closure result each.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array<string, array{count?: array{value: int, exact: bool, capped: bool, estimated: bool}, badge?: string|int|null}>
     */
    public function tabCounts(string $class): array
    {
        $resource = $class::make();
        $query = new ResourceQuery($resource, $this->request);
        $counts = [];

        foreach ($query->tabs() as $tab) {
            if ($tab->hasCount()) {
                $counts[$tab->key()]['count'] = $query->tabCount($tab)->toArray();
            }

            if ($tab->hasBadgeClosure()) {
                $counts[$tab->key()]['badge'] = $query->tabBadge($tab);
            }
        }

        foreach ($this->savedTabModels($resource) as $tab) {
            if ($tab->show_count) {
                $counts["saved:{$tab->id}"]['count'] = $query->savedTabCount($tab)->toArray();
            }
        }

        return $counts;
    }

    /**
     * The user's saved tabs: filters and sort are the cleaned values a Reset saves back.
     *
     * @return list<array{id: int, name: string, badgeType: string|null, showCount: bool, stale: bool, filters: array<string, mixed>, q: string|null, sort: string|null}>
     */
    protected function savedTabs(Resource $resource, ResourceQuery $query): array
    {
        $tabs = $this->savedTabModels($resource);

        if ($tabs->isEmpty()) {
            return [];
        }

        $hash = SchemaHash::for($resource, $this->request);
        $sortKeys = $query->sortKeys();

        return array_values($tabs->map(function (SavedTab $tab) use ($resource, $hash, $sortKeys) {
            $applicator = new SavedTabQueryApplicator($resource, $this->request, $tab);

            return [
                'id' => $tab->id,
                'name' => $tab->name,
                'badgeType' => $tab->badge_type,
                'showCount' => $tab->show_count,
                'stale' => $applicator->isStale($sortKeys, $hash),
                'filters' => $applicator->filters(),
                'q' => $tab->q,
                'sort' => $applicator->applySort($sortKeys),
            ];
        })->all());
    }

    /**
     * The filters of the saved view ?saved_tab= opens, as they still apply; none on a lens or without one.
     *
     * @return array<string, mixed>
     */
    protected function openSavedFilters(Resource $resource, ?Lens $lens): array
    {
        $tab = $lens === null && SavedTabQueryApplicator::enabled($resource) ? SavedTabQueryApplicator::active($resource, $this->request) : null;

        return $tab === null ? [] : (new SavedTabQueryApplicator($resource, $this->request, $tab))->filters();
    }

    protected function activeSavedTab(Resource $resource): ?int
    {
        return SavedTabQueryApplicator::active($resource, $this->request)?->id;
    }

    /**
     * @return Collection<int, SavedTab>
     */
    protected function savedTabModels(Resource $resource): Collection
    {
        return SavedTabQueryApplicator::enabled($resource) ? SavedTabQueryApplicator::listFor($resource, $this->request) : new Collection;
    }

    /**
     * Two rows with one key break selection and row identity on the page, so a debug app stops and
     * a live one logs and goes on.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  Collection<int, Model>  $rows
     */
    protected function checkRowKeys(string $class, string $rowKey, Collection $rows): void
    {
        $duplicates = $rows->map(fn (Model $model) => $model->getAttribute($rowKey))->duplicates();

        if ($duplicates->isEmpty()) {
            return;
        }

        $message = sprintf('%s has two rows with the %s key %s on one page.', $class, $rowKey, json_encode($duplicates->first()));

        if (config('app.debug') === true) {
            throw new DuplicateRowKeyException($message);
        }

        Log::error($message);
    }

    /**
     * Whether some column of the page edits its cell in place.
     *
     * @internal
     *
     * @param  list<Field>  $fields
     */
    public function hasInlineColumn(array $fields): bool
    {
        return array_filter($fields, fn (Field $field) => $field->isInlineEditable($this->request)) !== [];
    }

    /**
     * The abilities asked for each row; inlineEdit joins them only when a column is inline.
     *
     * @internal
     *
     * @return list<string>
     */
    public function rowAbilities(bool $softDeletes, bool $inline): array
    {
        return [
            ...self::ROW_ABILITIES,
            ...($softDeletes ? ['restore', 'forceDelete'] : []),
            ...($inline ? ['inlineEdit'] : []),
        ];
    }

    /**
     * The model key that abilitiesFor() keys a row's abilities by.
     */
    public function key(Model $model): int|string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
    }
}
