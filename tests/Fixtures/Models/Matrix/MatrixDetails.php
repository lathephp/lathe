<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class MatrixDetails extends Resource
{
    public static ?string $policy = MatrixPolicy::class;

    public static function uriKey(): string
    {
        return 'mx-details';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxDetail::class)->title('body')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Parent', 'parent', MatrixParents::class),
            Field::text('Body')->rules('required', 'max:20'),
        ];
    }
}
