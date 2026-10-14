<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;

class HoldOrder extends Action
{
    public function __construct()
    {
        parent::__construct();

        $this->showInline()->sole();
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::modal('hold-order', ['id' => $models->firstOrFail()->getKey()]);
    }
}
