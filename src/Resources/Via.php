<?php

namespace Lathe\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Field\Relations\HasOneField;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A create under ?via={parentUriKey}/{id}/{relationship}: the parent record, its relation
 * field, and the child's field that points back at it. The form locks that field to the
 * parent and the save forces it, whatever the client sent.
 */
class Via
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $parent
     */
    public function __construct(
        public readonly string $parent,
        public readonly Model $parentModel,
        public readonly RelationField $relation,
        public readonly BelongsToField|MorphToField|null $field,
    ) {
        //
    }

    /**
     * The request's via for a create of the child: null without one, a 422 on via when it
     * names no parent record and relation the user may see, a 403 without add{Relation}.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $child
     */
    public static function fromRequest(LatheRequest $request, string $child): ?self
    {
        $via = $request->input('via');

        if ($via === null) {
            return null;
        }

        $segments = is_string($via) ? explode('/', $via) : [];

        if (count($segments) !== 3 || in_array('', $segments, true)) {
            throw self::invalid();
        }

        [$uriKey, $id, $relationship] = $segments;
        $parent = app(ResourceRegistry::class)->forKey($uriKey) ?? throw self::invalid();

        try {
            $model = (new ResourceQuery($parent::make(), $request, QueryContext::Detail))->find($id);
        } catch (NotFoundHttpException) {
            throw self::invalid();
        }

        $authorization = app(ResourceAuthorization::class);

        if (! $authorization->allows($request->user(), $parent, 'view', $model)) {
            throw self::invalid();
        }

        $relation = $parent::make($model)->fieldsFor(FieldContext::Detail, $request)->first(
            fn (Field $field) => $field instanceof RelationField
                && $field->attribute() === $relationship
                && $field->createsThroughPanel()
                && $field->relatedResource() === $child,
        );

        if (! $relation instanceof RelationField) {
            throw self::invalid();
        }

        abort_unless($authorization->allows($request->user(), $parent, 'add'.Str::studly($relationship), $model), 403, $authorization->denialMessage($parent));

        // A hasOne or morphOne parent takes one child.
        if ($relation instanceof HasOneField && $relation->alreadyFilled($request, $model)) {
            throw ValidationException::withMessages(['already_filled' => Lathe::__('The :resource already has a :related.', [
                'resource' => $parent::singularLabel(),
                'related' => $relation->getSingularLabel(),
            ])]);
        }

        return new self($parent, $model, $relation, self::match($child, $request, $model, $relationship));
    }

    /**
     * The child's create field that points at the parent: the one naming the relation as
     * its inverse(), else the one writing the relation's foreign key.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $child
     */
    protected static function match(string $child, LatheRequest $request, Model $parent, string $relationship): BelongsToField|MorphToField|null
    {
        $fields = array_filter(
            $child::make()->fieldsFor(FieldContext::Create, $request)->all(),
            fn (Field $field) => $field instanceof BelongsToField || $field instanceof MorphToField,
        );
        $relation = $parent->{$relationship}();
        $model = $child::newModel();

        return Arr::first($fields, fn (BelongsToField|MorphToField $field) => $field->getInverse() === $relationship)
            ?? Arr::first($fields, fn (BelongsToField|MorphToField $field) => $relation instanceof Relation && $field->writesRelation($model, $relation));
    }

    protected static function invalid(): ValidationException
    {
        return ValidationException::withMessages(['via' => 'The via does not name a parent record and relation you may add to.']);
    }

    /**
     * Whether the field is the one locked to the parent.
     */
    public function locks(Field $field): bool
    {
        return $this->field !== null && $field->key() === $this->field->key();
    }

    /**
     * The parent's key as the child's foreign key holds it.
     */
    public function parentKey(): mixed
    {
        $relation = $this->parentModel->{$this->relation->attribute()}();

        return $relation instanceof HasOneOrMany ? $relation->getParentKey() : $this->parentModel->getKey();
    }

    /**
     * The locked field's form value: the parent's key and title.
     *
     * @return array<string, mixed>|null
     */
    public function value(): ?array
    {
        if ($this->field instanceof MorphToField) {
            return ['type' => $this->parent::uriKey(), 'id' => $this->parentKey(), 'display' => $this->field->displayFor($this->parentModel)];
        }

        return $this->field === null ? null : ['value' => $this->parentKey(), 'display' => $this->field->displayFor($this->parentModel)];
    }

    /**
     * Put the parent's key in the request under the locked field, so validation and the
     * fill both see it in place of what the client sent.
     */
    public function force(LatheRequest $request): void
    {
        if ($this->field instanceof MorphToField) {
            $request->merge([$this->field->attribute() => ['type' => $this->parent::uriKey(), 'id' => $this->parentKey()]]);
        } elseif ($this->field !== null) {
            $request->merge([$this->field->attribute() => ['value' => $this->parentKey()]]);
        }
    }
}
