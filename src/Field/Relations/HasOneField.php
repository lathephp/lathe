<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Evaluator;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\ConfigStore;

/**
 * The single child of a hasOne relation: an inline block in the parent form and a panel on
 * detail. ofMany() makes a read-only, detail-only view of a latestOfMany, oldestOfMany or
 * ofMany relation.
 *
 * The form carries the related Resource's create fields, or its update fields once the row
 * exists, and posts their values under this field's attribute (`warranty.type`). The row is
 * saved after the parent, in the parent's transaction.
 */
class HasOneField extends RelationField
{
    protected string $component = 'has-one-field';

    protected bool $ofMany = false;

    protected ?Closure $alreadyFilledWhen = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex()->nullable();
    }

    public function kind(): string
    {
        return $this->ofMany ? 'hasOneOfMany' : 'hasOne';
    }

    public function ofMany(): static
    {
        $this->mutate();
        $this->ofMany = true;

        return $this->onlyOnDetail()->readonly();
    }

    public function isOfMany(): bool
    {
        return $this->ofMany;
    }

    /**
     * Replace the "the related row exists" test that hides Create once filled.
     *
     * @param  Closure(LatheRequest, Model): bool  $callback
     */
    public function alreadyFilledWhen(Closure $callback): static
    {
        $this->mutate();
        $this->alreadyFilledWhen = $callback;

        return $this;
    }

    public function getAlreadyFilledWhen(): ?Closure
    {
        return $this->alreadyFilledWhen;
    }

    /**
     * Whether the parent holds its one related row: alreadyFilledWhen(), else the row exists.
     */
    public function alreadyFilled(LatheRequest $request, Model $parent): bool
    {
        if ($this->alreadyFilledWhen !== null) {
            return (bool) ($this->alreadyFilledWhen)($request, $parent);
        }

        $relation = $parent->{$this->attribute}();

        return $relation instanceof Relation && $relation->exists();
    }

    public function createsThroughPanel(): bool
    {
        return ! $this->ofMany;
    }

    // Values

    /**
     * The related row's values keyed by its update fields' attributes, or null; an ofMany
     * field gives the row's title.
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $request = Evaluator::currentRequest();

        if (! $record instanceof Model || ! $record->exists || $request === null) {
            return null;
        }

        $related = $this->relatedRecord($record);

        if ($related === null) {
            return null;
        }

        if ($this->ofMany) {
            return $this->displayFor($related);
        }

        $values = [];

        foreach ($this->childFields(FieldContext::Update, $request, $record, $related) as $field) {
            $values[$field->attribute()] = $field->resolve($related);
        }

        return $values;
    }

    /**
     * Detail shows the related row's title.
     *
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $related = $this->relatedRecord($model);
        $title = $related === null ? null : $this->displayFor($related);

        return ['value' => $title, 'display' => $title ?? ''];
    }

    /**
     * Save the related row after the parent: update it when it exists, create it otherwise.
     * A null or blank value saves nothing.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        if ($this->ofMany || ! $model instanceof Model || ! $this->submitted($request)) {
            return null;
        }

        return function () use ($request, $model) {
            $relation = $model->{$this->attribute}();

            if (! $relation instanceof HasOneOrMany) {
                return;
            }

            $related = $relation->first();
            $related = $related instanceof Model ? $related : null;
            $child = $related ?? $relation->make();
            $callbacks = [];

            foreach ($this->writableChildFields($request, $model, $related) as $field) {
                $key = "{$this->attribute}.{$field->attribute()}";

                if (! $request->exists($key)) {
                    continue;
                }

                $callback = $field->fill($request, $child, $key);

                if ($callback instanceof Closure) {
                    $callbacks[] = $callback;
                }
            }

            $relation->save($child);

            foreach ($callbacks as $callback) {
                $callback();
            }

            $model->unsetRelation($this->attribute);
        };
    }

    // Validation

    /**
     * The field's own rule, then each related field's rules under `attribute.key` when a
     * value was sent.
     */
    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $rules = parent::getRules($context, $request, $model);

        if ($this->ofMany || $request === null || ! $this->submitted($request)) {
            return $rules;
        }

        $parent = $model ?? $this->ownerModel();
        $related = $model?->exists === true ? $this->relatedRecord($model) : null;

        if ($parent === null) {
            return $rules;
        }

        foreach ($this->writableChildFields($request, $parent, $related) as $field) {
            foreach ($field->getRules($related === null ? FieldContext::Create : FieldContext::Update, $request, $related) as $key => $fieldRules) {
                $rules["{$this->attribute}.{$key}"] = $fieldRules;
            }
        }

        return $rules;
    }

    public function getMessages(): array
    {
        return $this->scoped(fn (Field $field) => $field->getMessages(), parent::getMessages());
    }

    public function getValidationAttributes(): array
    {
        return $this->scoped(fn (Field $field) => $field->getValidationAttributes(), parent::getValidationAttributes());
    }

    protected function impliedRules(): array
    {
        return ['array'];
    }

    /**
     * Related field messages or attribute names under `attribute.key`.
     *
     * @param  Closure(Field): array<string, string>  $read
     * @param  array<string, string>  $scoped
     * @return array<string, string>
     */
    protected function scoped(Closure $read, array $scoped): array
    {
        $request = Evaluator::currentRequest();
        $parent = $this->ownerModel();

        if ($this->ofMany || $request === null || $parent === null) {
            return $scoped;
        }

        foreach ($this->childFields(FieldContext::Create, $request, $parent, null) as $field) {
            foreach ($read($field) as $key => $value) {
                $scoped["{$this->attribute}.{$key}"] = $value;
            }
        }

        return $scoped;
    }

    // Payload

    /**
     * Forms add the related fields; detail adds the related record, the via of a create
     * under this parent and whether Create shows.
     */
    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $schema = parent::schema($context, $request, $model);
        $parent = $model ?? $this->ownerModel();

        if ($this->ofMany || $parent === null) {
            return $schema;
        }

        $related = $parent->exists ? $this->relatedRecord($parent) : null;

        if ($context->isForm()) {
            $childContext = $related === null ? FieldContext::Create : FieldContext::Update;

            return [...$schema, 'fields' => array_map(
                fn (Field $field) => $field->schema($childContext, $request, $related),
                $this->childFields($childContext, $request, $parent, $related),
            )];
        }

        if ($context !== FieldContext::Detail || $this->owner === null) {
            return $schema;
        }

        $authorization = Container::getInstance()->make(ResourceAuthorization::class);
        $class = $this->relatedResource();
        $key = $parent->getKey();

        return [
            ...$schema,
            'record' => $related === null ? null : $this->recordSummary($related),
            'via' => $this->owner::uriKey().'/'.(is_scalar($key) ? (string) $key : '').'/'.$this->attribute,
            'canAdd' => $class !== null
                && $authorization->allows($request->user(), $this->owner, 'add'.Str::studly($this->attribute), $parent)
                && $authorization->allows($request->user(), $class, 'create')
                && ! $this->alreadyFilled($request, $parent),
        ];
    }

    /**
     * @return array{id: mixed, title: string, href: string|null}
     */
    protected function recordSummary(Model $related): array
    {
        $class = $this->relatedResource();
        $key = $related->getKey();
        $viewable = $class !== null && (is_int($key) || is_string($key))
            && Container::getInstance()->make(ResourceAuthorization::class)->allows(Evaluator::currentRequest()?->user(), $class, 'view', $related);
        $base = '/'.trim(Container::getInstance()->make(ConfigStore::class)->string('path'), '/');

        return [
            'id' => $key,
            'title' => $this->displayFor($related),
            'href' => $viewable ? rtrim($base, '/').'/resources/'.$class::uriKey().'/'.$key : null,
        ];
    }

    // The related fields

    /**
     * Whether a value was sent with at least one filled key.
     */
    protected function submitted(LatheRequest $request): bool
    {
        $value = $request->input($this->attribute);

        return is_array($value) && array_filter($value, fn (mixed $item) => $item !== null && $item !== '') !== [];
    }

    /**
     * A blank model of the Resource holding this field, for reading the relation on a create.
     */
    protected function ownerModel(): ?Model
    {
        return $this->owner === null ? null : $this->owner::newModel();
    }

    /**
     * The related Resource's fields of a context this user may see, without the one that
     * points back at the parent: the parent's save sets that link.
     *
     * @return list<Field>
     */
    protected function childFields(FieldContext $context, LatheRequest $request, Model $parent, ?Model $related): array
    {
        $class = $this->relatedResource();
        $relation = $parent->{$this->attribute}();

        if ($class === null || ! $relation instanceof Relation) {
            return [];
        }

        $model = $related ?? $class::newModel();

        return array_values($class::make($related)->fieldsFor($context, $request)
            ->forContext($context, $request, $related)
            ->authorized($request)
            ->reject(fn (Field $field) => $field instanceof RelationField && $field->pointsBackThrough($model, $relation, $this->attribute))
            ->all());
    }

    /**
     * The related fields a save validates and fills: not read-only, not computed, not hidden
     * by visibleWhen.
     *
     * @return list<Field>
     */
    protected function writableChildFields(LatheRequest $request, Model $parent, ?Model $related): array
    {
        $context = $related === null ? FieldContext::Create : FieldContext::Update;

        return array_values(array_filter(
            $this->childFields($context, $request, $parent, $related),
            fn (Field $field) => ! $field->isComputed() && ! $field->isReadonly($request, $related) && $field->isVisibleFor($request, $related),
        ));
    }
}
