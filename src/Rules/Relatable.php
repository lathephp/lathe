<?php

namespace Lathe\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Relations\Associatable;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * A relation value must be a record the field's picker offers, and the user must be
 * allowed to add that relation on the parent. The value is a key or {value: key}.
 */
class Relatable implements ValidationRule
{
    public function __construct(
        protected LatheRequest $request,
        protected Associatable $associatable,
        protected RelationField $field,
        protected ?Model $parent = null,
    ) {
        //
    }

    /**
     * Whether the value is the one the saved parent record already holds.
     */
    protected function unchanged(mixed $key, mixed $type): bool
    {
        if ($this->parent === null || ! $this->parent->exists || (! is_int($key) && ! is_string($key))) {
            return false;
        }

        $relation = $this->parent->{$this->field->attribute()}();

        if ($relation instanceof MorphTo) {
            $class = $this->field instanceof MorphToField && is_string($type) ? $this->field->resourceForType($type) : null;

            return $class !== null
                && $this->parent->getAttribute($relation->getMorphType()) === $class::newModel()->getMorphClass()
                && self::same($this->parent->getAttribute($relation->getForeignKeyName()), $key);
        }

        return $relation instanceof BelongsTo && self::same($this->parent->getAttribute($relation->getForeignKeyName()), $key);
    }

    protected static function same(mixed $stored, int|string $key): bool
    {
        return (is_int($stored) || is_string($stored)) && (string) $stored === (string) $key;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = is_array($value) ? ($value['value'] ?? $value['id'] ?? null) : $value;
        $type = is_array($value) ? ($value['type'] ?? null) : null;

        // {value: ''} is not an empty value to the validator, so the nullable skip is made here.
        if (($key === null || $key === '') && $this->field->isNullable()) {
            return;
        }

        // A record keeps the parent it has, even one trashed or out of the picker since.
        if ($this->unchanged($key, $type)) {
            return;
        }

        $saved = $this->associatable->forSavedValue();
        $query = $this->field instanceof MorphToField
            ? $saved->forType(is_string($type) ? $type : null)->query()
            : $saved->query();

        if (! is_string($key) && ! is_int($key) || $query === null || $query->option((string) $key) === null) {
            $fail(Lathe::__('The :attribute must be a valid relation.'));

            return;
        }

        $owner = $this->field->owner();

        if ($owner === null) {
            return;
        }

        $ability = 'add'.Str::studly($this->field->attribute());

        if (! app(ResourceAuthorization::class)->allows($this->request->user(), $owner, $ability, $this->parent ?? $owner::newModel())) {
            $fail(Lathe::__('You are not authorized to relate this resource.'));
        }
    }
}
