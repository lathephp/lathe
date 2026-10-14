<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Attributes\Widget\CacheFor;
use Lathe\Attributes\Widget\RefreshEvery;
use Lathe\Attributes\Widget\RefreshWhenActionsRun;
use Lathe\Attributes\Widget\RefreshWhenFiltersChange;
use Lathe\Attributes\Widget\Width;
use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

#[Width(CardWidth::Half)]
#[RefreshEvery(10)]
#[CacheFor(30)]
#[RefreshWhenActionsRun]
#[RefreshWhenFiltersChange]
class AttributedCard extends Widget
{
    public function calculate(LatheRequest $request): mixed
    {
        return 1;
    }
}
