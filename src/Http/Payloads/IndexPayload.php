<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Filters\Filter;
use Lathe\Filters\FilterApplicator;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\SavedTabs\SavedTab;
use Lathe\SavedTabs\SavedTabQueryApplicator;
use Lathe\SavedTabs\SchemaHash;
use Lathe\Tabs\Tab;

/**
 * The index data the page and the JSON API both send. Built from fields, never from a model.
 */
class IndexPayload
{
    /** @var list<string> */
    public const array ROW_ABILITIES = ['view', 'update', 'delete'];

    public function __construct(
        protected LatheRequest $request,
        protected ResourceAuthorization $authorization,
    ) {
        //
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{resource: array<string, mixed>, table: array<string, mixed>, filters: array{available: list<array<string, mixed>>, applied: array<string, mixed>}, state: array<string, mixed>, tabs: array{items: list<array<string, mixed>>, active: string|null, saved: list<array<string, mixed>>, activeSaved: int|null}}
     */
    public function build(string $class): array
    {
        $resource = $class::make();
        $query = new ResourceQuery($resource, $this->request);
        $rows = $query->rows();
        [$perPage, $page] = $query->page();
        $fields = array_values(array_filter($resource->fields($this->request), fn (mixed $field) => $field instanceof Field));
        $softDeletes = $resource->definition()->usesSoftDeletes();
        $abilities = $this->authorization->abilitiesFor(
            $this->request->user(),
            $class,
            $rows,
            $softDeletes ? [...self::ROW_ABILITIES, 'restore', 'forceDelete'] : self::ROW_ABILITIES,
        );
        $threshold = $resource->definition()->getFilterRemoteThreshold();
        $filters = $this->request->query('filter');

        return [
            'resource' => [
                'uriKey' => $class::uriKey(),
                'label' => $class::label(),
                'singularLabel' => $class::singularLabel(),
                'perPageOptions' => $query->perPageOptionsInUse(),
                'definition' => $resource->definition()->options(),
            ],
            'table' => [
                'columns' => $fields,
                'sortKeys' => $query->sortKeys(),
                'rowKey' => 'id',
                'rows' => $rows->map(fn (Model $model) => [
                    'id' => $model->getKey(),
                    'title' => $resource->forModel($model)->title(),
                    'cells' => collect($fields)->mapWithKeys(fn (Field $field) => [$field->key() => $field->cell($model)])->all(),
                    'abilities' => $abilities[$this->key($model)] ?? [],
                    ...($softDeletes ? ['trashed' => method_exists($model, 'trashed') && $model->trashed() === true] : []),
                ])->values()->all(),
            ],
            'filters' => [
                'available' => array_map(fn (Filter $filter) => $this->filter($filter, $threshold), $resource->availableFilters($this->request)),
                'applied' => (new FilterApplicator($resource))->values($this->request, is_array($filters) ? $filters : []),
            ],
            'tabs' => [
                'items' => array_map(fn (Tab $tab) => $tab->toArray(), $query->tabs()),
                'active' => $query->activeTabKey(),
                'saved' => $this->savedTabs($resource, $query),
                'activeSaved' => $this->activeSavedTab($resource),
            ],
            'state' => [
                'page' => $page,
                'perPage' => $perPage,
                'hasMorePages' => $query->hasMorePages(),
                'sort' => $query->sortString(),
            ],
        ];
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{value: int, exact: bool, capped: bool, estimated: bool}|null
     */
    public function count(string $class): ?array
    {
        return (new ResourceQuery($class::make(), $this->request))->count()?->toArray();
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
     * @return list<array{id: int, name: string, badgeType: string|null, showCount: bool, stale: bool, filters: array<string, mixed>, sort: string|null}>
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
                'sort' => $applicator->applySort($sortKeys),
            ];
        })->all());
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
     * A remote filter sends no options, so a long list never loads on the index.
     *
     * @return array<string, mixed>
     */
    protected function filter(Filter $filter, int $threshold): array
    {
        $remote = $filter->isRemote($this->request, $threshold);
        $options = $remote ? null : $filter->options($this->request);

        return [
            'key' => $filter->key(),
            'name' => $filter->name(),
            'component' => $filter->component(),
            'options' => $options === null ? null : array_map(
                fn (string|int $label, mixed $value) => ['label' => (string) $label, 'value' => $value],
                array_keys($options),
                array_values($options),
            ),
            'remote' => $remote,
            'immediate' => $filter->isImmediate(),
            'placement' => $filter->getPlacement(),
            'default' => $filter->default(),
        ];
    }

    protected function key(Model $model): int|string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
    }
}
