<?php

namespace Lathe\Field\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * The records a relation field may offer and accept: the picker lists them and the
 * Relatable rule checks a saved key against them.
 */
class Associatable
{
    /**
     * @param  class-string<resource>  $parent  the Resource whose form holds the field
     */
    public function __construct(
        protected LatheRequest $request,
        protected string $parent,
        protected RelationField $field,
    ) {
        //
    }

    /** The morph type a morphTo picker asks for: a related Resource's uriKey. */
    protected ?string $type = null;

    /**
     * The same picker for one morph type.
     */
    public function forType(?string $type): static
    {
        $copy = clone $this;
        $copy->type = $type;

        return $copy;
    }

    /**
     * The related Resource's query: its scopes, the field's relatableQuery, then the
     * parent's relatable{Model}s when it defines one, which replaces the related generic hook.
     */
    public function query(): ?ResourceQuery
    {
        $related = $this->relatedResource();

        if ($related === null) {
            return null;
        }

        $parent = $this->parent;
        $field = $this->field;
        $query = new ResourceQuery($related::make(), $this->request, QueryContext::Relatable);
        $method = 'relatable'.Str::plural(class_basename($related::newModel()));

        $query->relatableHooks(
            $field->getRelatableQuery(),
            method_exists($parent, $method) ? function (LatheRequest $request, Builder $builder) use ($parent, $method, $field): Builder {
                /** @var Builder<Model> */
                return $parent::$method($request, $builder, $field);
            } : null,
        );

        if ($field->allowsTrashed() && $this->request->query('with_trashed') === '1') {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * The Resource the options come from, the one place a morphTo field picks by type.
     *
     * @return class-string<resource>|null
     */
    public function relatedResource(): ?string
    {
        return $this->field instanceof MorphToField ? $this->field->resourceForType($this->type) : $this->field->relatedResource();
    }
}
