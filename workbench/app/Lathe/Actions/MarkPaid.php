<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;

class MarkPaid extends Action
{
    public function __construct()
    {
        parent::__construct();

        $this->withName('Mark paid')->group('Fulfilment')->icon('check-circle')->confirmable('Mark :count orders as paid?');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        foreach ($models as $order) {
            $order->forceFill(['status' => 'paid'])->save();
        }

        return parent::handle($fields, $models);
    }
}
