<?php

namespace Lathe\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The stale-edit token: the record's stored updated_at, which an edit form sends back.
 */
class TrafficCop
{
    /**
     * The stored updated_at as UTC ISO 8601 with milliseconds, or null without timestamps.
     */
    public static function token(Model $model): ?string
    {
        $column = $model->getUpdatedAtColumn();

        if (! $model->usesTimestamps() || $column === null) {
            return null;
        }

        $value = $model->getAttribute($column);

        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->utc()->format('Y-m-d\TH:i:s.v\Z')
            : null;
    }

    /**
     * Whether a token the form sent no longer matches the stored record. A write that sends no
     * token is not checked.
     */
    public static function isStale(Model $model, mixed $sent): bool
    {
        return $sent !== null && $sent !== '' && $sent !== static::token($model);
    }
}
