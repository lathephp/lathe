<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Actions\ActionMenu;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The Actions menu of a Resource and the modal form of one of its actions.
 */
class ActionController
{
    use AuthorizesResources;

    /**
     * The visible actions for a display, metadata only. With resourceId each action says
     * whether the user may run it on that record, read through the detail query.
     */
    public function index(LatheRequest $request, ResourceAuthorization $authorization, ActionMenu $menu): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $request->validate([
            'display' => ['required', 'in:index,detail,table-row'],
            'resourceId' => ['nullable', 'string'],
        ]);
        $resourceId = $request->query('resourceId');
        $model = is_string($resourceId) ? (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId) : null;

        return new JsonResponse($menu->build($class, (string) $request->query('display'), $model));
    }

    /**
     * The modal of one action: its fields, its confirm text with :count filled in, and its
     * metadata. 404 for an unknown action, 403 for one canSee hides.
     */
    public function form(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $action): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $found = $class::make()->availableActions($request)->findByUriKey($action);

        abort_if($found === null, 404);
        abort_unless($found->authorizedToSee($request), 403);

        $fields = (new FieldCollection(array_values(array_filter($found->fields($request), fn (mixed $field) => $field instanceof Field))))
            ->forContext(FieldContext::Create, $request)
            ->authorized($request);
        $metadata = $found->jsonSerialize();
        $count = $request->query('count');

        return new JsonResponse([
            'fields' => array_values($fields->map(fn (Field $field) => [
                ...$field->schema(FieldContext::Create, $request),
                'value' => $field->resolveDefaultValue($request),
            ])->all()),
            'confirmText' => is_numeric($count) ? $found->confirmTextFor((int) $count) : $metadata['confirmText'],
            'action' => $metadata,
        ]);
    }
}
