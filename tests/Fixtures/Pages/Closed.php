<?php

namespace Lathe\Tests\Fixtures\Pages;

use Lathe\Pages\Page;

class Closed extends Page
{
    public function __construct()
    {
        $this->canSee(fn () => false);
    }
}
