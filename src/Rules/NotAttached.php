<?php

namespace Lathe\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lathe\Lathe;

/**
 * The related id has no pivot row on the parent's relation yet.
 */
class NotAttached implements ValidationRule
{
    /**
     * @param  BelongsToMany<Model, Model>  $relation
     */
    public function __construct(protected BelongsToMany $relation)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((is_int($value) || is_string($value)) && $this->relation->newPivotStatementForId($value)->exists()) {
            $fail(Lathe::__('This :attribute is already attached.'));
        }
    }
}
