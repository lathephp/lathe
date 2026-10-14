<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Field\Evaluator;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Rules\Relatable;
use Lathe\Support\ConfigStore;

/**
 * The parent of a morphTo relation: a type select, then a record picker. The client sends a
 * Resource uriKey as the type, never a class name.
 */
class MorphToField extends RelationField implements FilterableField
{
    protected string $component = 'morph-to-field';

    /** @var list<class-string<resource>> */
    protected array $types = [];

    /** @var class-string<resource>|null */
    protected ?string $defaultResource = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options['withTrashed'] = true;
    }

    public function kind(): string
    {
        return 'morphTo';
    }

    /**
     * @param  list<class-string<resource>>  $resources
     */
    public function types(array $resources): static
    {
        $this->mutate();
        $this->types = $resources;

        return $this;
    }

    /**
     * @return list<class-string<resource>>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param  class-string<resource>  $class
     */
    public function defaultResource(string $class): static
    {
        $this->mutate();
        $this->defaultResource = $class;

        return $this;
    }

    /**
     * @return class-string<resource>|null
     */
    public function getDefaultResource(): ?string
    {
        return $this->defaultResource;
    }

    public function displaysWithTrashed(bool $show = true): static
    {
        return $this->withTrashed($show);
    }

    /**
     * The type whose uriKey the client sent; null for anything outside types(), a class name included.
     *
     * @return class-string<resource>|null
     */
    public function resourceForType(?string $uriKey): ?string
    {
        foreach ($this->types as $type) {
            if ($type::uriKey() === $uriKey) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return class-string<resource>|null
     */
    protected function relatedResourceFor(Model $related): ?string
    {
        foreach ($this->types as $type) {
            if ($type::model() === $related::class) {
                return $type;
            }
        }

        return null;
    }

    public function relatedResource(): ?string
    {
        return $this->resourceClass;
    }

    public function loadsWithRecord(): bool
    {
        return true;
    }

    /**
     * Each soft-deleting type loads with its trashed rows, so a trashed parent still shows.
     */
    public function eagerLoad(): array
    {
        $types = $this->types;

        return [$this->attribute => function (Relation $relation) use ($types): void {
            if (! $relation instanceof MorphTo) {
                return;
            }

            $constraints = [];

            foreach ($types as $type) {
                if (in_array(SoftDeletes::class, class_uses_recursive($type::model()), true)) {
                    $constraints[$type::model()] = fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class);
                }
            }

            $relation->constrain($constraints);
        }];
    }

    /**
     * @return array{type: string, id: mixed, display: string, href: string|null}|null
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $related = $record instanceof Model ? $this->relatedRecord($record) : null;
        $type = $related !== null ? $this->relatedResourceFor($related) : null;

        if ($related === null || $type === null) {
            return null;
        }

        $key = $related->getKey();
        $viewable = Container::getInstance()->make(ResourceAuthorization::class)
            ->allows(Evaluator::currentRequest()?->user(), $type, 'view', $related);
        $base = '/'.trim(Container::getInstance()->make(ConfigStore::class)->string('path'), '/');

        return [
            'type' => $type::uriKey(),
            'id' => $key,
            'display' => $this->displayFor($related),
            'href' => $viewable && (is_int($key) || is_string($key)) ? rtrim($base, '/').'/resources/'.$type::uriKey().'/'.$key : null,
        ];
    }

    /**
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolve($model);

        return ['value' => $value, 'display' => $value['display'] ?? ''];
    }

    /**
     * Write {attr}_type as the chosen type's morph alias and {attr}_id; the type is a uriKey
     * the rules already checked against types().
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        if (! $model instanceof Model || ($model->exists && $this->isImmutable($request, $model))) {
            return null;
        }

        $value = $request->input($requestAttribute ?? $this->attribute);
        $type = is_array($value) && is_string($value['type'] ?? null) ? $this->resourceForType($value['type']) : null;
        $id = is_array($value) ? ($value['id'] ?? $value['value'] ?? null) : null;
        $relation = $model->{$this->attribute}();

        if (! $relation instanceof MorphTo) {
            return null;
        }

        $model->setAttribute($relation->getMorphType(), $type === null || $id === null || $id === '' ? null : $type::newModel()->getMorphClass());
        $model->setAttribute($relation->getForeignKeyName(), $type === null || $id === '' ? null : $id);

        return null;
    }

    /**
     * Required unless nullable().
     */
    public function isRequired(?LatheRequest $request = null, mixed $model = null): bool
    {
        return ! $this->isNullable() || parent::isRequired($request, $model);
    }

    /**
     * The type must be one of types(), by uriKey; the record must be one the picker offers.
     */
    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $rules = parent::getRules($context, $request, $model);
        $rules[$this->attribute][] = function (string $attribute, mixed $value, Closure $fail): void {
            $empty = $value === null || $value === '' || (is_array($value) && in_array($value['id'] ?? null, [null, ''], true));

            if ($empty && $this->isNullable()) {
                return;
            }

            if (! is_array($value) || ! is_string($value['type'] ?? null) || $this->resourceForType($value['type']) === null) {
                $fail(Lathe::__('The :attribute type is not one of its types.'));
            }
        };

        if ($request !== null && $this->owner !== null) {
            $rules[$this->attribute][] = new Relatable($request, new Associatable($request, $this->owner, $this), $this, $model);
        }

        return $rules;
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'types' => array_map(fn (string $class) => ['value' => $class::uriKey(), 'label' => $class::singularLabel()], $this->types),
            'defaultType' => $this->defaultResource !== null ? $this->defaultResource::uriKey() : null,
        ];
    }
}
