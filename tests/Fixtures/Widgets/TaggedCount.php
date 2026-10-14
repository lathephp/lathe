<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

/**
 * Counts the tag pivot rows without an aggregate helper, so it names its own cache tag.
 */
class TaggedCount extends Widget
{
    public function calculate(LatheRequest $request): mixed
    {
        $this->cacheTags(['taggables']);

        return DB::table('taggables')->count();
    }
}
