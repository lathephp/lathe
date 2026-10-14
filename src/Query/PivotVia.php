<?php

namespace Lathe\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;
use Lathe\Actions\ActionCollection;
use Lathe\Actions\ActionMenu;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\BelongsToManyField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The parent record and pivot relation a selection's via names, as {parentUriKey}/{id}/{relationship}:
 * the related Resource's rows are read through that relation, so an action runs on the parent's
 * pivot rows only. A via the user may not follow answers 422.
 */
class PivotVia
{
    /**
     * @param  BelongsToMany<Model, Model>  $relation
     */
    public function __construct(
        public readonly Model $parent,
        public readonly BelongsToManyField $field,
        public readonly BelongsToMany $relation,
    ) {
        //
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $related  the Resource the selection's rows belong to
     */
    public static function resolve(LatheRequest $request, string $related, string $via): self
    {
        $parts = explode('/', $via);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw self::invalid();
        }

        [$uriKey, $id, $relationship] = $parts;
        $class = app(ResourceRegistry::class)->forKey($uriKey) ?? throw self::invalid();
        $authorization = app(ResourceAuthorization::class);

        try {
            $parent = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($id);
        } catch (HttpException) {
            throw self::invalid();
        }

        if (! $authorization->canSee($request, $class) || ! $authorization->allows($request->user(), $class, 'view', $parent)) {
            throw self::invalid();
        }

        $field = $class::make($parent)->fieldsFor(FieldContext::Detail, $request)->first(
            fn (Field $field) => $field instanceof BelongsToManyField && $field->attribute() === $relationship,
        );
        $relation = method_exists($parent, $relationship) ? $parent->{$relationship}() : null;

        if (! $field instanceof BelongsToManyField || ! $field->authorizedToSee($request) || $field->relatedResource() !== $related || ! $relation instanceof BelongsToMany) {
            throw self::invalid();
        }

        return new self($parent, $field, $relation);
    }

    /**
     * The pivot actions the parent's field declares.
     */
    public function actions(LatheRequest $request): ActionCollection
    {
        return new ActionCollection((new ActionMenu($request, app(ResourceAuthorization::class)))->fieldPivotActions($this->field));
    }

    protected static function invalid(): ValidationException
    {
        return ValidationException::withMessages(['selection' => Lathe::__('The via of the selection is not valid.')]);
    }
}
