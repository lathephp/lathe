<?php

namespace Lathe\Field;

use Illuminate\Database\Eloquent\Model;

/**
 * Turns fields plus a record into wire values, in one pass per row.
 */
class FieldResolver
{
    /**
     * The index cells of one record, keyed by field key.
     *
     * @param  iterable<Field>  $fields
     * @return array<string, array{value: mixed, display: string}>
     */
    public static function cells(Model $model, iterable $fields): array
    {
        $cells = [];

        foreach ($fields as $field) {
            $cells[$field->key()] = $field->cell($model);
        }

        return $cells;
    }
}
