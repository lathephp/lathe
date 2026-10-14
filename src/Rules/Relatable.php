<?php

namespace Lathe\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
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

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = is_array($value) ? ($value['value'] ?? $value['id'] ?? null) : $value;
        $type = is_array($value) ? ($value['type'] ?? null) : null;

        // {value: ''} is not an empty value to the validator, so the nullable skip is made here.
        if (($key === null || $key === '') && $this->field->isNullable()) {
            return;
        }

        $query = $this->field instanceof MorphToField
            ? $this->associatable->forType(is_string($type) ? $type : null)->query()
            : $this->associatable->query();

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
