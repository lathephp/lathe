<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Lathe\Events\ResourceAttached;
use Lathe\Events\ResourceAttachmentUpdated;
use Lathe\Events\ResourceDetached;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
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

    /**
     * The names of the declared pivot actions, as the panel lists them. A Closure is called
     * with the request; an action object names itself with name(), a string is its own name.
     *
     * @return list<string>
     */
    public function pivotActionNames(LatheRequest $request): array
    {
        $actions = $this->pivotActions instanceof Closure ? ($this->pivotActions)($request) : $this->pivotActions;
        $names = [];

        foreach (is_array($actions) ? $actions : [] as $action) {
            $name = match (true) {
                is_string($action) => $action,
                is_object($action) && method_exists($action, 'name') => $action->name(),
                is_object($action) => Str::headline(class_basename($action)),
                default => null,
            };

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * On a form (only when shown there) the value is the related records as picker items.
     *
     * @return list<array<string, mixed>>|null
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        if (! $record instanceof Model || ! ($this->isShownOn(FieldContext::Update) || $this->isShownOn(FieldContext::Create))) {
            return null;
        }

        if (! $record->relationLoaded($this->attribute)) {
            $record->load($this->attribute);
        }

        $related = $record->getRelation($this->attribute);

        return $related instanceof EloquentCollection
            ? array_values($related->map(fn (Model $model) => $this->pickerItem($model))->all())
            : [];
    }

    /**
     * A form posts the ids and {attribute}_pivot ({id: {pivot values}}); the sync runs after
     * the parent saves, inside its transaction, and fires an event per attached, detached and
     * changed row.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $attribute = $requestAttribute ?? $this->attribute;

        if (! $model instanceof Model || ! $request->exists($attribute) || ($model->exists && $this->isImmutable($request, $model))) {
            return null;
        }

        $ids = TagField::ids($request->input($attribute));
        $posted = $request->input($attribute.'_pivot');
        $columns = array_map(fn (Field $field) => $field->attribute(), $this->pivotFieldsFor($request));
        $records = [];

        foreach ($ids as $id) {
            $values = is_array($posted) && is_array($posted[$id] ?? null) ? $posted[$id] : [];
            $records[$id] = array_intersect_key($values, array_flip($columns));
        }

        $owner = $this->owner;

        return function () use ($model, $records, $owner): void {
            $relation = $model->{$this->attribute}();

            if (! $relation instanceof BelongsToMany) {
                return;
            }

            $changes = $relation->sync($records);
            $model->unsetRelation($this->attribute);
            $keys = fn (mixed $ids): array => array_values(array_filter(is_array($ids) ? $ids : [], fn (mixed $id) => is_int($id) || is_string($id)));

            if ($owner === null) {
                return;
            }

            foreach ($keys($changes['attached']) as $id) {
                event(new ResourceAttached($model, $owner, $this->attribute, [$id], $records[$id] ?? []));
            }

            if ($keys($changes['detached']) !== []) {
                event(new ResourceDetached($model, $owner, $this->attribute, $keys($changes['detached'])));
            }

            foreach ($keys($changes['updated']) as $id) {
                event(new ResourceAttachmentUpdated($model, $owner, $this->attribute, [$id], [], $records[$id] ?? []));
            }
        };
    }

    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $rules = parent::getRules($context, $request, $model);

        if ($request !== null && $this->owner !== null && $request->exists($this->attribute)) {
            $rules[$this->attribute][] = $this->relatedIdsRule($request, $this->owner, $model);
        }

        return $rules;
    }
}
