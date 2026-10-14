<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

/**
 * A many-to-many relation listed in a panel on detail, where records are attached and
 * detached with their pivot values.
 */
class BelongsToManyField extends RelationField implements FilterableField
{
    protected string $component = 'belongs-to-many-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->onlyOnDetail();
    }

    public function kind(): string
    {
        return 'belongsToMany';
    }

    /** @var list<Field>|Closure */
    protected Closure|array $pivotFields = [];

    /** @var list<mixed>|Closure */
    protected Closure|array $pivotActions = [];

    protected bool $allowDuplicateRelations = false;

    /**
     * Fields kept on the pivot row, validated under pivot.<attribute>.
     *
     * @param  list<Field>|Closure  $fields  Closure(LatheRequest $request, ?Model $related): list<Field>
     */
    public function pivotFields(Closure|array $fields): static
    {
        $this->mutate();
        $this->pivotFields = $fields;

        return $this;
    }

    /**
     * @return list<Field>|Closure
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
     * @param  list<mixed>|Closure  $actions  Closure(LatheRequest $request): list<Action>
     */
    public function pivotActions(Closure|array $actions): static
    {
        $this->mutate();
        $this->pivotActions = $actions;

        return $this;
    }

    /**
     * @return list<mixed>|Closure
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
