<?php

namespace Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The filter a filterable() relation field generates. A belongsTo or belongsToMany field picks
 * one related record: listed inline while the related table is small, else a remote picker
 * that types ahead on the related title. A morphTo field picks one of its types.
 */
class RelationFilter extends Filter
{
    public function __construct(protected RelationField $field, protected ?Closure $callback = null)
    {
        //
    }

    public function key(): string
    {
        return 'field_'.$this->field->attribute();
    }

    public function name(): string
    {
        return $this->field->label();
    }

    /**
     * @return array<string, mixed> label => value
     */
    public function options(LatheRequest $request): array
    {
        if ($this->field instanceof MorphToField) {
            $options = [];

            foreach ($this->field->getTypes() as $type) {
                $options[$type::singularLabel()] = $type::uriKey();
            }

            return $options;
        }

        $options = [];

        foreach ($this->relatedQuery($request)?->scoped()->get() ?? [] as $model) {
            $options[$this->field->displayFor($model)] = $model->getKey();
        }

        return $options;
    }

    /**
     * @return list<array{label: string, value: mixed}>
     */
    public function searchOptions(LatheRequest $request, string $term, int $page, int $perPage): array
    {
        $query = $this->relatedQuery($request);

        if ($query === null || $this->field instanceof MorphToField) {
            return parent::searchOptions($request, $term, $page, $perPage);
        }

        // The picker reads q and page from the request; one row past the page says more follow.
        $models = $query->options($perPage + 1);

        return array_values($models->map(fn (Model $model) => ['label' => $this->field->displayFor($model), 'value' => $model->getKey()])->all());
    }

    /**
     * Remote once the related table holds more rows than the threshold, counted without loading them.
     */
    public function isRemote(LatheRequest $request, int $threshold): bool
    {
        if ($this->field instanceof MorphToField) {
            return false;
        }

        $query = $this->relatedQuery($request);

        if ($query === null) {
            return true;
        }

        $scoped = $query->scoped();
        // Counting a subquery capped one past the threshold reads at most that many keys.
        $capped = $scoped->toBase()->select($scoped->getModel()->getQualifiedKeyName())->limit($threshold + 1);

        return $capped->newQuery()->fromSub($capped, 'capped')->count() > $threshold;
    }

    protected function acceptsRemoteValue(string $value): bool
    {
        return $value !== '' && strlen($value) <= 255;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        $attribute = $this->field->attribute();

        if ($this->callback !== null) {
            ($this->callback)($request, $query, $value, $attribute);

            return $query;
        }

        if ($this->field instanceof MorphToField) {
            $type = collect($this->field->getTypes())->first(fn (string $class) => $class::uriKey() === $value);

            return $type === null
                ? $query->whereRaw('1 = 0')
                : $query->where($query->qualifyColumn($attribute.'_type'), $type::newModel()->getMorphClass());
        }

        $relation = $query->getModel()->{$attribute}();

        if ($relation instanceof BelongsTo) {
            return $query->where($relation->getQualifiedForeignKeyName(), $value);
        }

        return $query->whereHas($attribute, fn (Builder $related) => $related->whereKey($value));
    }

    protected function relatedQuery(LatheRequest $request): ?ResourceQuery
    {
        $related = $this->field->relatedResource();

        return $related === null ? null : new ResourceQuery($related::make(), $request, QueryContext::Relatable);
    }
}
