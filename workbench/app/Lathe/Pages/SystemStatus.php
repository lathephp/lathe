<?php

namespace Workbench\App\Lathe\Pages;

use Lathe\Pages\Page;

/**
 * Server facts for whoever may view system status.
 */
class SystemStatus extends Page
{
    public function __construct()
    {
        $this->canSeeWhen('view system status');
    }
}
