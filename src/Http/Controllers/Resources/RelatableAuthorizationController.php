<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The add and attach flags of every relation field on a detail page, in one batch:
 * GET resources/{resource}/relate-authorization?resourceId=.
 */
class RelatableAuthorizationController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $resourceId = $request->query('resourceId');
        $model = is_string($resourceId) && $resourceId !== ''
            ? (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId)
            : $class::newModel();
        $user = $request->user();
        $flags = [];

        foreach ($class::make($model)->fieldsFor(FieldContext::Detail, $request) as $field) {
            if (! $field instanceof RelationField) {
                continue;
            }

            $relation = Str::studly($field->attribute());

            // attach{Relation} takes the ids to attach; none are chosen yet.
            $flags[$field->attribute()] = [
                'add' => $authorization->allows($user, $class, 'add'.$relation, $model),
                'attach' => $authorization->allows($user, $class, 'attach'.$relation, $model, [[]]),
                'attachAny' => $authorization->allows($user, $class, 'attachAny'.$relation, $model),
                'detach' => $authorization->allows($user, $class, 'detach'.$relation, $model, [[]]),
            ];
        }

        return new JsonResponse((object) $flags);
    }
}
