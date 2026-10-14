<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Actions\Action;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Dependency;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;

/**
 * The lookups of an action's modal: the dependent-field reply POST /field-dependencies gives
 * a Resource form, and the remote picker of an action's relation field.
 */
class ActionLookupController extends AssociatableController
{
    /**
     * PATCH /action?action=&field=: the field the user changed, with the modal's values; the
     * reply is the one POST /field-dependencies gives a Resource form.
     */
    public function dependencies(LatheRequest $request, ResourceAuthorization $authorization, DependencyController $engine): JsonResponse
    {
        $request->validate([
            'values' => ['array'],
            'dirty' => ['array'],
            'dirty.*' => ['string'],
        ]);
        $action = $this->action($request, $authorization);
        /** @var array<string, Field> $fields */
        $fields = [];

        foreach ($this->fields($request, $action) as $field) {
            $fields[$field->key()] = $field;
        }

        // The fields to recompute: those whose dependsOn names the field the user changed.
        $changed = $request->query('field');
        $request->merge(['fields' => is_string($changed) ? array_keys(array_filter($fields, fn (Field $field) => array_filter(
            $field->dependencies(),
            fn (Dependency $dependency) => in_array($changed, $dependency->attributes, true),
        ) !== [])) : $request->input('fields', [])]);

        return new JsonResponse($engine->lookup($request, $fields, FieldContext::Create));
    }

    /**
     * GET /action/associatable?action=&field=: one page of an action relation field's picker.
     */
    public function associatable(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $action = $this->action($request, $authorization);
        $field = $request->query('field');

        return $this->options($request, $class, $this->relationField($request, $this->fields($request, $action), is_string($field) ? $field : ''));
    }

    /**
     * The action the query names: 404 when unknown, 403 when hidden.
     */
    protected function action(LatheRequest $request, ResourceAuthorization $authorization): Action
    {
        $class = $this->viewableResource($request, $authorization);
        $key = $request->query('action');
        $action = $class::make()->availableActions($request)->findByUriKey(is_string($key) ? $key : '');

        abort_if($action === null, 404);
        abort_unless($action->authorizedToSee($request), 403);

        return $action;
    }

    /**
     * The action's modal fields the user may see.
     */
    protected function fields(LatheRequest $request, Action $action): FieldCollection
    {
        return (new FieldCollection(array_values(array_filter($action->fields($request), fn (mixed $field) => $field instanceof Field))))
            ->forContext(FieldContext::Create, $request)
            ->authorized($request);
    }
}
