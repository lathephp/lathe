<?php

namespace Lathe\ActionEvents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lathe\Lathe;

/**
 * Gives a model the action events written about it.
 *
 * @mixin Model
 */
trait Actionable
{
    /**
     * @return MorphMany<ActionEvent, $this>
     */
    public function actions(): MorphMany
    {
        /** @var class-string<ActionEvent> $model */
        $model = Lathe::actionEventModel();

        return $this->morphMany($model, 'actionable');
    }
}
