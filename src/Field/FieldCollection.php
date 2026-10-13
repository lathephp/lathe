<?php

namespace Lathe\Field;

use Illuminate\Support\Collection;
use Lathe\Http\Requests\LatheRequest;

/**
 * The frozen fields of one Resource for one request.
 *
 * @extends Collection<int, Field>
 */
class FieldCollection extends Collection
{
    /**
     * The fields shown in a context. Index never passes a record.
     */
    public function forContext(FieldContext $context, ?LatheRequest $request = null, mixed $model = null): static
    {
        return $this->filter(fn (Field $field) => $field->isShownOn($context, $request, $model))->values();
    }

    /**
     * Drop the fields whose canSee refuses this request.
     */
    public function authorized(LatheRequest $request): static
    {
        return $this->filter(fn (Field $field) => $field->authorizedToSee($request))->values();
    }

    public function withoutComputed(): static
    {
        return $this->reject(fn (Field $field) => $field->isComputed())->values();
    }

    public function sortable(): static
    {
        return $this->filter(fn (Field $field) => $field->isSortable())->values();
    }

    /**
     * Only the fields with these keys, so no other field's closure runs.
     *
     * @param  list<string>|null  $keys
     */
    public function onlyKeys(?array $keys): static
    {
        return $keys === null ? $this : $this->filter(fn (Field $field) => in_array($field->key(), $keys, true))->values();
    }

    public function findByAttribute(string $attribute): ?Field
    {
        return $this->first(fn (Field $field) => $field->attribute() === $attribute);
    }
}
