<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;
use Lathe\Resources\Resource;

/**
 * Fires inside the transaction after a Resource's record is restored, so a throwing listener rolls it back.
 */
class ResourceRestored
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public function __construct(public Model $model, public string $resource)
    {
        //
    }
}
