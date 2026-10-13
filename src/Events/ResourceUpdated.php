<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;
use Lathe\Resources\Resource;

/**
 * Fires inside the transaction after a Resource's record is updated, so a throwing listener rolls it back.
 */
class ResourceUpdated
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  array<string, mixed>  $original  the changed attributes as they were
     * @param  array<string, mixed>  $changes  the changed attributes as they are now
     */
    public function __construct(public Model $model, public string $resource, public array $original = [], public array $changes = [])
    {
        //
    }
}
