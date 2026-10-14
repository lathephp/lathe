<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;
use Lathe\Resources\Resource;

/**
 * Fires inside the transaction after records are attached to a parent, so a throwing listener rolls it back.
 */
class ResourceAttached
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource  the parent's Resource
     * @param  list<int|string>  $ids  the attached related keys
     * @param  array<string, mixed>  $pivot  the pivot values written
     */
    public function __construct(public Model $parent, public string $resource, public string $relationship, public array $ids, public array $pivot = [])
    {
        //
    }
}
