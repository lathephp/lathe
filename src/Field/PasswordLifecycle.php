<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Support\Facades\Hash;

/**
 * A password leaves as an empty string, so the hash never reaches a page. A non-empty
 * value is hashed on save and an empty one keeps the stored hash.
 */
class PasswordLifecycle extends DefaultLifecycle
{
    public function present(mixed $raw, object $record, string $attribute): mixed
    {
        return '';
    }

    public function persist(object $model, string $attribute, mixed $value): ?Closure
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return parent::persist($model, $attribute, Hash::make($value));
    }
}
