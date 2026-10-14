<?php

namespace Lathe\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lathe\Lathe;

/**
 * The related id has no pivot row with these same pivot values, for a relation that allows duplicates.
 */
class NotExactlyAttached implements ValidationRule
{
    /**
     * @param  BelongsToMany<Model, Model>  $relation
     * @param  array<string, mixed>  $pivot  the pivot values the attach would write
     */
    public function __construct(protected BelongsToMany $relation, protected array $pivot)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((is_int($value) || is_string($value)) && $this->relation->newPivotStatementForId($value)->where($this->pivot)->exists()) {
            $fail(Lathe::__('This :attribute is already attached with the same values.'));
        }
    }
}
