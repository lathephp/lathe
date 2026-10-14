<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * The matrix parent with one field of every kind it owns.
 */
class MatrixParents extends Resource
{
    public static ?string $policy = MatrixPolicy::class;

    public static function uriKey(): string
    {
        return 'mx-parents';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxParent::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
            Field::hasOne('Detail', 'detail', MatrixDetails::class),
            Field::hasMany('Children', 'children', MatrixChildren::class),
            Field::belongsToMany('Tags', 'tags', MatrixTags::class)
                ->pivotFields([Field::number('Position')->min(0)->required()])
                ->showOnCreating()
                ->showOnUpdating(),
            Field::morphMany('Notes', 'notes', MatrixNotes::class),
            Field::morphOne('Pinned', 'pinned', MatrixPinnedNotes::class),
            Field::morphToMany('Labels', 'labels', MatrixTags::class)
                ->pivotFields([Field::number('Position')->min(0)->required()])
                ->showOnCreating()
                ->showOnUpdating(),
            Field::hasManyThrough('Grandchildren', 'grandchildren', MatrixGrandchildren::class),
            Field::hasOneThrough('First Grandchild', 'firstGrandchild', MatrixGrandchildren::class),
        ];
    }
}
