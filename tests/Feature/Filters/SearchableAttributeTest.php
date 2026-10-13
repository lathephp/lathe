<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Lathe\Attributes\Filter\Searchable;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;

class PlainSmallFilter extends Filter
{
    public function options(LatheRequest $request): array
    {
        return ['One' => 1];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }
}

#[Searchable]
class AttributeSearchableFilter extends PlainSmallFilter {}

it('serves a filter marked #[Filter\Searchable] as a remote picker', function () {
    $request = LatheRequest::createFrom(Request::create('/'));

    expect((new AttributeSearchableFilter)->isRemote($request, 100))->toBeTrue()
        ->and((new PlainSmallFilter)->isRemote($request, 100))->toBeFalse();
});
