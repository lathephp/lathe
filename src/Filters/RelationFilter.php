<?php

namespace Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lathe\Auth\ResourceAuthorization;
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

        abort_unless($this->mayList($request), 403);

        // The picker reads q and page from the request and pages by $perPage.
        $models = $query->options($perPage);
        $options = array_values($models->map(fn (Model $model) => ['label' => $this->field->displayFor($model), 'value' => $model->getKey()])->all());

        // The caller reads one option past the page as "more follow"; it never shows it.
        return $query->hasMorePages() ? [...$options, ['label' => '', 'value' => null]] : $options;
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(LatheRequest $request, array $values): array
    {
        $query = $this->relatedQuery($request);

        if ($query === null || $this->field instanceof MorphToField) {
            return parent::labels($request, $values);
        }

        $labels = [];

        foreach ($query->scoped()->whereKey($values)->get() as $model) {
            $key = $model->getKey();
            $labels[is_scalar($key) ? (string) $key : ''] = $this->field->displayFor($model);
        }

        return $labels;
    }

    /**
     * Remote once the related table holds more rows than the threshold, counted without loading them.
     * A user who may not list the related records gets the remote picker too, whose search refuses them,
     * so the definition never carries the records inline.
     */
    public function isRemote(LatheRequest $request, int $threshold): bool
    {
        if ($this->field instanceof MorphToField) {
            return false;
        }

        $query = $this->relatedQuery($request);

        if ($query === null || ! $this->mayList($request)) {
            return true;
        }

        return $query->holdsMoreThan($threshold);
    }

    /**
     * Listing the related records needs canSee and viewAny on their Resource, as a relation picker does.
     */
    protected function mayList(LatheRequest $request): bool
    {
        $related = $this->field->relatedResource();
        $authorization = app(ResourceAuthorization::class);

        return $related !== null && $authorization->canSee($request, $related) && $authorization->allows($request->user(), $related, 'viewAny');
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
