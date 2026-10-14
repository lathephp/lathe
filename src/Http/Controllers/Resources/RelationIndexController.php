<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Payloads\RelationIndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The rows of a relation panel: GET resources/{resource}/{resourceId}/{relationship}.
 */
class RelationIndexController
{
    use AuthorizesResources;

    /** The relation kinds a panel lists; a single relation or a tag field has no list. */
    public const array PANEL_KINDS = ['hasMany', 'morphMany', 'belongsToMany', 'morphToMany', 'morphedByMany', 'hasManyThrough', 'hasOneThrough'];

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, RelationIndexPayload $payload, string $resource, string $resourceId, string $relationship): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $parent = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'view', $parent), 403, $authorization->denialMessage($class));

        $field = $class::make()->fieldsFor(FieldContext::Detail, $request)->first(
            fn (Field $field) => $field instanceof RelationField && $field->attribute() === $relationship && in_array($field->kind(), self::PANEL_KINDS, true),
        );

        abort_unless($field instanceof RelationField, 404);
        abort_unless($field->authorizedToSee($request), 403);

        $related = $field->relatedResource();
        $relation = method_exists($parent, $relationship) ? $parent->{$relationship}() : null;

        abort_unless($related !== null && $relation instanceof Relation, 404);
        abort_unless($authorization->allows($request->user(), $related, 'viewAny'), 403, $authorization->denialMessage($related));

        return new JsonResponse($payload->build($related, $parent, $field, $relation));
    }
}
