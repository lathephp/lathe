<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class MatrixGrandchildren extends Resource
{
    public static ?string $policy = MatrixGrandchildPolicy::class;

    public static function uriKey(): string
    {
        return 'mx-grandchildren';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxGrandchild::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}
