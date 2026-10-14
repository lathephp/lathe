<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * The notes of a parent, the other end of its morphMany.
 */
class MatrixNotes extends Resource
{
    public static ?string $policy = MatrixPolicy::class;

    public static function uriKey(): string
    {
        return 'mx-notes';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxNote::class)->title('body')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphTo('Notable')->types([MatrixParents::class])->inverse('notes'),
            Field::text('Body')->rules('required', 'max:20'),
        ];
    }
}
