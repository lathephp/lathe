<?php

namespace Lathe\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Lenses\Lens;
use Lathe\Resources\Resource;

/**
 * The rows a Selection names. It reads them only through ResourceQuery, so scopes, hooks,
 * search, filters, tabs and lenses narrow a selection exactly as they narrow the index.
 */
class SelectionResolver
{
    /** @var Builder<Model>|null */
    protected ?Builder $query = null;

    public function __construct(
        protected Resource $resource,
        protected LatheRequest $request,
        protected Selection $selection,
        protected ?Lens $lens = null,
    ) {
        //
    }

    /**
     * The scoped query narrowed to the selection: the named keys in mode ids, everything
     * the index state shows but the exclusions in mode all.
     *
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $this->query ??= $this->narrow($this->indexQuery()->scoped());

        return clone $this->query;
    }

    /**
     * 422 on `selection` when no row matches.
     */
    public function ensureNotEmpty(): void
    {
        if (! $this->query()->exists()) {
            throw ValidationException::withMessages(['selection' => Lathe::__('Select at least one row.')]);
        }
    }

    /**
     * Whether more than $max rows match, read through a subquery that stops one row past
     * it. Mode ids answers from the length of its list.
     */
    public function exceeds(int $max): bool
    {
        if (! $this->selection->isAll()) {
            return count($this->selection->ids) > $max;
        }

        $query = $this->query();
        $capped = $query->toBase()->select($query->getModel()->getQualifiedKeyName())->limit($max + 1);

        return $capped->newQuery()->fromSub($capped, 'capped')->count() > $max;
    }

    /**
     * The rows in chunks of $chunk by key, so a set of any size is never loaded whole.
     *
     * @return LazyCollection<int, Model>
     */
    public function lazy(int $chunk = 200): LazyCollection
    {
        $model = $this->query()->getModel();

        return $this->query()->lazyById($chunk, $model->getQualifiedKeyName(), $model->getKeyName());
    }

    /**
     * The index read of the selection's state, built from a copy of the request that holds
     * only that state, so the URL of the action never narrows it.
     */
    protected function indexQuery(): ResourceQuery
    {
        $state = array_filter([
            'q' => $this->selection->q,
            'filter' => $this->selection->filters,
            'tab' => $this->selection->tab,
            'saved_tab' => $this->selection->savedTab,
            'trashed' => $this->selection->trashed,
        ], fn (mixed $value) => $value !== null && $value !== '' && $value !== []);

        $query = new ResourceQuery($this->resource, $this->request->duplicate($state), QueryContext::Index);
        $lens = $this->lens ?? $this->selectionLens();

        return $lens === null ? $query : $query->forLens($lens);
    }

    /**
     * The lens the selection names: 404 when the Resource has none by that key, 403 when
     * canSee or the viewLens ability refuses it.
     */
    protected function selectionLens(): ?Lens
    {
        $key = $this->selection->lens;

        if ($key === null || $key === '') {
            return null;
        }

        $lens = collect($this->resource->lenses($this->request))->first(fn (Lens $lens) => $lens->uriKey() === $key);

        abort_if($lens === null, 404);
        abort_unless($lens->authorizedToSee($this->request), 403);
        abort_unless(app(ResourceAuthorization::class)->allowsLens($this->request->user(), $this->resource::class, $lens), 403);

        return $lens;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function narrow(Builder $query): Builder
    {
        return $this->selection->isAll()
            ? $query->whereKeyNot($this->selection->exclusions)
            : $query->whereKey($this->selection->ids);
    }
}
