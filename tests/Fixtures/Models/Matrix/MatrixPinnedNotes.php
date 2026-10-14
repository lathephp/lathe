<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

/**
 * The same note rows as the parent's morphOne, which names its own relation as the inverse.
 */
class MatrixPinnedNotes extends MatrixNotes
{
    public static function uriKey(): string
    {
        return 'mx-pinned-notes';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphTo('Notable')->types([MatrixParents::class])->inverse('pinned'),
            Field::text('Body')->rules('required', 'max:20'),
        ];
    }
}
