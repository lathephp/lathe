<?php

namespace Lathe\Field\Relations\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

/**
 * The pivot options of the join-table relations: belongsToMany, morphToMany and morphedByMany.
 */
trait HasPivotFields
{
    /** @var Closure|list<Field> */
    protected Closure|array $pivotFields = [];

    /** @var Closure|list<mixed> */
    protected Closure|array $pivotActions = [];

    protected bool $allowDuplicateRelations = false;

    /**
     * Fields kept on the pivot row, validated under pivot.<attribute>.
     *
     * @param  Closure|list<Field>  $fields  Closure(LatheRequest $request, ?Model $related): list<Field>
     */
    public function pivotFields(Closure|array $fields): static
    {
        $this->mutate();
        $this->pivotFields = $fields;

        return $this;
    }

    /**
     * @return Closure|list<Field>
     */
    public function getPivotFields(): Closure|array
    {
        return $this->pivotFields;
    }

    /**
     * The pivot fields for a request; a Closure is called with the related record, null for the column list.
     *
     * @return list<Field>
     */
    public function pivotFieldsFor(LatheRequest $request, ?Model $related = null): array
    {
        $fields = $this->pivotFields instanceof Closure
            ? $this->evaluate($this->pivotFields, ['request' => $request, 'record' => $related], [$request, $related])
            : $this->pivotFields;

        return array_values(array_filter(is_array($fields) ? $fields : [], fn (mixed $field) => $field instanceof Field));
    }

    /**
     * Actions that run on selected pivot rows through the related Resource's action route.
     *
     * @param  Closure|list<mixed>  $actions  Closure(LatheRequest $request): list<Action>
     */
    public function pivotActions(Closure|array $actions): static
    {
        $this->mutate();
        $this->pivotActions = $actions;

        return $this;
    }

    /**
     * @return Closure|list<mixed>
     */
    public function getPivotActions(): Closure|array
    {
        return $this->pivotActions;
    }

    /**
     * Allow the same record to be attached more than once; the pivot table needs a key.
     */
    public function allowDuplicateRelations(bool $allow = true): static
    {
        $this->mutate();
        $this->allowDuplicateRelations = $allow;

        return $this;
    }

    public function noDuplicateRelations(): static
    {
        return $this->allowDuplicateRelations(false);
    }

    public function allowsDuplicateRelations(): bool
    {
        return $this->allowDuplicateRelations;
    }

    public function attaches(): bool
    {
        return true;
    }
}
