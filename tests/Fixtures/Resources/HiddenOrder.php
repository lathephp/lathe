<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Resources\ResourceDefinition;

/**
 * An orders Resource that exists but is hidden from every user.
 */
class HiddenOrder extends WidgetOrder
{
    public static function uriKey(): string
    {
        return 'hidden-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canSee(fn () => false);
    }
}
