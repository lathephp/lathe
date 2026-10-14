<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;

/**
 * A pivot action of a product's Tags panel: the ticked tags move to position 1 for that product.
 */
class MoveToFirst extends Action
{
    public function __construct()
    {
        parent::__construct();

        $this->withName('Move to First')->withoutConfirmation();
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        foreach ($models as $tag) {
            $pivot = $tag->getRelation('pivot');

            if ($pivot instanceof Pivot) {
                $pivot->forceFill(['position' => 1])->save();
            }
        }

        return ActionResult::message('Moved to first.');
    }
}
