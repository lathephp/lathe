<?php

namespace Lathe\Tests\Fixtures\Plugins;

use Lathe\Plugins\Plugin;

class BillingPlugin extends Plugin
{
    public function requires(): array
    {
        return ['Acme\\Missing\\Ledger', 'Acme\\Missing\\Tax', Beacon::class];
    }
}
