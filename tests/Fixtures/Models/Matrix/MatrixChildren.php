<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class MatrixChildren extends Resource
{
    public static ?string $policy = MatrixPolicy::class;

    public static function uriKey(): string
    {
        return 'mx-children';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxChild::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
            Field::belongsTo('Parent', 'parent', MatrixParents::class),
        ];
    }
}
