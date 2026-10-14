<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

class Secret extends Widget
{
    public function __construct()
    {
        parent::__construct();
        $this->canSee(fn () => false);
    }

    public function calculate(LatheRequest $request): mixed
    {
        return 42;
    }
}
