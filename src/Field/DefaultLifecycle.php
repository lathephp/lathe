<?php

namespace Lathe\Field;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lathe\Http\Requests\LatheRequest;
use UnitEnum;

/**
 * Eloquent attributes through their casts. Dates leave as UTC ISO 8601 and integers past
 * 2^53 - 1 as strings, so JavaScript never rounds them.
 */
class DefaultLifecycle implements FieldLifecycle
{
    /** The largest integer a JavaScript number holds exactly. */
    public const int MAX_SAFE_INTEGER = 9007199254740991;

    public function extract(object $record, string $attribute): mixed
    {
        return data_get($record, $attribute);
    }

    public function present(mixed $raw, object $record, string $attribute): mixed
    {
        return self::wire($raw);
    }

    public function parse(LatheRequest $request, string $attribute): mixed
    {
        return $request->input($attribute);
    }

    public function persist(object $model, string $attribute, mixed $value): ?Closure
    {
        if ($model instanceof Model) {
            $model->setAttribute($attribute, $value);
        } else {
            data_set($model, $attribute, $value);
        }

        return null;
    }

    /**
     * The JSON-safe form of a PHP value.
     */
    public static function wire(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => Carbon::instance($value)->utc()->toIso8601ZuluString(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            is_int($value) && ($value > self::MAX_SAFE_INTEGER || $value < -self::MAX_SAFE_INTEGER) => (string) $value,
            default => $value,
        };
    }
}
