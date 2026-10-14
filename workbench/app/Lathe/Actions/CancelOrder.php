<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Actions\DestructiveAction;

class CancelOrder extends DestructiveAction
{
    public function __construct()
    {
        parent::__construct();

        $this->confirmText('Cancel :count order?');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        foreach ($models as $order) {
            $order->forceFill(['status' => 'cancelled'])->save();
        }

        return parent::handle($fields, $models);
    }
}
