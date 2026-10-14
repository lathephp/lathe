<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Actions\Action;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Lenses\CountedOrders;

/**
 * An orders Resource the user may open, with a lens and an action it may not see.
 */
class GatedOrder extends WidgetOrder
{
    public static function uriKey(): string
    {
        return 'gated-orders';
    }

    public function lenses(LatheRequest $request): array
    {
        return [(new CountedOrders)->canSee(fn () => false)];
    }

    public function actions(LatheRequest $request): array
    {
        return [Action::make()->withName('Secret')->withUriKey('secret')->canSee(fn () => false)];
    }
}
