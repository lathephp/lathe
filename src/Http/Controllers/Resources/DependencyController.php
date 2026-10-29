<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Dependency;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\FieldPatch;
use Lathe\Field\FormData;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * The dependsOn lookup: run the callbacks of the named fields against the values the user
 * has now, then of the fields their new values reach, at most 5 rounds deep.
 */
class DependencyController
{
    use AuthorizesResources;

    /** How many rounds a cascade of setValue() may run. */
    public const int MAX_DEPTH = 5;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, string $resource): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $request->validate([
            'fields' => ['required', 'array', 'max:20'],
            'fields.*' => ['string'],
            'context' => ['required', 'in:create,update'],
            'values' => ['array'],
            'dirty' => ['array'],
            'dirty.*' => ['string'],
            'id' => ['nullable'],
        ]);
        $context = $request->input('context') === 'update' ? FieldContext::Update : FieldContext::Create;
        $model = $this->model($request, $authorization, $class, $context, $request->input('id'));
        /** @var array<string, Field> $fields */
        $fields = [];

        foreach ($class::make($model)->fieldsFor($context, $request)->forContext($context, $request, $model)->authorized($request) as $field) {
            $fields[$field->key()] = $field;
        }

        return new JsonResponse($this->lookup($request, $fields, $context));
    }

    /**
     * Runs the dependsOn callbacks of the named fields, then of the fields their new values
     * reach. An action's modal asks the same through PATCH /action.
     *
     * @param  array<string, Field>  $fields  by key
     * @return array{patches: object, changed: object}
     */
    public function lookup(LatheRequest $request, array $fields, FieldContext $context): array
    {
        $values = $request->input('values');
        $values = is_array($values) ? $values : [];
        $dirty = array_values(array_filter((array) $request->input('dirty', []), is_string(...)));
        $queue = array_values(array_filter((array) $request->input('fields', []), fn (mixed $key) => is_string($key) && isset($fields[$key])));
        $patches = [];
        $changed = [];
        $seen = [];

        for ($depth = 0; $queue !== [] && $depth < self::MAX_DEPTH; $depth++) {
            $moved = [];

            foreach ($queue as $key) {
                // A cycle stops at its first repeat.
                $seen[$key] = true;
                $field = $fields[$key];
                $patch = new FieldPatch($field);

                foreach ($field->dependencies() as $dependency) {
                    if ($dependency->appliesTo($context)) {
                        $this->call($dependency, $patch, $request, new FormData($this->keys($values, $fields), $request));
                    }
                }

                // A patch never overwrites what the user typed.
                if ($patch->hasValue() && in_array($key, $dirty, true)) {
                    $patch->withoutValue();
                }

                if ($patch->hasValue()) {
                    $values[$field->attribute()] = $changed[$key] = $patch->value();
                    $moved[] = $field->attribute();
                }

                $patches[$key] = $patch;
            }

            $queue = array_keys(array_filter($fields, fn (Field $field, string $key) => ! isset($seen[$key]) && array_filter(
                $field->dependencies(),
                fn (Dependency $dependency) => $dependency->appliesTo($context) && array_intersect($dependency->attributes, $moved) !== [],
            ) !== [], ARRAY_FILTER_USE_BOTH));
        }

        return ['patches' => (object) $patches, 'changed' => (object) $changed];
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function model(LatheRequest $request, ResourceAuthorization $authorization, string $class, FieldContext $context, mixed $id): ?Model
    {
        if ($context === FieldContext::Create || ! is_scalar($id) || $id === '') {
            abort_unless($authorization->allows($request->user(), $class, 'create'), 403, $authorization->denialMessage($class));

            return null;
        }

        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find((string) $id);

        abort_unless($authorization->allows($request->user(), $class, 'update', $model), 403, $authorization->denialMessage($class));

        return $model;
    }

    /**
     * Call fn (FieldPatch $field, LatheRequest $request, FormData $formData), its parameters
     * matched by type, then by name, then by position.
     */
    /**
     * The values with each relation picker's {value, display} as its key, as fill() reads it.
     *
     * @param  array<array-key, mixed>  $values
     * @param  array<string, Field>  $fields
     * @return array<array-key, mixed>
     */
    private function keys(array $values, array $fields): array
    {
        foreach ($fields as $field) {
            $value = $values[$field->attribute()] ?? null;

            if ($field instanceof BelongsToField && is_array($value)) {
                $values[$field->attribute()] = $value['value'] ?? null;
            }
        }

        return $values;
    }

    protected function call(Dependency $dependency, FieldPatch $patch, LatheRequest $request, FormData $data): void
    {
        $byType = [FieldPatch::class => $patch, LatheRequest::class => $request, FormData::class => $data];
        $byName = ['field' => $patch, 'patch' => $patch, 'request' => $request, 'formData' => $data, 'data' => $data];
        $positional = [$patch, $request, $data];
        $arguments = [];

        foreach ((new ReflectionFunction($dependency->callback))->getParameters() as $index => $parameter) {
            $type = $parameter->getType();
            $name = $type instanceof ReflectionNamedType ? $type->getName() : null;
            $arguments[] = $byType[$name ?? ''] ?? $byName[$parameter->getName()] ?? $positional[$index] ?? null;
        }

        ($dependency->callback)(...$arguments);
    }
}
