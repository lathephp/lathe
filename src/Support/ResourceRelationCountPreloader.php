<?php

namespace Lathe\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Loads the related counts of a page of records in one query per relation, never one per row.
 */
class ResourceRelationCountPreloader
{
    /**
     * @param  Collection<array-key, Model>  $records
     * @param  list<string>  $relations
     */
    public static function preload(Collection $records, array $relations): void
    {
        $missing = array_values(array_filter(
            $relations,
            fn (string $relation) => $records->contains(fn (Model $record) => ! array_key_exists($relation.'_count', $record->getAttributes())),
        ));

        if ($missing === [] || $records->isEmpty()) {
            return;
        }

        (new EloquentCollection($records->all()))->loadCount($missing);
    }
}
