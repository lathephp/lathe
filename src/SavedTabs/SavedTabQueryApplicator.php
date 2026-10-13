<?php

namespace Lathe\SavedTabs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lathe\Filters\FilterApplicator;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\Count;
use Lathe\Resources\Resource;
use Lathe\Search\SearchApplicator;
use Lathe\Support\ConfigStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns a saved tab into query changes. Whatever the Resource no longer offers is
 * dropped, so a tab saved before a filter or sort went away still opens.
 */
class SavedTabQueryApplicator
{
    public function __construct(
        protected Resource $resource,
        protected LatheRequest $request,
        protected SavedTab $tab,
    ) {
        //
    }

    /**
     * Whether this Resource and the config offer saved tabs at all.
     */
    public static function enabled(Resource $resource): bool
    {
        return $resource->definition()->usesSavedTabs() && app(ConfigStore::class)->boolean('saved_tabs.enabled');
    }

    /**
     * The tab ?saved_tab= names, or null without it. A tab that is not the user's, or is not
     * this Resource's, is 404, the same as an unknown id.
     */
    public static function active(Resource $resource, LatheRequest $request): ?SavedTab
    {
        $id = $request->query('saved_tab');

        if ($id === null || $id === '' || ! static::enabled($resource)) {
            return null;
        }

        if (! is_string($id) || ! ctype_digit($id)) {
            throw new NotFoundHttpException;
        }

        return static::owned($resource, $request)->whereKey($id)->first() ?? throw new NotFoundHttpException;
    }

    /**
     * The user's saved tabs of this Resource in their order.
     *
     * @return Collection<int, SavedTab>
     */
    public static function listFor(Resource $resource, LatheRequest $request): Collection
    {
        return static::owned($resource, $request)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * The stored filters and search as the base of the query. The user's own filters and
     * search apply after it, so they narrow the tab and never widen it.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyBase(Builder $query): Builder
    {
        $filters = $this->filters();

        if ($filters !== []) {
            $query = (new FilterApplicator($this->resource))->apply($this->request, $query, $filters);
        }

        $term = $this->term();

        return $term === null ? $query : SearchApplicator::for($this->resource)->apply($query, $term);
    }

    /**
     * The stored sort cut to the tokens that are still sortable, as a ?sort= string, or null.
     *
     * @param  list<string>  $sortKeys
     */
    public function applySort(array $sortKeys): ?string
    {
        $kept = $this->keptSortTokens($sortKeys);

        return $kept === [] ? null : implode(',', $kept);
    }

    /**
     * A capped count of the tab over a Resource scope, never the user's filters or search.
     *
     * @param  Builder<Model>  $base
     */
    public function countForSavedTab(Builder $base): Count
    {
        $cap = $this->resource->definition()->getCountCap();

        $count = DB::connection($base->getModel()->getConnectionName())
            ->query()
            ->fromSub($this->applyBase($base)->toBase()->limit($cap + 1), 'lathe_count')
            ->count();

        return new Count(min($count, $cap), $count > $cap);
    }

    /**
     * The stored filters that still validate: what a Reset should save back.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $applicator = new FilterApplicator($this->resource);
        $valid = [];

        foreach ($this->tab->filters ?? [] as $key => $value) {
            try {
                $valid += $applicator->values($this->request, [$key => $value]);
            } catch (ValidationException) {
                // A filter the Resource dropped or changed is left out; the tab shows as stale.
            }
        }

        return $valid;
    }

    /**
     * Whether the Resource changed since the tab was saved, or the tab names something now gone.
     *
     * @param  list<string>  $sortKeys
     */
    public function isStale(array $sortKeys, string $schemaHash): bool
    {
        return $this->tab->schema_hash !== $schemaHash
            || count($this->filters()) !== count($this->tab->filters ?? [])
            || count($this->keptSortTokens($sortKeys)) !== count($this->sortTokens());
    }

    /**
     * @param  list<string>  $sortKeys
     * @return list<string>
     */
    protected function keptSortTokens(array $sortKeys): array
    {
        $kept = [];

        foreach ($this->sortTokens() as $token) {
            [$key] = explode(':', ltrim($token, '-'));

            if (in_array($key, $sortKeys, true)) {
                $kept[] = $token;
            }
        }

        return array_slice($kept, 0, 3);
    }

    /**
     * @return list<string>
     */
    protected function sortTokens(): array
    {
        return $this->tab->sort === null || $this->tab->sort === '' ? [] : explode(',', $this->tab->sort);
    }

    protected function term(): ?string
    {
        $definition = $this->resource->definition();

        try {
            return SearchApplicator::term($this->tab->q, $definition->getSearchTermChars(), $definition->getSearchTermWords());
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * @return Builder<SavedTab>
     */
    protected static function owned(Resource $resource, LatheRequest $request): Builder
    {
        $id = $request->user()?->getAuthIdentifier();

        return SavedTab::where('user_id', is_scalar($id) ? (string) $id : '')->where('resource_class', $resource::class);
    }
}
