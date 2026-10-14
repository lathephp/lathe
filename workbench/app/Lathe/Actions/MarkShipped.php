<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;

class MarkShipped extends Action
{
    public function __construct()
    {
        parent::__construct();

        $this->group('Fulfilment')->confirmable('Mark :count orders as shipped?');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        foreach ($models as $order) {
            $order->forceFill(['status' => 'shipped'])->save();
        }

        return parent::handle($fields, $models);
    }
}
