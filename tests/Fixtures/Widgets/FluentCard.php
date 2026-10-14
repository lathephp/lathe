<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

class FluentCard extends Widget
{
    public function __construct()
    {
        parent::__construct();
        $this->width(CardWidth::Half)->refreshEvery(10)->refreshWhenActionsRun()->refreshWhenFiltersChange();
    }

    public function cacheFor(): int
    {
        return 30;
    }

    public function calculate(LatheRequest $request): mixed
    {
        return 1;
    }
}
