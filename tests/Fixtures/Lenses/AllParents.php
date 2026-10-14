<?php

namespace Lathe\Tests\Fixtures\Lenses;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;

class AllParents extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name')];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }
}
