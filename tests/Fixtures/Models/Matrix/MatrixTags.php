<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * Tags, the related end of the parent's pivot relations and the owner of the morphedByMany.
 */
class MatrixTags extends Resource
{
    public static ?string $policy = MatrixPolicy::class;

    public static function uriKey(): string
    {
        return 'mx-tags';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxTag::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
            Field::morphedByMany('Parents', 'parents', MatrixParents::class)
                ->pivotFields([Field::number('Position')->min(0)->required()])
                ->showOnCreating()
                ->showOnUpdating(),
        ];
    }
}
