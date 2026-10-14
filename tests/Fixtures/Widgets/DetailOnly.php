<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Attributes\Widget\OnlyOnDetail;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

#[OnlyOnDetail]
class DetailOnly extends Widget
{
    public function calculate(LatheRequest $request): mixed
    {
        return 'detail '.$request->query('resourceId');
    }
}
